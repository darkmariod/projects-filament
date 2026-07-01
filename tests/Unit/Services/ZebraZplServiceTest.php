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

        // Brand appears as the Paraiso logo graphic (^GFA) with a text fallback
        $this->assertTrue(
            str_contains($zpl, '^GFA') || str_contains($zpl, 'PARAISO'),
            'Expected the Paraiso logo graphic or the PARAISO text fallback in the ZPL'
        );
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
        $this->assertStringContainsString('^FO15,100^GB730,2,2^FS', $zpl);
        $this->assertStringContainsString('^FO10,295^GB740,4,4^FS', $zpl);
        $this->assertStringContainsString('^FO10,620^GB740,4,4^FS', $zpl);
        $this->assertStringContainsString('^FO15,15^A0N,13,13^FDN°: 2606-CR SE 090-V-00000016-1^FS', $zpl);
        $this->assertStringContainsString('^FO300,35^A0N,12,12^FDTipo IV: COL. RES^FS', $zpl);
        $this->assertStringContainsString('^FO15,640^BQN,2,5^FDQA,http://108.174.152.179:8081/p/2606-CR%20SE%20090-V-00000016-1^FS', $zpl);
        $this->assertStringContainsString('^FO735,640^A0R,14,14^FDNO DESPRENDER LA ETIQUETA^FS', $zpl);

        // El barcode no debe invadir la columna derecha (x >= 400) del bloque principal.
        $this->assertStringContainsString('^FO15,845^BY1', $zpl);
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
}
