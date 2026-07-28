<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private ProductModel $productModel;
    private Category $category;
    private LabelBatch $batch;
    private Label $label;
    private Customer $customer;
    private Warranty $warranty;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Colchones',
            'code'  => 'COL',
        ]);

        $this->productModel = ProductModel::create([
            'category_id'    => $this->category->id,
            'name'           => 'CR SE ESP',
            'code'           => 'CRSEESP',
            'type'           => 'Colchón',
            'class'          => 'Especial',
            'warranty_years' => 5,
            'active'         => true,
        ]);

        $this->product = Product::create([
            'product_model_id' => $this->productModel->id,
            'name'             => 'CR SE ESP 150x190',
            'product_code'     => 'A50135',
            'barcode'          => 'BC-A50135',
            'width_cm'         => 150,
            'length_cm'        => 190,
            'height_cm'        => 30,
            'measurements_text' => '150x190x30',
            'active'           => true,
        ]);

        $this->product->technicalComposition()->create([
            'commercial_name'           => 'Colchón CR SE ESP',
            'manufacturer'              => 'Paraíso del Ecuador',
            'manufacturer_ruc'          => '1790098230001',
            'inen_standard'             => 'NTE INEN 2035',
        ]);

        $this->user = User::factory()->create();

        $this->batch = LabelBatch::create([
            'product_id'            => $this->product->id,
            'internal_batch_code'   => 'LOTE-001',
            'customer_batch_number' => 'CBN-001',
            'customer_batch_date'   => '2026-05-01',
            'quantity'              => 1,
            'generated_by_user_id'  => $this->user->id,
            'status'                => 'generated',
        ]);

        $this->label = Label::create([
            'label_batch_id'  => $this->batch->id,
            'product_id'      => $this->product->id,
            'serial'          => '2605-A50135-V-00000001-3',
            'sequence_number' => 1,
            'barcode'         => 'BC-001',
            'qr_url'          => 'https://test.test/p/2605-A50135-V-00000001-3',
            'status'          => 'available',
        ]);

        $this->customer = Customer::create([
            'first_name'       => 'Juan',
            'second_name'      => 'Carlos',
            'last_name'        => 'Pérez',
            'second_last_name' => 'García',
            'document_type'    => 'cedula',
            'document_number'  => '1712345678',
            'email'            => 'juan@example.com',
            'phone'            => '0999123456',
            'address'          => 'Av. Test',
            'province'         => 'Pichincha',
            'city'             => 'Quito',
        ]);

        $this->warranty = Warranty::create([
            'label_id'            => $this->label->id,
            'customer_id'         => $this->customer->id,
            'store_name'          => 'Paraíso Centro',
            'invoice_number'      => 'FAC-001',
            'purchase_date'       => '2026-05-15',
            'warranty_start_date' => '2026-05-15',
            'warranty_end_date'   => '2031-05-15',
            'status'              => 'active',
            'terms_accepted'      => true,
        ]);
    }

    // ── Category ─────────────────────────────────────────────────────

    /** @test */
    public function category_has_many_product_models(): void
    {
        $models = $this->category->productModels;

        $this->assertCount(1, $models);
        $this->assertTrue($models->first()->is($this->productModel));
    }

    // ── ProductModel ─────────────────────────────────────────────────

    /** @test */
    public function product_model_belongs_to_category(): void
    {
        $this->assertTrue($this->productModel->category->is($this->category));
    }

    /** @test */
    public function product_model_has_many_products(): void
    {
        $products = $this->productModel->products;

        $this->assertCount(1, $products);
        $this->assertTrue($products->first()->is($this->product));
    }

    // ── Product ──────────────────────────────────────────────────────

    /** @test */
    public function product_belongs_to_product_model(): void
    {
        $this->assertTrue($this->product->productModel->is($this->productModel));
    }

    /** @test */
    public function product_has_one_technical_composition(): void
    {
        $this->assertNotNull($this->product->technicalComposition);
        $this->assertInstanceOf(TechnicalComposition::class, $this->product->technicalComposition);
    }

    /** @test */
    public function product_has_many_label_batches(): void
    {
        $batches = $this->product->labelBatches;

        $this->assertCount(1, $batches);
        $this->assertTrue($batches->first()->is($this->batch));
    }

    // ── TechnicalComposition ─────────────────────────────────────────

    /** @test */
    public function technical_composition_belongs_to_product(): void
    {
        $composition = $this->product->technicalComposition;

        $this->assertTrue($composition->product->is($this->product));
    }

    // ── LabelBatch ───────────────────────────────────────────────────

    /** @test */
    public function label_batch_belongs_to_product(): void
    {
        $this->assertTrue($this->batch->product->is($this->product));
    }

    /** @test */
    public function label_batch_has_many_labels(): void
    {
        $labels = $this->batch->labels;

        $this->assertCount(1, $labels);
        $this->assertTrue($labels->first()->is($this->label));
    }

    /** @test */
    public function label_batch_belongs_to_generated_by_user(): void
    {
        $this->assertTrue($this->batch->generatedBy->is($this->user));
    }

    // ── Label ────────────────────────────────────────────────────────

    /** @test */
    public function label_belongs_to_label_batch(): void
    {
        $this->assertTrue($this->label->labelBatch->is($this->batch));
    }

    /** @test */
    public function label_belongs_to_product(): void
    {
        $this->assertTrue($this->label->product->is($this->product));
    }

    /** @test */
    public function label_has_one_warranty(): void
    {
        $this->assertTrue($this->label->warranty->is($this->warranty));
    }

    // ── Warranty ─────────────────────────────────────────────────────

    /** @test */
    public function warranty_belongs_to_label(): void
    {
        $this->assertTrue($this->warranty->label->is($this->label));
    }

    /** @test */
    public function warranty_belongs_to_customer(): void
    {
        $this->assertTrue($this->warranty->customer->is($this->customer));
    }

    // ── Customer ─────────────────────────────────────────────────────

    /** @test */
    public function customer_has_many_warranties(): void
    {
        $warranties = $this->customer->warranties;

        $this->assertCount(1, $warranties);
        $this->assertTrue($warranties->first()->is($this->warranty));
    }

    /** @test */
    public function customer_full_name_accessor(): void
    {
        $name = $this->customer->full_name;

        $this->assertSame('Juan Carlos Pérez García', $name);
    }

    /** @test */
    public function customer_full_name_without_optional_names(): void
    {
        $customer = Customer::create([
            'first_name'      => 'María',
            'last_name'       => 'López',
            'document_type'   => 'cedula',
            'document_number' => '1712345679',
            'email'           => 'maria@example.com',
            'phone'           => '0999123457',
            'address'         => 'Test',
            'province'        => 'Pichincha',
            'city'            => 'Quito',
        ]);

        // Accessor joins all name parts with spaces (double space expected when middle names are null)
        $this->assertStringContainsString('María', $customer->full_name);
        $this->assertStringContainsString('López', $customer->full_name);
    }

    // ── Status Constants ─────────────────────────────────────────────

    /** @test */
    public function label_factory_creates_anulled_label(): void
    {
        $label = Label::factory()->anulled()->create();

        $this->assertSame('anulled', $label->status);
    }

    /** @test */
    public function label_factory_creates_registered_label(): void
    {
        $label = Label::factory()->registered()->create();

        $this->assertSame('registered', $label->status);
    }

    /** @test */
    public function warranty_factory_creates_anulled_warranty(): void
    {
        $warranty = Warranty::factory()->anulled()->create();

        $this->assertSame('anulled', $warranty->status);
    }

    /** @test */
    public function warranty_factory_creates_expired_warranty(): void
    {
        $warranty = Warranty::factory()->expired()->create();

        $this->assertSame('expired', $warranty->status);
    }

    /** @test */
    public function label_batch_factory_creates_generated_batch(): void
    {
        $batch = LabelBatch::factory()->generated()->create();

        $this->assertSame('generated', $batch->status);
        $this->assertNotNull($batch->generated_at);
    }
}
