<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\User;
use App\Services\SerialGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerialGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private SerialGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SerialGeneratorService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Serial format
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_generates_serials_in_correct_format(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'A50135', date: '2026-05-27');

        $serials = $this->service->generateForBatch($batch);

        $this->assertCount(1, $serials);
        // Format: YYMM-PRODUCTCODE-V-SEQUENCE-DV
        $this->assertMatchesRegularExpression(
            '/^\d{4}-[A-Z0-9]+-V-\d{8}-\d$/',
            $serials[0]['serial']
        );
    }

    /** @test */
    public function it_generates_sequential_serials_for_batch(): void
    {
        $batch = $this->createBatch(quantity: 3, productCode: 'A50135', date: '2026-05-27');

        $serials = $this->service->generateForBatch($batch);

        $this->assertCount(3, $serials);

        // Verify sequential serials: same prefix, different sequence
        $prefix = '2605-A50135-V-';
        $this->assertStringStartsWith($prefix, $serials[0]['serial']);
        $this->assertStringStartsWith($prefix, $serials[1]['serial']);
        $this->assertStringStartsWith($prefix, $serials[2]['serial']);

        // Sequences should be 00000001, 00000002, 00000003
        $this->assertSame(1, $serials[0]['sequence_number']);
        $this->assertSame(2, $serials[1]['sequence_number']);
        $this->assertSame(3, $serials[2]['sequence_number']);
    }

    /** @test */
    public function it_uses_date_from_batch_for_yymm_prefix(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'B001', date: '2026-01-15');

        $serials = $this->service->generateForBatch($batch);

        // January 2026 → "2601"
        $this->assertStringStartsWith('2601-', $serials[0]['serial']);
    }

    /** @test */
    public function it_uppercases_product_code(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'abc-123', date: '2026-05-27');

        $serials = $this->service->generateForBatch($batch);

        $this->assertStringStartsWith('2605-ABC-123-V-', $serials[0]['serial']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DV calculation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_appends_a_check_digit_to_each_serial(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'A50135', date: '2026-05-27');

        $serials = $this->service->generateForBatch($batch);

        // DV is the last character after the last dash, should be a digit
        $parts = explode('-', $serials[0]['serial']);
        $dv = end($parts);

        $this->assertMatchesRegularExpression('/^\d$/', $dv);
    }

    /** @test */
    public function dv_is_consistent_for_same_input(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'A50135', date: '2026-05-27');

        $first  = $this->service->generateForBatch($batch);
        $second = $this->service->generateForBatch($batch);

        // Same batch, same serial → same DV
        $this->assertSame($first[0]['serial'], $second[0]['serial']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Uniqueness
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_generates_unique_serials(): void
    {
        $batch = $this->createBatch(quantity: 10, productCode: 'UNIQUE', date: '2026-05-27');

        $serials = $this->service->generateForBatch($batch);

        $allSerials = array_column($serials, 'serial');
        $uniqueSerials = array_unique($allSerials);

        $this->assertCount(10, $uniqueSerials);
    }

    /** @test */
    public function it_skips_existing_serials_when_generating(): void
    {
        $batch = $this->createBatch(quantity: 2, productCode: 'SKIP01', date: '2026-05-27');

        // Pre-create a label with serial that would be sequence 1
        Label::create([
            'label_batch_id'  => $batch->id,
            'product_id'      => $batch->product_id,
            'serial'          => '2605-SKIP01-V-00000001-5',
            'sequence_number' => 1,
            'barcode'         => 'BC',
            'qr_url'          => 'https://test.test/qr/x',
            'status'          => 'available',
        ]);

        $serials = $this->service->generateForBatch($batch);

        // Should skip 00000001 (it exists) and start from 00000002
        $this->assertCount(2, $serials);
        $this->assertSame(2, $serials[0]['sequence_number']);
        $this->assertSame(3, $serials[1]['sequence_number']);

        // Verify the serials are NOT the pre-created one
        $this->assertNotEquals('2605-SKIP01-V-00000001-5', $serials[0]['serial']);
        $this->assertNotEquals('2605-SKIP01-V-00000001-5', $serials[1]['serial']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  generateLabelsForBatch
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_creates_labels_for_batch(): void
    {
        $batch = $this->createBatch(quantity: 5, productCode: 'GEN01', date: '2026-05-27');

        $result = $this->service->generateLabelsForBatch($batch);

        $this->assertTrue($result);
        $this->assertCount(5, $batch->labels);
    }

    /** @test */
    public function it_creates_labels_with_correct_data(): void
    {
        $batch = $this->createBatch(quantity: 2, productCode: 'DATA01', date: '2026-05-27');

        $this->service->generateLabelsForBatch($batch);

        $labels = $batch->labels()->orderBy('sequence_number')->get();

        $this->assertSame(1, $labels[0]->sequence_number);
        $this->assertSame(2, $labels[1]->sequence_number);
        $this->assertSame('available', $labels[0]->status);
        $this->assertNotNull($labels[0]->qr_url);
        $this->assertStringContainsString('/p/', $labels[0]->qr_url);
    }

    /** @test */
    public function it_updates_batch_serial_from_and_to(): void
    {
        $batch = $this->createBatch(quantity: 3, productCode: 'RANGE', date: '2026-05-27');

        $this->service->generateLabelsForBatch($batch);
        $batch->refresh();

        $this->assertNotNull($batch->serial_from);
        $this->assertNotNull($batch->serial_to);
        $this->assertStringContainsString('00000001', $batch->serial_from);
        $this->assertStringContainsString('00000003', $batch->serial_to);
    }

    /** @test */
    public function it_does_not_duplicate_labels(): void
    {
        $batch = $this->createBatch(quantity: 3, productCode: 'NODUP', date: '2026-05-27');

        // First call — should create
        $first = $this->service->generateLabelsForBatch($batch);
        $this->assertTrue($first);

        // Second call — should NOT duplicate
        $second = $this->service->generateLabelsForBatch($batch);
        $this->assertFalse($second);

        // Still only 3 labels
        $this->assertCount(3, $batch->labels()->get());
    }

    /** @test */
    public function it_sets_generated_at_and_status_on_batch(): void
    {
        $batch = $this->createBatch(quantity: 1, productCode: 'STATUS', date: '2026-05-27');

        $this->service->generateLabelsForBatch($batch);
        $batch->refresh();

        $this->assertNotNull($batch->generated_at);
        $this->assertSame('generated', $batch->status);
    }

    /** @test */
    public function build_qr_url_returns_correct_format(): void
    {
        $url = $this->service->buildQrUrl('2605-TEST-V-00000001-3');

        $this->assertStringContainsString('/p/2605-TEST-V-00000001-3', $url);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createBatch(
        int $quantity = 1,
        string $productCode = 'A50135',
        string $date = '2026-05-27',
    ): LabelBatch {
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

        $product = Product::create([
            'product_model_id' => $productModel->id,
            'name'             => 'CR SE ESP',
            'product_code'     => $productCode,
            'barcode'          => 'BC-' . $productCode,
            'active'           => true,
        ]);

        $user = User::factory()->create();

        return LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'LOTE-' . substr(uniqid(), -6),
            'customer_batch_number' => 'CBN-' . substr(uniqid(), -6),
            'customer_batch_date'   => $date,
            'quantity'              => $quantity,
            'generated_by_user_id'  => $user->id,
            'status'                => 'generated',
            'operator'              => 'Test Operator',
        ]);
    }
}
