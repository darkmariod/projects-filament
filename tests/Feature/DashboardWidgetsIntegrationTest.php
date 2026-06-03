<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\BatchesChart;
use App\Filament\Widgets\DashboardStatsOverview;
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

class DashboardWidgetsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Product $demoProduct;
    private Product $realProduct;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Cat', 'code' => 'C-' . substr(uniqid(), -6)]);
        $model = ProductModel::create([
            'category_id' => $category->id,
            'name' => 'Model',
            'code' => 'M-' . substr(uniqid(), -6),
            'warranty_years' => 1,
            'active' => true,
        ]);

        // Demo product (should be excluded from count)
        $this->demoProduct = Product::create([
            'product_model_id' => $model->id,
            'name' => 'Demo Product',
            'product_code' => config('dashboard.demo_product_code'),
            'barcode' => 'BC-DEMO',
            'active' => true,
        ]);

        // Real product (should be counted)
        $this->realProduct = Product::create([
            'product_model_id' => $model->id,
            'name' => 'Real Product',
            'product_code' => 'REAL-001',
            'barcode' => 'BC-REAL',
            'active' => true,
        ]);

        $this->customer = Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'document_type' => 'C',
            'document_number' => '0999999999',
            'email' => 'john-' . substr(uniqid(), -6) . '@example.com',
            'phone' => '0999999999',
            'address' => 'Test',
            'province' => 'Pichincha',
            'city' => 'Quito',
        ]);
    }

    /** @test */
    public function stats_overview_shows_correct_counts_with_seeded_data(): void
    {
        $user = User::factory()->create();

        // Create 3 labels in 1 batch
        $batch = $this->createBatch($this->realProduct, $user, 3, now()->subDays(5));

        // Create 2 active warranties
        foreach ($batch->labels->take(2) as $i => $label) {
            $warranty = Warranty::create([
                'label_id' => $label->id,
                'customer_id' => $this->customer->id,
                'store_name' => 'Store',
                'invoice_number' => 'INV-' . $i,
                'purchase_date' => now()->subDays(5),
                'warranty_start_date' => now()->subDays(5),
                'warranty_end_date' => now()->subDays(5)->addYear(),
                'status' => 'active',
                'terms_accepted' => true,
            ]);
            $warranty->created_at = now()->subDays(5);
            $warranty->save();
        }

        // Another batch this month
        $this->createBatch($this->realProduct, $user, 1, now());

        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('Total Etiquetas')
            ->assertSee('4') // 3 + 1 labels
            ->assertSee('Garantías Activas')
            ->assertSee('2') // 2 active warranties
            ->assertSee('Lotes Este Mes')
            ->assertSee('2') // 2 batches this month
            ->assertSee('Productos')
            ->assertSee('1'); // 1 real product (demo excluded)
    }

    /** @test */
    public function warranties_chart_includes_warranties_from_last_30_days(): void
    {
        $user = User::factory()->create();

        // Create warranties across different days
        $dates = [now()->subDays(2), now()->subDays(2), now()->subDays(15)];

        foreach ($dates as $i => $date) {
            $batch = $this->createBatch($this->realProduct, $user, 1);
            $label = $batch->labels->first();

            $warranty = Warranty::create([
                'label_id' => $label->id,
                'customer_id' => $this->customer->id,
                'store_name' => 'Store',
                'invoice_number' => 'INV-I-' . $i,
                'purchase_date' => $date,
                'warranty_start_date' => $date,
                'warranty_end_date' => $date->copy()->addYear(),
                'status' => 'active',
                'terms_accepted' => true,
            ]);
            $warranty->created_at = $date;
            $warranty->save();
        }

        $widget = new WarrantiesChart();
        $method = new ReflectionMethod($widget, 'getData');
        $data = $method->invoke($widget);

        // Should have 30 labels
        $this->assertCount(30, $data['labels']);

        // 2 days ago should have 2
        $day2agoLabel = now()->subDays(2)->format('Y-m-d');
        $day2agoIndex = array_search($day2agoLabel, $data['labels']);
        $this->assertNotFalse($day2agoIndex);
        $this->assertSame(2, $data['datasets'][0]['data'][$day2agoIndex]);

        // 15 days ago should have 1
        $day15agoLabel = now()->subDays(15)->format('Y-m-d');
        $day15agoIndex = array_search($day15agoLabel, $data['labels']);
        $this->assertNotFalse($day15agoIndex);
        $this->assertSame(1, $data['datasets'][0]['data'][$day15agoIndex]);

        // A day with no data should be 0
        $emptyDayLabel = now()->subDays(10)->format('Y-m-d');
        $emptyDayIndex = array_search($emptyDayLabel, $data['labels']);
        $this->assertNotFalse($emptyDayIndex);
        $this->assertSame(0, $data['datasets'][0]['data'][$emptyDayIndex]);
    }

    /** @test */
    public function batches_chart_includes_batches_from_last_30_days(): void
    {
        $user = User::factory()->create();

        // Create batches across different days
        $today = now();
        $pastDate1 = now()->subDays(3);
        $pastDate2 = now()->subDays(20);

        $batch1 = $this->createBatch($this->realProduct, $user, 1, $today);
        $batch2 = $this->createBatch($this->realProduct, $user, 1, $pastDate1);
        $batch3 = $this->createBatch($this->realProduct, $user, 1, $pastDate2);

        // Set created_at to specific dates (not in $fillable)
        foreach ([$batch1, $batch2, $batch3] as $i => $batch) {
            $dates = [$today, $pastDate1, $pastDate2];
            $batch->created_at = $dates[$i];
            $batch->save();
        }

        $widget = new BatchesChart();
        $method = new ReflectionMethod($widget, 'getData');
        $data = $method->invoke($widget);

        $this->assertCount(30, $data['labels']);

        // Today should have 1
        $todayLabel = $today->format('Y-m-d');
        $todayIndex = array_search($todayLabel, $data['labels']);
        $this->assertNotFalse($todayIndex);
        $this->assertSame(1, $data['datasets'][0]['data'][$todayIndex]);

        // 3 days ago should have 1
        $day3Label = $pastDate1->format('Y-m-d');
        $day3Index = array_search($day3Label, $data['labels']);
        $this->assertNotFalse($day3Index);
        $this->assertSame(1, $data['datasets'][0]['data'][$day3Index]);

        // 20 days ago should have 1
        $day20Label = $pastDate2->format('Y-m-d');
        $day20Index = array_search($day20Label, $data['labels']);
        $this->assertNotFalse($day20Index);
        $this->assertSame(1, $data['datasets'][0]['data'][$day20Index]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createBatch(Product $product, User $user, int $quantity, $date = null): LabelBatch
    {
        $date = $date ?? now();

        $batch = LabelBatch::create([
            'product_id' => $product->id,
            'internal_batch_code' => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date' => $date->format('Y-m-d'),
            'quantity' => $quantity,
            'generated_by_user_id' => $user->id,
            'status' => 'generated',
            'operator' => 'Operator',
        ]);

        for ($i = 1; $i <= $quantity; $i++) {
            Label::create([
                'label_batch_id' => $batch->id,
                'product_id' => $product->id,
                'serial' => 'SN-' . substr(uniqid(), -8),
                'sequence_number' => $i,
                'barcode' => 'BC-' . $i,
                'qr_url' => 'https://test.test/qr/' . uniqid(),
                'status' => 'available',
            ]);
        }

        return $batch;
    }
}
