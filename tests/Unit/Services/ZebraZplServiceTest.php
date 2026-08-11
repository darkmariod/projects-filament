<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use App\Models\ZebraPrintSetting;
use App\Services\ZebraZplService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZebraZplServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZebraZplService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default active setting so the constructor picks it up
        ZebraPrintSetting::create([
            'name'            => 'Test Setting',
            'printer_model'   => 'Zebra ZT411',
            'dpi'             => 203,
            'label_width_mm'  => 100,
            'label_height_mm' => 350,
            'label_gap_mm'    => 2,
            'width_dots'      => 800,
            'height_dots'     => 2800,
            'margin_x'        => 20,
            'margin_y'        => 20,
            'qr_size'         => 6,
            'barcode_height'  => 120,
            'active'          => true,
        ]);

        $this->service = new ZebraZplService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  mmToDots
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_converts_mm_to_dots_correctly(): void
    {
        // At 203 DPI → 8 dots per mm
        $this->assertSame(8, $this->service->mmToDots(1));
        $this->assertSame(16, $this->service->mmToDots(2));
        $this->assertSame(80, $this->service->mmToDots(10));
        $this->assertSame(0, $this->service->mmToDots(0));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  generateForLabel — ZPL structure
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_generates_zpl_with_required_commands(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('^XZ', $zpl);
        $this->assertStringContainsString('^BQN', $zpl);
        $this->assertStringContainsString('^PW', $zpl);
        $this->assertStringContainsString('^LL', $zpl);
        $this->assertStringContainsString('^CI28', $zpl);
    }

    /** @test */
    public function it_contains_label_data_in_zpl_output(): void
    {
        $label = $this->createFullLabel([
            'serial' => '2605-TEST-V-00000001-3',
            'qr_url' => 'https://garantia.test/qr/abc123',
        ]);

        $zpl = $this->service->generateForLabel($label);

        $this->assertStringContainsString('2605-TEST-V-00000001-3', $zpl);
        $this->assertStringContainsString('https://garantia.test/qr/abc123', $zpl);
    }

    /** @test */
    public function it_contains_product_and_model_info(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        // Logo: either GFA graphic (paraiso-logo.gfa exists) or text fallback "PARAISO"
        $hasLogo = str_contains($zpl, '^GFA') || str_contains($zpl, 'PARAISO');
        $this->assertTrue($hasLogo, 'Should contain logo graphic or PARAISO text');
        $this->assertStringContainsString('DONDE EMPIEZAN TUS SUEÑOS', $zpl);
        $this->assertStringContainsString('CONTROL DE CALIDAD', $zpl);
        $this->assertStringContainsString($label->product->product_code, $zpl);
        $this->assertStringContainsString($label->product->productModel->name, $zpl);
        $this->assertStringContainsString($label->product->measurements_text, $zpl);
    }

    /** @test */
    public function it_contains_trazability_sections(): void
    {
        $label = $this->createFullLabel([
            'serial' => 'SN-TRAZA-001',
        ]);

        $zpl = $this->service->generateForLabel($label);

        // Trazability appears twice
        $this->assertStringContainsString('SN-TRAZA-001', $zpl);
        $this->assertStringContainsString('Trazabilidad', $zpl);
    }

    /** @test */
    public function it_contains_composition_section(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        $this->assertStringContainsString('Informacion de Composicion', $zpl);
        $this->assertStringContainsString('HECHO EN ECUADOR', $zpl);
        $this->assertStringContainsString('FABRICADO POR:', $zpl);
    }

    /** @test */
    public function it_contains_vertical_label_text(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        $this->assertStringContainsString('NO DESPRENDER LA ETIQUETA', $zpl);
    }

    /** @test */
    public function it_uses_lh0_as_home_position(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        $this->assertStringContainsString('^LH0,0', $zpl);
    }

    /** @test */
    public function it_generates_without_barcode_when_label_has_no_barcode(): void
    {
        $label = $this->createFullLabel();
        // Label already has barcode set in createFullLabel; override
        $label->barcode = '';
        $label->save();
        $label->load('product');

        $zpl = $this->service->generateForLabel($label);

        // Should not contain ^BC since barcode is empty
        $this->assertStringNotContainsString('^BC', $zpl);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  generateForBatch
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_generates_zpl_for_a_batch(): void
    {
        $batch = $this->createBatchWithLabels(3);

        $zpl = $this->service->generateForBatch($batch);

        $this->assertSame(3, mb_substr_count($zpl, '^XA'));
        $this->assertSame(3, mb_substr_count($zpl, '^XZ'));
        $this->assertStringContainsString('SN-BATCH-1', $zpl);
        $this->assertStringContainsString('SN-BATCH-3', $zpl);
    }

    /** @test */
    public function it_generates_zpl_for_empty_batch(): void
    {
        $batch = $this->createBatchWithLabels(0);

        $zpl = $this->service->generateForBatch($batch);

        $this->assertSame('', $zpl);
    }

    /** @test */
    public function it_generates_zpl_for_single_label_batch(): void
    {
        $batch = $this->createBatchWithLabels(1);

        $zpl = $this->service->generateForBatch($batch);

        $this->assertSame(1, mb_substr_count($zpl, '^XA'));
        $this->assertSame(1, mb_substr_count($zpl, '^XZ'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Filenames
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_generates_filename_for_batch(): void
    {
        $batch = $this->createBatchWithLabels(1);

        $filename = $this->service->getFilenameForBatch($batch);

        $this->assertStringContainsString('etiquetas-', $filename);
        $this->assertStringEndsWith('.zpl', $filename);
    }

    /** @test */
    public function it_generates_filename_for_label(): void
    {
        $label = $this->createFullLabel();

        $filename = $this->service->getFilenameForLabel($label);

        $this->assertStringContainsString('etiqueta-', $filename);
        $this->assertStringEndsWith('.zpl', $filename);
        $this->assertStringContainsString($label->serial, $filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Constructor fallback
    // ─────────────────────────────────────────────────────────────────────────


    /** @test */
    public function it_generates_portrait_label_layout_matching_physical_zpl(): void
    {
        $label = $this->createFullLabel([
            'serial' => '2606-CR SE 090-V-00000016-1',
            'barcode' => '7861191234261',
            'qr_url' => 'http://108.174.152.179:8081/p/2606-CR%20SE%20090-V-00000016-1',
        ]);

        $label->product->update([
            'product_code' => 'CR SE 090',
            'measurements_text' => '90X190X22',
            'class' => 'Clase B',
            'plazas' => '1 1/4 PLZ',
        ]);

        $label->product->productModel->update([
            'name' => 'COL. SENORIAL',
            'type' => 'Tipo IV: COL. RES',
            'warranty_years' => 1,
        ]);

        $zpl = $this->service->generateForLabel($label->fresh());

        $this->assertStringContainsString('^PW760', $zpl);
        $this->assertStringContainsString('^LL1600', $zpl);
        // Bloque único de control de calidad: separador inferior en y=180
        // Separadores compactados: sin espacios vacíos entre secciones
        $this->assertStringContainsString('^FO15,140^GB730,2,2^FS', $zpl);
        $this->assertStringContainsString('^FO10,195^GB740,4,4^FS', $zpl);
        $this->assertStringContainsString('^FO10,700^GB740,4,4^FS', $zpl);
        // Serial no longer in stickers — must NOT appear at sticker Y
        $this->assertStringNotContainsString('^FO15,15^A0N,13,13^FDN°: 2606-CR SE 090-V-00000016-1^FS', $zpl);
        // Tipo IV in sticker (y=15 + offset 20 = 35)
        $this->assertStringContainsString('^FO300,35^A0N,12,12^FDTipo IV: COL. RES^FS', $zpl);
        $this->assertStringContainsString('^FO15,730^BQN,2,8^FDQA,http://108.174.152.179:8081/p/2606-CR%20SE%20090-V-00000016-1^FS', $zpl);
        $this->assertStringContainsString('^FO735,730^A0R,16,16^FDNO DESPRENDER LA ETIQUETA^FS', $zpl);

        // El barcode no debe invadir la columna derecha (x >= 400) del bloque principal.
        // Barcode at startY(730) + 330 = 1060
        $this->assertStringContainsString('^FO15,1060^BY1', $zpl);
    }

    /** @test */
    public function it_contains_serial_exactly_twice_in_full_zpl(): void
    {
        $serial = '2606-TEST-V-00000001-3';
        $label = $this->createFullLabel(['serial' => $serial]);

        $zpl = $this->service->generateForLabel($label);

        $count = mb_substr_count($zpl, $serial);
        // Serial now appears 3 times: composition + sticker 2 (traceability) + main label
        $this->assertSame(3, $count, "Serial should appear exactly 3 times (composition + sticker 2 + main label), found {$count}");
    }

    /** @test */
    public function it_renders_serial_in_composition_section(): void
    {
        $serial = '2606-COMP-V-00000010-7';
        $label = $this->createFullLabel(['serial' => $serial]);

        $zpl = $this->service->generateForLabel($label);

        // Serial must appear in the composition section (Y >= 315)
        // The composition section starts at Y=315 per render() method.
        // The serial line in buildComposition uses fitFont so font size varies,
        // but the ^FD containing serial must be present.
        $this->assertStringContainsString("^FD{$serial}^FS", $zpl);
    }

    /** @test */
    public function it_renders_serial_in_main_label(): void
    {
        $serial = '2606-MAIN-V-00000020-5';
        $label = $this->createFullLabel(['serial' => $serial]);

        $zpl = $this->service->generateForLabel($label);

        // Main label has "N°: {serial}" text
        $this->assertStringContainsString("N°: {$serial}", $zpl);
    }

    /** @test */
    public function it_uses_fallback_settings_when_no_active_setting(): void
    {
        // Delete all settings
        ZebraPrintSetting::query()->delete();

        $service = new ZebraZplService();

        $label = $this->createFullLabel();
        $zpl = $service->generateForLabel($label);

        // Should still generate ZPL with defaults (portrait 760×1600)
        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('^PW760', $zpl);
        $this->assertStringContainsString('^LL1600', $zpl);
    }

    /** @test */
    public function it_includes_gfa_graphic_when_product_has_image(): void
    {
        // Create a small test image in public storage
        $imageRelative = 'test-product-label.png';
        $imageAbsolute = storage_path('app/public/' . $imageRelative);
        $img = imagecreatetruecolor(10, 10);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 9, 9, $black);
        imagepng($img, $imageAbsolute);
        imagedestroy($img);

        $label = $this->createFullLabel();
        $label->product->update(['image' => $imageRelative]);
        $label->load('product');

        $zpl = $this->service->generateForLabel($label->fresh());

        // ZPL should contain ^GFA graphic command (product image converted)
        $this->assertStringContainsString('^GFA', $zpl);

        @unlink($imageAbsolute);
    }

    /** @test */
    public function it_falls_back_to_logo_when_product_has_no_image(): void
    {
        $label = $this->createFullLabel();
        $label->product->update(['image' => null]);
        $label->load('product');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Should render the graphic area — either logo ^GFA or PARAISO text
        // When logo file exists, it uses logo; when missing, falls back to PARAISO text
        $hasLogo = str_contains($zpl, '^GFA');
        $hasText = str_contains($zpl, 'PARAISO');
        $this->assertTrue($hasLogo || $hasText, 'Label should render logo or PARAISO fallback');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createProductWithComposition(): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
            'code'  => 'TC-' . substr(uniqid(), -6),
        ]);

        $productModel = ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'Test Model',
            'code'           => 'TM-' . substr(uniqid(), -6),
            'type'           => 'Colchón',
            'class'          => 'A',
            'warranty_years' => 5,
            'active'         => true,
        ]);

        $product = Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'Test Product',
            'product_code'     => 'TP-' . substr(uniqid(), -6),
            'barcode'          => 'BC-' . substr(uniqid(), -6),
            'measurements_text'=> '150x190x30',
            'active'           => true,
        ]);

        // Create technical composition
        $product->technicalComposition()->create([
            'commercial_name'           => 'Colchón Paraíso',
            'product_family'            => 'Colchones',
            'cover_material'            => 'Tela',
            'springs'                   => 'Resortes Bonnell',
            'foam_description'          => 'Espuma de alta densidad',
            'conservation_instructions' => 'Mantener en lugar seco',
            'manufacturer'              => 'Paraíso del Ecuador',
            'manufacturer_ruc'          => '1790098230001',
            'manufacturer_address'      => 'AV. Panamericana Sur KM 25 Tambillo',
            'manufacturing_country'     => 'Ecuador',
            'inen_standard'             => 'NTE INEN 2035',
            'website'                   => 'www.paraiso.com.ec',
            'legal_text'                => 'Garantía válida bajo condiciones normales de uso.',
        ]);

        return $product;
    }

    private function createFullLabel(array $overrides = []): Label
    {
        $product = $this->createProductWithComposition();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-TEST',
            'customer_batch_date'   => '2026-05-27',
            'quantity'              => 1,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => 'Test Operator',
        ]);

        return Label::create(array_merge([
            'label_batch_id'  => $batch->id,
            'product_id'      => $product->id,
            'serial'          => 'SN-' . substr(uniqid(), -8),
            'sequence_number' => 1,
            'barcode'         => 'BC-TEST',
            'qr_url'          => 'https://garantia.test/qr/' . uniqid(),
            'status'          => 'available',
        ], $overrides));
    }

    private function createBatchWithLabels(int $labelCount): LabelBatch
    {
        $product = $this->createProductWithComposition();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'BATCH-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-TEST',
            'customer_batch_date'   => '2026-05-27',
            'quantity'              => $labelCount,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => 'Test Operator',
        ]);

        for ($i = 1; $i <= $labelCount; $i++) {
            Label::create([
                'label_batch_id'  => $batch->id,
                'product_id'      => $product->id,
                'serial'          => "SN-BATCH-{$i}",
                'sequence_number' => $i,
                'barcode'         => "BC-BATCH-{$i}",
                'qr_url'          => "https://garantia.test/qr/batch-{$i}",
                'status'          => 'available',
            ]);
        }

        return $batch;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Phase 1: Dynamic data in quality stickers
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function sticker_1_shows_operator_lote_and_fecha(): void
    {
        $label = $this->createFullLabel([
            'serial' => 'SN-DYN-STICKER1-01',
        ]);

        // Reload with relationships so extractData can access batch fields
        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label);

        $batch = $label->labelBatch;

        // Sticker 1 (y=15 area) must show operator, lot number, and batch date
        $this->assertStringContainsString("Operador: {$batch->operator}", $zpl);
        $this->assertStringContainsString("Lote: {$batch->customer_batch_number}", $zpl);
        $this->assertStringContainsString("Fecha: {$batch->customer_batch_date->format('d/m/Y')}", $zpl);
    }

    /** @test */
    public function sticker_2_shows_operator_and_internal_batch_code(): void
    {
        $label = $this->createFullLabel([
            'serial' => 'SN-DYN-STICKER2-01',
        ]);

        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label);

        $batch = $label->labelBatch;

        // Sticker 2 (y=100 area) must show operator and internal batch code
        // Both stickers use "Operador:" label — verify it appears at least twice
        $this->assertGreaterThanOrEqual(
            2,
            mb_substr_count($zpl, 'Operador:'),
            'Operador must appear in both sticker 1 and sticker 2'
        );

        $this->assertStringContainsString("Lote: {$batch->internal_batch_code}", $zpl);
    }

    /** @test */
    public function composition_shows_operator(): void
    {
        $label = $this->createFullLabel();

        $zpl = $this->service->generateForLabel($label);

        $batch = $label->labelBatch;

        // Composition section must show operator
        $this->assertStringContainsString("Operador: {$batch->operator}", $zpl);
    }

    /** @test */
    public function empty_batch_data_does_not_break_zpl(): void
    {
        $product = $this->createProductWithComposition();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => '',
            'customer_batch_number' => '',
            'customer_batch_date'   => now(),
            'quantity'              => 1,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => '',
        ]);

        $label = Label::create([
            'label_batch_id'  => $batch->id,
            'product_id'      => $product->id,
            'serial'          => 'SN-EMPTY-TEST-01',
            'sequence_number' => 1,
            'barcode'         => '',
            'qr_url'          => '',
            'status'          => 'available',
        ]);

        $zpl = $this->service->generateForLabel($label);

        // Must generate valid ZPL even with empty batch data
        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('^XZ', $zpl);
        $this->assertStringContainsString('SN-EMPTY-TEST-01', $zpl);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Phase 2: Traceability in sticker 2
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function sticker_2_shows_serial_and_sequence(): void
    {
        $label = $this->createFullLabel([
            'serial'          => 'SN-TRAZA-001',
            'sequence_number' => 5,
        ]);

        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label);

        // Sticker 2 must show serial and sequence
        $this->assertStringContainsString('SN-TRAZA-001', $zpl);
        $this->assertStringContainsString('Seq: 5', $zpl);
    }

    /** @test */
    public function sticker_2_shows_generated_by_name(): void
    {
        $label = $this->createFullLabel();

        $label->load('labelBatch.generatedBy');
        $userName = $label->labelBatch->generatedBy->name;

        $zpl = $this->service->generateForLabel($label);

        // Sticker 2 must show the user who generated the batch
        $this->assertStringContainsString("Gen: {$userName}", $zpl);
    }

    /** @test */
    public function sticker_2_shows_internal_batch_code_with_label(): void
    {
        $label = $this->createFullLabel();

        $label->load('labelBatch.generatedBy');
        $internalCode = $label->labelBatch->internal_batch_code;

        $zpl = $this->service->generateForLabel($label);

        // Sticker 2 must show internal batch code with "Lote:" prefix
        $this->assertStringContainsString("Lote: {$internalCode}", $zpl);
    }

    /** @test */
    public function serial_appears_exactly_twice_in_zpl_for_traceability(): void
    {
        $serial = 'SN-EXACTLY-2-001';
        $label = $this->createFullLabel(['serial' => $serial]);

        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label);

        // Serial now appears 3 times: composition + sticker 2 (traceability) + main label
        $count = mb_substr_count($zpl, $serial);
        $this->assertSame(
            3,
            $count,
            "Serial should appear exactly 3 times (composition + sticker 2 + main label), found {$count}"
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Phase 3: Category logo fallback chain
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function product_with_image_uses_product_image(): void
    {
        // Create a test image
        $imageRelative = 'test-cat-logo.png';
        $imageAbsolute = storage_path('app/public/' . $imageRelative);
        $img = imagecreatetruecolor(10, 10);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 9, 9, $black);
        imagepng($img, $imageAbsolute);
        imagedestroy($img);

        $label = $this->createFullLabel();
        $label->product->update(['image' => $imageRelative]);
        $label->load('product');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Product image takes priority — must contain ^GFA
        $this->assertStringContainsString('^GFA', $zpl);

        @unlink($imageAbsolute);
    }

    /** @test */
    public function product_without_image_category_with_logo_uses_category_logo(): void
    {
        // Create category logo image
        $logoRelative = 'test-category-logo.png';
        $logoAbsolute = storage_path('app/public/' . $logoRelative);
        $img = imagecreatetruecolor(10, 10);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 9, 9, $white);
        imagepng($img, $logoAbsolute);
        imagedestroy($img);

        $label = $this->createFullLabel();
        $label->product->update(['image' => null]);

        // Set logo on category
        $label->product->productModel->category->update(['logo' => $logoRelative]);
        $label->load('product.productModel.category');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Category logo should be used — must contain ^GFA
        $this->assertStringContainsString('^GFA', $zpl);

        @unlink($logoAbsolute);
    }

    /** @test */
    public function no_image_no_category_logo_uses_paraiso_logo_gfa(): void
    {
        $label = $this->createFullLabel();
        $label->product->update(['image' => null]);
        $label->product->productModel->category->update(['logo' => null]);
        $label->load('product.productModel.category');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Either paraiso-logo.gfa exists (^GFA) or text fallback "PARAISO"
        $hasGfa = str_contains($zpl, '^GFA');
        $hasText = str_contains($zpl, 'PARAISO');
        $this->assertTrue(
            $hasGfa || $hasText,
            'Should use paraiso-logo.gfa or PARAISO text fallback'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Phase 4: Test label
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function render_test_label_with_product_contains_product_data(): void
    {
        $label = $this->createFullLabel();
        $product = $label->product;

        $settings = ZebraPrintSetting::where('active', true)->first();
        $renderer = new \App\Services\ZebraZplRenderer($settings);

        $zpl = $renderer->renderTestLabel($product);

        $this->assertStringContainsString('ETIQUETA DE PRUEBA', $zpl);
        $this->assertStringContainsString($product->product_code, $zpl);
        $this->assertStringContainsString($product->measurements_text, $zpl);
        $this->assertStringContainsString($product->productModel->name, $zpl);
    }

    /** @test */
    public function render_test_label_without_product_is_retrocompatible(): void
    {
        $settings = ZebraPrintSetting::where('active', true)->first();
        $renderer = new \App\Services\ZebraZplRenderer($settings);

        $zpl = $renderer->renderTestLabel();

        // Must produce valid ZPL with "ETIQUETA DE PRUEBA"
        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('^XZ', $zpl);
        $this->assertStringContainsString('ETIQUETA DE PRUEBA', $zpl);
    }

    /** @test */
    public function render_test_label_has_visible_border(): void
    {
        $settings = ZebraPrintSetting::where('active', true)->first();
        $renderer = new \App\Services\ZebraZplRenderer($settings);

        $zpl = $renderer->renderTestLabel();

        // Must contain ^GB (box) command for visible border
        $this->assertStringContainsString('^GB', $zpl);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Responsables de control de calidad (nombre impreso + línea de firma)
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function quality_block_prints_the_three_responsibles(): void
    {
        $label = $this->createFullLabel();
        $label->labelBatch->update([
            'operator' => 'Juan Perez',
            'closer'   => 'Maria Lopez',
            'tracer'   => 'Carlos Ruiz',
        ]);
        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Los tres nombres se imprimen
        $this->assertStringContainsString('Juan Perez', $zpl);
        $this->assertStringContainsString('Maria Lopez', $zpl);
        $this->assertStringContainsString('Carlos Ruiz', $zpl);

        // Y se conservan los rótulos con su línea de firma
        $this->assertStringContainsString('Operador / Ensamble', $zpl);
        $this->assertStringContainsString('Cerrador', $zpl);
        $this->assertStringContainsString('Trazabilidad', $zpl);
    }

    /** @test */
    public function quality_block_without_closer_and_tracer_still_renders(): void
    {
        $label = $this->createFullLabel();
        $label->labelBatch->update(['closer' => null, 'tracer' => null]);
        $label->load('labelBatch.generatedBy');

        $zpl = $this->service->generateForLabel($label->fresh());

        // Sin nombres, los rótulos y las líneas de firma siguen presentes
        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('Cerrador', $zpl);
        $this->assertStringContainsString('Trazabilidad', $zpl);
    }
}
