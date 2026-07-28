<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Exports\LabelBatchesExport;
use App\Models\Category;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class LabelBatchesExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function export_headings_are_correct(): void
    {
        $export = new LabelBatchesExport();

        $headings = $export->headings();

        $expectedHeadings = [
            'Código interno',
            'Producto',
            'Cantidad',
            'Número lote cliente',
            'Operador',
            'Generado por',
            'Fecha generación',
            'Observaciones',
            'Estado',
        ];

        $this->assertSame($expectedHeadings, $headings);
        $this->assertCount(9, $headings);
    }

    /** @test */
    public function empty_dataset_returns_valid_collection(): void
    {
        $export = new LabelBatchesExport();

        $collection = $export->collection();

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    /** @test */
    public function export_with_data_maps_correctly(): void
    {
        $batch = $this->createBatch(3);

        $export = new LabelBatchesExport();
        $collection = $export->collection();

        $this->assertCount(1, $collection);

        $mapped = $export->map($batch);

        $this->assertSame($batch->internal_batch_code, $mapped[0]);
        $this->assertSame($batch->product->name, $mapped[1]);
        $this->assertSame(3, $mapped[2]);
        $this->assertSame($batch->customer_batch_number, $mapped[3]);
        $this->assertSame($batch->operator, $mapped[4]);
        $this->assertSame('Generado', $mapped[8]);
    }

    /** @test */
    public function export_with_multiple_records_returns_all(): void
    {
        $this->createBatch(1);
        $this->createBatch(2);
        $this->createBatch(3);

        $export = new LabelBatchesExport();
        $collection = $export->collection();

        $this->assertCount(3, $collection);
    }

    /** @test */
    public function export_status_mapping_is_correct(): void
    {
        $export = new LabelBatchesExport();

        $batch = $this->createBatch(1);
        $generated = $export->map($batch);
        $this->assertStringContainsString('Generado', $generated[8]);

        $batch->update(['status' => 'active']);
        $active = $export->map($batch->fresh());
        $this->assertStringContainsString('Activo', $active[8]);

        $batch->update(['status' => 'printed']);
        $printed = $export->map($batch->fresh());
        $this->assertStringContainsString('Impreso', $printed[8]);

        $batch->update(['status' => 'anulled']);
        $anulled = $export->map($batch->fresh());
        $this->assertStringContainsString('Anulado', $anulled[8]);
    }

    /** @test */
    public function export_supports_styles_method(): void
    {
        $export = new LabelBatchesExport();

        $styles = $export->styles(new Worksheet());

        $this->assertIsArray($styles);
        $this->assertArrayHasKey(1, $styles);
    }

    /** @test */
    public function export_filters_by_status(): void
    {
        $this->createBatch(1, 'generated');
        $this->createBatch(2, 'active');
        $this->createBatch(3, 'generated');

        $export = new LabelBatchesExport(['status' => 'generated']);
        $collection = $export->collection();

        $this->assertCount(2, $collection);
    }

    /** @test */
    public function export_maps_customer_batch_number(): void
    {
        $batch = $this->createBatch(3);

        $export = new LabelBatchesExport();

        $mapped = $export->map($batch);

        $this->assertSame($batch->customer_batch_number, $mapped[3]);
        $this->assertNotEmpty($mapped[3]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function createProduct(): Product
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

        return Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'Test Product',
            'product_code'     => 'TP-' . substr(uniqid(), -8),
            'barcode'          => 'BC-' . substr(uniqid(), -6),
            'active'           => true,
        ]);
    }

    private function createBatch(int $labelCount, string $status = 'generated'): LabelBatch
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date'   => now()->format('Y-m-d'),
            'quantity'              => $labelCount,
            'generated_by_user_id'  => $user->id,
            'status'                => $status,
            'operator'              => 'Test Operator',
            'observations'          => 'Test observations',
        ]);

        for ($i = 1; $i <= $labelCount; $i++) {
            Label::create([
                'label_batch_id'  => $batch->id,
                'product_id'      => $product->id,
                'serial'          => 'SN-BATCH-' . $i . '-' . substr(uniqid(), -4),
                'sequence_number' => $i,
                'barcode'         => 'BC-' . $i,
                'qr_url'          => 'https://test.test/qr/' . uniqid(),
                'status'          => 'available',
            ]);
        }

        return $batch;
    }


}
