<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El cliente pidió que el lote sea MMYY + secuencial (agosto 2026 → 0826-001)
 * y que rote automáticamente cada mes.
 */
class LabelBatchNumberTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Colchones', 'code' => 'COL']);

        $productModel = ProductModel::create([
            'category_id'    => $category->id,
            'name'           => 'CR SE ESP',
            'code'           => 'CRSEESP',
            'warranty_years' => 5,
            'active'         => true,
        ]);

        $this->product = Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'CR SE ESP 150x190',
            'product_code'     => 'A50135',
            'active'           => true,
        ]);

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function first_batch_of_the_month_uses_mmyy_prefix(): void
    {
        Carbon::setTestNow('2026-08-04 10:00:00');

        $batch = $this->makeBatch();

        // agosto 2026 → 08 + 26
        $this->assertSame('0826-001', $batch->customer_batch_number);
    }

    /** @test */
    public function sequence_increments_within_the_same_month(): void
    {
        Carbon::setTestNow('2026-08-04 10:00:00');

        $this->assertSame('0826-001', $this->makeBatch()->customer_batch_number);
        $this->assertSame('0826-002', $this->makeBatch()->customer_batch_number);
        $this->assertSame('0826-003', $this->makeBatch()->customer_batch_number);
    }

    /** @test */
    public function prefix_rotates_and_sequence_resets_next_month(): void
    {
        Carbon::setTestNow('2026-08-31 23:00:00');
        $this->assertSame('0826-001', $this->makeBatch()->customer_batch_number);

        // Al cambiar de mes el prefijo rota y el secuencial vuelve a 001
        Carbon::setTestNow('2026-09-01 00:30:00');
        $this->assertSame('0926-001', $this->makeBatch()->customer_batch_number);
    }

    /** @test */
    public function prefix_uses_two_digit_year_across_year_change(): void
    {
        Carbon::setTestNow('2026-12-15 10:00:00');
        $this->assertSame('1226-001', $this->makeBatch()->customer_batch_number);

        Carbon::setTestNow('2027-01-05 10:00:00');
        $this->assertSame('0127-001', $this->makeBatch()->customer_batch_number);
    }

    /** @test */
    public function explicit_batch_number_is_respected(): void
    {
        Carbon::setTestNow('2026-08-04 10:00:00');

        $batch = $this->makeBatch(['customer_batch_number' => 'MANUAL-999']);

        $this->assertSame('MANUAL-999', $batch->customer_batch_number);
    }

    private function makeBatch(array $overrides = []): LabelBatch
    {
        return LabelBatch::create(array_merge([
            'product_id'           => $this->product->id,
            'quantity'             => 1,
            'generated_by_user_id' => $this->user->id,
        ], $overrides));
    }
}
