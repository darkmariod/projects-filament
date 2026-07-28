<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Widgets\BatchesChart;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class BatchesChartTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_is_a_bar_chart(): void
    {
        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getType');

        $this->assertSame('bar', $method->invoke($widget));
    }

    /** @test */
    public function it_has_a_heading(): void
    {
        Livewire::test(BatchesChart::class)
            ->assertSee('Lotes (30 días)');
    }

    /** @test */
    public function it_returns_data_with_labels_and_datasets_structure(): void
    {
        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getData');

        $data = $method->invoke($widget);

        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(30, $data['labels']);
    }

    /** @test */
    public function it_shows_zero_for_all_days_when_no_batches_exist(): void
    {
        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getData');

        $data = $method->invoke($widget);

        $this->assertCount(1, $data['datasets']);
        $this->assertCount(30, $data['datasets'][0]['data']);
        $this->assertSame(array_fill(0, 30, 0), $data['datasets'][0]['data']);
    }

    /** @test */
    public function it_counts_batches_per_day_for_last_30_days(): void
    {
        // Create 2 batches today
        $this->createBatch();
        $this->createBatch();

        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getData');
        $data = $method->invoke($widget);

        // Today should have count 2
        $todayLabel = now()->format('Y-m-d');
        $todayIndex = array_search($todayLabel, $data['labels']);
        $this->assertNotFalse($todayIndex, 'Today not found in labels');
        $this->assertSame(2, $data['datasets'][0]['data'][$todayIndex]);
    }

    /** @test */
    public function it_fills_sparse_days_with_zero(): void
    {
        // Create one batch 15 days ago
        $pastDate = now()->subDays(15);
        $batch = $this->createBatch();
        $batch->created_at = $pastDate;
        $batch->save();

        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getData');
        $data = $method->invoke($widget);

        // Day 15 days ago should have count 1
        $targetLabel = $pastDate->format('Y-m-d');
        $targetIndex = array_search($targetLabel, $data['labels']);
        $this->assertNotFalse($targetIndex, "Target date '{$targetLabel}' not found in labels");
        $this->assertSame(1, $data['datasets'][0]['data'][$targetIndex]);

        // A day with no data should be 0
        $otherDate = now()->subDays(10);
        $otherLabel = $otherDate->format('Y-m-d');
        $otherIndex = array_search($otherLabel, $data['labels']);
        $this->assertNotFalse($otherIndex);
        $this->assertSame(0, $data['datasets'][0]['data'][$otherIndex]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createProduct(?string $productCode = null): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
            'code' => 'TC-' . substr(uniqid(), -6),
        ]);

        $productModel = ProductModel::create([
            'category_id' => $category->id,
            'name' => 'Test Model',
            'code' => 'TM-' . substr(uniqid(), -6),
            'warranty_years' => 1,
            'active' => true,
        ]);

        $code = $productCode ?? ('TP-' . substr(uniqid(), -8));

        return Product::create([
            'product_model_id' => $productModel->id,
            'name' => 'Test Product',
            'product_code' => $code,
            'barcode' => 'BC-' . $code,
            'active' => true,
        ]);
    }

    private function createBatch(): LabelBatch
    {
        $product = $this->createProduct();
        $user = User::factory()->create();

        return LabelBatch::create([
            'product_id' => $product->id,
            'internal_batch_code' => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date' => now()->format('Y-m-d'),
            'quantity' => 1,
            'generated_by_user_id' => $user->id,
            'status' => 'generated',
            'operator' => 'Test Operator',
        ]);
    }
}
