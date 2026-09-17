<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelBatchTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function produced_count_equals_printed_plus_registered(): void
    {
        $batch = $this->createBatch();

        // 70 impresas, 30 anuladas antes de imprimir
        $this->addLabels($batch, 70, 'printed');
        $this->addLabels($batch, 30, 'anulled');

        $this->assertSame(70, $batch->producedCount());
        $this->assertSame(30, $batch->cancelledBeforePrintCount());
    }

    /** @test */
    public function produced_count_counts_registered_labels_too(): void
    {
        $batch = $this->createBatch();

        $this->addLabels($batch, 20, 'registered');
        $this->addLabels($batch, 10, 'available');

        $this->assertSame(20, $batch->producedCount());
    }

    /** @test */
    public function produced_count_ignores_available_and_anulled(): void
    {
        $batch = $this->createBatch();

        $this->addLabels($batch, 15, 'available');
        $this->addLabels($batch, 5, 'anulled');

        $this->assertSame(0, $batch->producedCount());
        $this->assertSame(5, $batch->cancelledBeforePrintCount());
    }

    /** @test */
    public function produced_count_returns_zero_for_empty_batch(): void
    {
        $batch = $this->createBatch();

        $this->assertSame(0, $batch->producedCount());
        $this->assertSame(0, $batch->cancelledBeforePrintCount());
    }

    /** @test */
    public function counts_are_isolated_per_batch(): void
    {
        $batchA = $this->createBatch();
        $batchB = $this->createBatch();

        $this->addLabels($batchA, 10, 'printed');
        $this->addLabels($batchB, 3, 'printed');
        $this->addLabels($batchB, 7, 'anulled');

        $this->assertSame(10, $batchA->producedCount());
        $this->assertSame(3, $batchB->producedCount());
        $this->assertSame(7, $batchB->cancelledBeforePrintCount());
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

    private function createBatch(): LabelBatch
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        return LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date'   => now()->format('Y-m-d'),
            'quantity'              => 0,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => 'Test Operator',
        ]);
    }

    private function addLabels(LabelBatch $batch, int $count, string $status): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Label::create([
                'label_batch_id'  => $batch->id,
                'product_id'      => $batch->product_id,
                'serial'          => 'SN-' . substr(uniqid(), -10),
                'sequence_number' => $i,
                'barcode'         => 'BC-' . $i,
                'qr_url'          => 'https://test.test/qr/' . uniqid(),
                'status'          => $status,
            ]);
        }
    }
}
