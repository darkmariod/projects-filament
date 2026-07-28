<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Exports\LabelsExport;
use App\Models\Category;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class LabelsExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function export_headings_are_correct(): void
    {
        $export = new LabelsExport();

        $headings = $export->headings();

        $expectedHeadings = [
            'Fecha generación',
            'Código de lote',
            'Código único',
            'Producto',
            'Modelo',
            'Medida',
            'Estado',
            'Fecha impresión',
            'Fecha registro garantía',
            'Cliente',
        ];

        $this->assertSame($expectedHeadings, $headings);
        $this->assertCount(10, $headings);
    }

    /** @test */
    public function empty_dataset_returns_valid_collection(): void
    {
        $export = new LabelsExport();

        $collection = $export->collection();

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    /** @test */
    public function export_with_data_maps_correctly(): void
    {
        $label = $this->createLabel();

        $export = new LabelsExport();
        $collection = $export->collection();

        $this->assertCount(1, $collection);

        $mapped = $export->map($label);

        $this->assertSame($label->serial, $mapped[2]);
        $this->assertSame($label->product->name, $mapped[3]);
        $this->assertSame($label->product->productModel->name, $mapped[4]);
        $this->assertSame('Disponible', $mapped[6]);
    }

    /** @test */
    public function export_with_multiple_records_returns_all(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        $batch = $this->createBatch($product, $user, 5);

        $export = new LabelsExport();
        $collection = $export->collection();

        $this->assertCount(5, $collection);
    }

    /** @test */
    public function export_status_mapping_is_correct(): void
    {
        $export = new LabelsExport();

        $label = $this->createLabel();
        $available = $export->map($label);
        $this->assertStringContainsString('Disponible', $available[6]);

        $label->update(['status' => 'printed']);
        $printed = $export->map($label->fresh());
        $this->assertStringContainsString('Impreso', $printed[6]);

        $label->update(['status' => 'registered']);
        $registered = $export->map($label->fresh());
        $this->assertStringContainsString('Registrado', $registered[6]);

        $label->update(['status' => 'anulled']);
        $anulled = $export->map($label->fresh());
        $this->assertStringContainsString('Anulado', $anulled[6]);
    }

    /** @test */
    public function export_supports_styles_method(): void
    {
        $export = new LabelsExport();

        $styles = $export->styles(new Worksheet());

        $this->assertIsArray($styles);
        $this->assertArrayHasKey(1, $styles);
    }

    /** @test */
    public function export_filters_by_batch(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        $batch1 = $this->createBatch($product, $user, 2);
        $batch2 = $this->createBatch($product, $user, 3);

        $export = new LabelsExport(['label_batch_id' => $batch1->id]);
        $collection = $export->collection();

        $this->assertCount(2, $collection);
    }

    /** @test */
    public function export_filters_by_status(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        $batch = $this->createBatch($product, $user, 5);

        // Set one label as printed
        $label = $batch->labels()->first();
        $label->update(['status' => 'printed']);

        $export = new LabelsExport(['status' => 'printed']);
        $collection = $export->collection();

        $this->assertCount(1, $collection);
    }

    /** @test */
    public function export_maps_customer_name(): void
    {
        $label = $this->createLabel();

        $export = new LabelsExport();

        $mapped = $export->map($label);

        // No warranty -> empty customer
        $this->assertSame('', $mapped[9]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function createProduct(?string $productCode = null): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
            'code'  => 'TC-' . substr(uniqid(), -6),
        ]);

        $productModel = ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'Test Model',
            'code'           => 'TM-' . substr(uniqid(), -6),
            'warranty_years' => 1,
            'active'         => true,
        ]);

        $code = $productCode ?? ('TP-' . substr(uniqid(), -8));

        return Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'Test Product',
            'product_code'     => $code,
            'barcode'          => 'BC-' . $code,
            'measurements_text'=> '150x190x30',
            'active'           => true,
        ]);
    }

    private function createProductModel(): ProductModel
    {
        $category = Category::create([
            'name' => 'Cat',
            'code'  => 'C-' . substr(uniqid(), -6),
        ]);

        return ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'Model',
            'code'           => 'M-' . substr(uniqid(), -6),
            'warranty_years' => 1,
            'active'         => true,
        ]);
    }

    private function createBatch(Product $product, User $user, int $labelCount): LabelBatch
    {
        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date'   => now()->format('Y-m-d'),
            'quantity'              => $labelCount,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => 'Test Operator',
        ]);

        for ($i = 1; $i <= $labelCount; $i++) {
            Label::create([
                'label_batch_id'  => $batch->id,
                'product_id'      => $product->id,
                'serial'          => 'SN-' . str_pad((string) $i, 8, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4),
                'sequence_number' => $i,
                'barcode'         => 'BC-' . $i,
                'qr_url'          => 'https://test.test/qr/' . uniqid(),
                'status'          => 'available',
            ]);
        }

        return $batch;
    }

    private function createLabel(): Label
    {
        $product = $this->createProduct();
        $user = User::factory()->create();
        $batch = $this->createBatch($product, $user, 1);

        return $batch->labels()->first();
    }
}
