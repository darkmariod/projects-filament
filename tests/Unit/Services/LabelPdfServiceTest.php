<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use App\Services\LabelPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class LabelPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabelPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LabelPdfService();
    }

    /** @test */
    public function it_generates_pdf_for_label(): void
    {
        $label = $this->createFullLabel();

        $pdfContent = $this->service->generateForLabel($label);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringContainsString('%PDF', $pdfContent);
    }

    /** @test */
    public function it_generates_pdf_for_batch(): void
    {
        $batch = $this->createBatchWithLabels(2);

        $pdfContent = $this->service->generateForBatch($batch);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringContainsString('%PDF', $pdfContent);
    }

    /** @test */
    public function it_returns_pdf_for_batch_without_labels(): void
    {
        $batch = $this->createBatchWithLabels(0);

        $pdfContent = $this->service->generateForBatch($batch);

        $this->assertNotEmpty($pdfContent);
        $this->assertStringContainsString('%PDF', $pdfContent);
    }

    /** @test */
    public function it_generates_filename_for_batch(): void
    {
        $batch = $this->createBatchWithLabels(1);

        $filename = $this->service->getFilenameForBatch($batch);

        $this->assertStringContainsString('etiquetas-', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    /** @test */
    public function it_generates_filename_for_label(): void
    {
        $label = $this->createFullLabel();

        $filename = $this->service->getFilenameForLabel($label);

        $this->assertStringContainsString('etiqueta-', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringContainsString($label->serial, $filename);
    }

    /** @test */
    public function it_generates_html_with_required_sections(): void
    {
        $label = $this->createFullLabel();

        $html = $this->invokeBuildLabelHtml($label);

        // Brand appears as the logo image (base64 img) with a text fallback
        $this->assertTrue(
            str_contains($html, 'data:image/png;base64,') || str_contains($html, 'PARAISO'),
            'Expected the Paraiso logo image or the PARAISO text fallback in the HTML'
        );
        $this->assertStringContainsString('DONDE EMPIEZAN TUS SUEÑOS', $html);
        $this->assertStringContainsString('CONTROL DE CALIDAD', $html);
        $this->assertStringContainsString('HECHO EN ECUADOR', $html);
        $this->assertStringContainsString('Informacion de Composicion', $html);
        $this->assertStringContainsString('Trazabilidad', $html);
    }

    /** @test */
    public function it_contains_label_data_in_html(): void
    {
        $label = $this->createFullLabel([
            'serial' => '2605-PDF-V-00000001-3',
        ]);

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString('2605-PDF-V-00000001-3', $html);
        $this->assertStringContainsString($label->product->product_code, $html);
        $this->assertStringContainsString($label->product->productModel->name, $html);
    }

    /** @test */
    public function it_contains_serial_in_html(): void
    {
        $label = $this->createFullLabel([
            'serial' => 'SN-HTML-001',
        ]);

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString('SN-HTML-001', $html);
    }

    /** @test */
    public function it_contains_qr_code_in_html(): void
    {
        $label = $this->createFullLabel([
            'qr_url' => 'https://garantia.test/p/2605-QR-TEST-V-00000001-3',
        ]);

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
    }

    /** @test */
    public function it_contains_composition_info_in_html(): void
    {
        $label = $this->createFullLabel();

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString($label->product->technicalComposition->manufacturer, $html);
        $this->assertStringContainsString($label->product->technicalComposition->manufacturer_ruc, $html);
        $this->assertStringContainsString($label->product->technicalComposition->inen_standard, $html);
    }

    /** @test */
    public function it_contains_batch_info_in_html(): void
    {
        $label = $this->createFullLabel();

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString($label->labelBatch->customer_batch_number, $html);
        $this->assertStringContainsString($label->labelBatch->customer_batch_date->format('d/m/Y'), $html);
    }

    /** @test */
    public function it_contains_no_desprender_text_in_html(): void
    {
        $label = $this->createFullLabel();

        $html = $this->invokeBuildLabelHtml($label);

        $this->assertStringContainsString('NO DESPRENDER LA ETIQUETA', $html);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function invokeBuildLabelHtml(Label $label): string
    {
        $method = new ReflectionMethod($this->service, 'buildLabelHtml');
        return $method->invoke($this->service, $label);
    }

    private function createProductWithComposition(): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
            'code'  => 'TC-' . substr(uniqid(), -6),
        ]);

        $productModel = ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'Test Model PDF',
            'code'           => 'TM-' . substr(uniqid(), -6),
            'type'           => 'Colchón',
            'class'          => 'A',
            'warranty_years' => 5,
            'active'         => true,
        ]);

        $product = Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'CR SE ESP PDF',
            'product_code'     => 'TP-' . substr(uniqid(), -6),
            'barcode'          => 'BC-' . substr(uniqid(), -6),
            'measurements_text'=> '150x190x30',
            'active'           => true,
        ]);

        $product->technicalComposition()->create([
            'commercial_name'           => 'Colchón Paraíso PDF',
            'product_family'            => 'Colchones',
            'cover_material'            => 'Tela',
            'springs'                   => 'Resortes Bonnell',
            'foam_description'          => 'Espuma de alta densidad',
            'conservation_instructions' => 'Mantener en lugar seco',
            'manufacturer'              => 'Paraíso del Ecuador',
            'manufacturer_ruc'          => '1790098230001',
            'manufacturer_address'      => 'AV. Panamericana Sur KM 25',
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
            'customer_batch_number' => 'CBN-PDF-TEST',
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
            'barcode'         => 'BC-PDF-TEST',
            'qr_url'          => 'https://garantia.test/p/' . uniqid(),
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
            'customer_batch_number' => 'CBN-PDF-BATCH',
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
                'serial'          => "SN-PDF-BATCH-{$i}",
                'sequence_number' => $i,
                'barcode'         => "BC-PDF-BATCH-{$i}",
                'qr_url'          => "https://garantia.test/p/batch-{$i}",
                'status'          => 'available',
            ]);
        }

        return $batch;
    }
}
