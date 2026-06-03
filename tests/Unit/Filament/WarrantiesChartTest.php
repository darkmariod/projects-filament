<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Widgets\WarrantiesChart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class WarrantiesChartTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_is_a_line_chart(): void
    {
        $widget = new WarrantiesChart();
        $method = new ReflectionMethod($widget, 'getType');

        $this->assertSame('line', $method->invoke($widget));
    }

    /** @test */
    public function it_has_a_heading(): void
    {
        Livewire::test(WarrantiesChart::class)
            ->assertSee('Garantías (30 días)');
    }

    /** @test */
    public function it_returns_data_with_labels_and_datasets_structure(): void
    {
        $widget = new WarrantiesChart();
        $method = new ReflectionMethod($widget, 'getData');

        $data = $method->invoke($widget);

        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(30, $data['labels']);
    }

    /** @test */
    public function it_shows_zero_for_all_days_when_no_warranties_exist(): void
    {
        $widget = new WarrantiesChart();
        $method = new ReflectionMethod($widget, 'getData');

        $data = $method->invoke($widget);

        $this->assertCount(1, $data['datasets']);
        $this->assertCount(30, $data['datasets'][0]['data']);
        $this->assertSame(array_fill(0, 30, 0), $data['datasets'][0]['data']);
    }

    /** @test */
    public function it_counts_warranties_per_day_for_last_30_days(): void
    {
        $product = $this->createProduct();
        $batch = $this->createBatchWithLabels(3, product: $product);
        $customer = $this->createCustomer();

        // Create 2 warranties today
        foreach ($batch->labels as $i => $label) {
            if ($i >= 2) {
                break;
            }
            $warranty = Warranty::create([
                'label_id' => $label->id,
                'customer_id' => $customer->id,
                'store_name' => 'Store',
                'invoice_number' => 'INV-' . $i,
                'purchase_date' => now(),
                'warranty_start_date' => now(),
                'warranty_end_date' => now()->addYear(),
                'status' => 'active',
                'terms_accepted' => true,
            ]);
            $warranty->created_at = now();
            $warranty->save();
        }

        $widget = new WarrantiesChart();
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
        $product = $this->createProduct();
        $customer = $this->createCustomer();

        // Create one warranty 15 days ago — use explicit date matching chart's range
        $pastDate = now()->subDays(15);
        $label = $this->createLabel(product: $product);
        $warranty = Warranty::create([
            'label_id' => $label->id,
            'customer_id' => $customer->id,
            'store_name' => 'Store',
            'invoice_number' => 'INV-PAST',
            'purchase_date' => $pastDate,
            'warranty_start_date' => $pastDate,
            'warranty_end_date' => $pastDate->copy()->addYear(),
            'status' => 'active',
            'terms_accepted' => true,
        ]);
        // Set created_at manually since it's not in $fillable
        $warranty->created_at = $pastDate;
        $warranty->save();

        $widget = new WarrantiesChart();
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

    private function createProduct(string $productCode = 'TP-TEST'): Product
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

        return Product::create([
            'product_model_id' => $productModel->id,
            'name' => 'Test Product',
            'product_code' => $productCode,
            'barcode' => 'BC-' . $productCode,
            'active' => true,
        ]);
    }

    private function createLabel(?Product $product = null): Label
    {
        $product ??= $this->createProduct();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id' => $product->id,
            'internal_batch_code' => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date' => now()->format('Y-m-d'),
            'quantity' => 1,
            'generated_by_user_id' => $user->id,
            'status' => 'generated',
            'operator' => 'Test Operator',
        ]);

        return Label::create([
            'label_batch_id' => $batch->id,
            'product_id' => $product->id,
            'serial' => 'SN-' . substr(uniqid(), -8),
            'sequence_number' => 1,
            'barcode' => 'BC-TEST',
            'qr_url' => 'https://test.test/qr/' . uniqid(),
            'status' => 'available',
        ]);
    }

    private function createBatchWithLabels(int $labelCount, ?Product $product = null): LabelBatch
    {
        $product ??= $this->createProduct();
        $user = User::factory()->create();

        $batch = LabelBatch::create([
            'product_id' => $product->id,
            'internal_batch_code' => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date' => now()->format('Y-m-d'),
            'quantity' => $labelCount,
            'generated_by_user_id' => $user->id,
            'status' => 'generated',
            'operator' => 'Test Operator',
        ]);

        for ($i = 1; $i <= $labelCount; $i++) {
            Label::create([
                'label_batch_id' => $batch->id,
                'product_id' => $product->id,
                'serial' => 'SN-BATCH-' . $i . '-' . substr(uniqid(), -4),
                'sequence_number' => $i,
                'barcode' => 'BC-BATCH-' . $i,
                'qr_url' => 'https://test.test/qr/' . uniqid(),
                'status' => 'available',
            ]);
        }

        return $batch;
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'document_type' => 'C',
            'document_number' => '1234567890',
            'email' => 'test-' . substr(uniqid(), -6) . '@example.com',
            'phone' => '0999999999',
            'address' => 'Test Address',
            'province' => 'Pichincha',
            'city' => 'Quito',
        ]);
    }
}
