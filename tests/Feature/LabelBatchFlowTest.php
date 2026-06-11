<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LabelBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelBatchFlowTest extends TestCase
{
    use RefreshDatabase;

    private SerialGeneratorService $serialService;
    private ZebraZplService $zplService;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serialService = new SerialGeneratorService();
        $this->zplService = new ZebraZplService();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function admin_can_create_batch_and_generate_labels(): void
    {
        $batch = LabelBatch::create([
            'product_id' => $this->product->id,
            'internal_batch_code' => 'LOTE-FLOW-001',
            'customer_batch_number' => 'CBN-FLOW-001',
            'customer_batch_date' => '2026-05-27',
            'quantity' => 5,
            'operator' => 'Admin User',
            'generated_by_user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $result = $this->serialService->generateLabelsForBatch($batch);

        $this->assertTrue($result);
        $this->assertCount(5, $batch->fresh()->labels);
    }

    /** @test */
    public function serials_are_generated_in_correct_format(): void
    {
        $batch = LabelBatch::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $this->serialService->generateLabelsForBatch($batch);

        $labels = $batch->fresh()->labels()->orderBy('sequence_number')->get();

        foreach ($labels as $label) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-[A-Z0-9-]+-V-\d{8}-\d$/',
                $label->serial
            );
        }
    }

    /** @test */
    public function batch_status_transitions_from_active_to_generated(): void
    {
        $batch = LabelBatch::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $this->assertSame('active', $batch->status);

        $this->serialService->generateLabelsForBatch($batch);
        $batch->refresh();

        $this->assertSame('generated', $batch->status);
        $this->assertNotNull($batch->generated_at);
    }

    /** @test */
    public function duplicate_generation_is_prevented(): void
    {
        $batch = LabelBatch::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $first = $this->serialService->generateLabelsForBatch($batch);
        $this->assertTrue($first);

        $second = $this->serialService->generateLabelsForBatch($batch);
        $this->assertFalse($second);

        $this->assertCount(3, $batch->fresh()->labels);
    }

    /** @test */
    public function batch_with_labels_generates_zpl_output(): void
    {
        $batch = LabelBatch::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $this->serialService->generateLabelsForBatch($batch);
        $batch->refresh();

        $this->assertCount(3, $batch->labels);

        $zpl = $this->zplService->generateForBatch($batch);

        $this->assertStringContainsString('^XA', $zpl);
        $this->assertStringContainsString('^XZ', $zpl);
        $this->assertSame(3, mb_substr_count($zpl, '^XA'));
        $this->assertSame(3, mb_substr_count($zpl, '^XZ'));
    }

    /** @test */
    public function batch_sets_generated_status_after_generation(): void
    {
        $batch = LabelBatch::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $this->serialService->generateLabelsForBatch($batch);
        $batch->refresh();

        $this->assertSame('generated', $batch->status);
        $this->assertNotNull($batch->generated_at);
        $this->assertCount(3, $batch->labels);
        $this->assertStringContainsString('00000001', $batch->labels[0]->serial);
        $this->assertStringContainsString('00000003', $batch->labels[2]->serial);
    }
}
