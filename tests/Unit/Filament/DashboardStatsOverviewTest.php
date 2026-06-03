<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Widgets\DashboardStatsOverview;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductModel;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_four_stat_cards_with_labels(): void
    {
        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('Total Etiquetas')
            ->assertSee('Garantías Activas')
            ->assertSee('Lotes Este Mes')
            ->assertSee('Productos');
    }

    /** @test */
    public function it_shows_zero_when_database_is_empty(): void
    {
        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('Total Etiquetas')
            ->assertSee('0');
    }

    /** @test */
    public function it_shows_correct_label_count(): void
    {
        $batch = $this->createBatchWithLabels(3);

        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('3');
    }

    /** @test */
    public function it_shows_correct_active_warranty_count(): void
    {
        $product = $this->createProduct();
        $batch = $this->createBatchWithLabels(1, product: $product);
        $label = $batch->labels()->first();

        Warranty::create([
            'label_id' => $label->id,
            'customer_id' => $this->createCustomer()->id,
            'store_name' => 'Store',
            'invoice_number' => 'INV-001',
            'purchase_date' => now(),
            'warranty_start_date' => now(),
            'warranty_end_date' => now()->addYear(),
            'status' => 'active',
            'terms_accepted' => true,
        ]);

        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('1');
    }

    /** @test */
    public function it_excludes_demo_product_from_product_count(): void
    {
        // Create a non-demo product
        $this->createProduct(productCode: 'REAL01');

        // Create the demo product with the configured code
        $this->createProduct(productCode: config('dashboard.demo_product_code', 'A50135'));

        Livewire::test(DashboardStatsOverview::class)
            ->assertSee('1'); // Only REAL01 counted, not demo
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

    private function createCustomer(): \App\Models\Customer
    {
        return \App\Models\Customer::create([
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

    private function createBatch(
        ?Product $product = null,
        ?User $user = null,
        int $quantity = 1,
    ): LabelBatch {
        $product ??= $this->createProduct();
        $user ??= User::factory()->create();

        $batch = LabelBatch::create([
            'product_id' => $product->id,
            'internal_batch_code' => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date' => now()->format('Y-m-d'),
            'quantity' => $quantity,
            'generated_by_user_id' => $user->id,
            'status' => 'generated',
            'operator' => 'Test Operator',
        ]);

        return $batch;
    }

    private function createBatchWithLabels(int $labelCount, ?Product $product = null): LabelBatch
    {
        $batch = $this->createBatch(product: $product, quantity: $labelCount);

        for ($i = 1; $i <= $labelCount; $i++) {
            Label::create([
                'label_batch_id' => $batch->id,
                'product_id' => $batch->product_id,
                'serial' => 'SN-BATCH-' . $i . '-' . substr(uniqid(), -4),
                'sequence_number' => $i,
                'barcode' => 'BC-BATCH-' . $i,
                'qr_url' => 'https://test.test/qr/' . uniqid(),
                'status' => 'available',
            ]);
        }

        return $batch;
    }
}
