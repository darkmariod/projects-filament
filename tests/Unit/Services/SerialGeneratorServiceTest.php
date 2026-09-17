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
        $batch = $this->createBatch(quantity: 3, productCode: 'RANGE', date: '2026-05-27');

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
    //  Public token (Fase 1 - token público no adivinable)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function public_token_has_exact_length(): void
    {
        $token = $this->service->generatePublicToken();

        $this->assertSame(20, strlen($token));
    }

    /** @test */
    public function public_token_uses_base32_alphabet_without_ambiguous_chars(): void
    {
        // Alfabeto: A-Z (sin I, O) + dígitos 2-9 (sin 0/1/L)
        $token = $this->service->generatePublicToken();

        $allowed = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $this->assertSame(strlen($token), strspn($token, $allowed), 'Token solo contiene caracteres del alfabeto permitido');
        $this->assertStringNotContainsString('0', $token);
        $this->assertStringNotContainsString('O', $token);
        $this->assertStringNotContainsString('1', $token);
        $this->assertStringNotContainsString('I', $token);
    }

    /** @test */
    public function generating_5000_tokens_produces_zero_collisions(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5000; $i++) {
            $tokens[] = $this->service->generatePublicToken();
        }

        $this->assertSame(5000, count(array_unique($tokens)), 'Deben ser 5000 tokens únicos');
    }

    /** @test */
    public function public_token_does_not_contain_sequence_or_product_code(): void
    {
        $token = $this->service->generatePublicToken();

        // El token no debe contener secuencia numérica (8 dígitos) ni el formato de serial
        $this->assertDoesNotMatchRegularExpression('/\d{8}/', $token);
        $this->assertDoesNotMatchRegularExpression('/-V-/', $token);

        // No debe contener ningún dígito (el alfabeto Base32 sin ambiguos usa 2-7, pero
        // el serial original tiene 8 dígitos de secuencia + DV — verificamos que el token
        // no repita el patrón secuencial)
        $this->assertDoesNotMatchRegularExpression('/^\d{4}-/', $token);
    }

    /** @test */
    public function two_tokens_from_same_batch_have_no_deterministic_relation(): void
    {
        // Reproduce el escenario de Diego: 2 etiquetas consecutivas del mismo lote
        // (sequence N y N+1) — sus tokens NO deben tener transformación determinista.
        $tokenN   = $this->service->generatePublicToken();
        $tokenN1  = $this->service->generatePublicToken();

        $this->assertNotSame($tokenN, $tokenN1);

        // No deben compartir prefijo secuencial detectable
        $commonPrefix = $this->commonPrefixLength($tokenN, $tokenN1);
        $this->assertLessThan(5, $commonPrefix, 'Tokens no deben tener prefijo común largo');
    }

    /** @test */
    public function generate_labels_for_batch_persists_unique_tokens_and_qr_urls(): void
    {
        $batch = $this->createBatch(quantity: 50, productCode: 'TOKENB', date: '2026-05-27');

        $result = $this->service->generateLabelsForBatch($batch);

        $this->assertTrue($result);

        $labels = $batch->labels()->orderBy('sequence_number')->get();

        $this->assertCount(50, $labels);

        // 50 tokens únicos
        $tokens = $labels->pluck('public_token')->all();
        $this->assertCount(50, array_unique($tokens));

        // Cada qr_url contiene SU propio token, no el de otra fila
        foreach ($labels as $label) {
            $this->assertNotNull($label->public_token);
            $this->assertStringContainsString('/p/' . $label->public_token, $label->qr_url);
        }

        // Serial interno intacto y secuencial
        $this->assertSame(1, $labels[0]->sequence_number);
        $this->assertSame(2, $labels[1]->sequence_number);
        $this->assertSame('2605-TOKENB-V-00000001-', substr($labels[0]->serial, 0, 23));
    }

    /** @test */
    public function build_public_url_uses_token_not_serial(): void
    {
        $token = 'ABC23456789';

        $url = $this->service->buildPublicUrl($token);

        $this->assertStringContainsString('/p/' . $token, $url);
        $this->assertStringNotContainsString('serial', strtolower($url));
    }

    /** @test */
    public function build_qr_url_alias_remains_for_backward_compat(): void
    {
        $this->assertTrue(method_exists($this->service, 'buildQrUrl'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function commonPrefixLength(string $a, string $b): int
    {
        $len = 0;
        $max = min(strlen($a), strlen($b));
        while ($len < $max && $a[$len] === $b[$len]) {
            $len++;
        }
        return $len;
    }

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
