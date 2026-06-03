<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Label;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Label $label;
    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label = Label::factory()->create([
            'serial' => '2605-WRNT-V-00000001-3',
            'status' => 'available',
        ]);

        // Ensure the product model has warranty years set
        $this->label->product->productModel->update(['warranty_years' => 2]);

        $this->validPayload = [
            'first_name' => 'Juan',
            'second_name' => 'Carlos',
            'last_name' => 'Pérez',
            'second_last_name' => 'García',
            'document_type' => 'cedula',
            'document_number' => '1712345678',
            'birth_date' => '1990-05-15',
            'gender' => 'masculino',
            'email' => 'juan.perez@example.com',
            'phone' => '0999123456',
            'address' => 'Av. Amazonas N10-200',
            'province' => 'Pichincha',
            'city' => 'Quito',
            'sector' => 'La Carolina',
            'store_name' => 'Almacén Paraíso Centro',
            'invoice_number' => 'FAC-001-123456',
            'purchase_date' => '2026-05-15',
            'terms_accepted' => '1',
        ];
    }

    /** @test */
    public function get_warranty_form_shows_registration_page(): void
    {
        $response = $this->get("/garantia/{$this->label->serial}/registrar");

        $response->assertStatus(200);
        $response->assertSee('REGISTRO DE GARANTÍA');
        $response->assertSee($this->label->product->name);
        $response->assertSee($this->label->serial);
    }

    /** @test */
    public function post_with_valid_data_creates_warranty(): void
    {
        $response = $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $response->assertRedirect("/garantia/{$this->label->serial}/certificado");

        $this->assertDatabaseHas('warranties', [
            'label_id' => $this->label->id,
            'store_name' => 'Almacén Paraíso Centro',
            'invoice_number' => 'FAC-001-123456',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('customers', [
            'document_number' => '1712345678',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);
    }

    /** @test */
    public function label_status_changes_to_registered_after_warranty(): void
    {
        $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $this->label->refresh();

        $this->assertSame('registered', $this->label->status);
        $this->assertNotNull($this->label->registered_at);
    }

    /** @test */
    public function duplicate_registration_is_prevented(): void
    {
        $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $response = $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $response->assertRedirect("/p/{$this->label->serial}");
        $response->assertSessionHas('error', 'Esta garantía ya fue registrada.');

        $this->assertDatabaseCount('warranties', 1);
    }

    /** @test */
    public function post_with_invalid_serial_returns_four_oh_four(): void
    {
        $response = $this->post(
            '/garantia/NONEXISTENT-99999/registrar',
            $this->validPayload
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function post_with_invalid_data_returns_validation_errors(): void
    {
        $response = $this->post(
            "/garantia/{$this->label->serial}/registrar",
            [
                'first_name' => '',
                'last_name' => '',
                'document_type' => 'invalido',
                'email' => 'not-an-email',
                'phone' => '',
                'terms_accepted' => '0',
            ]
        );

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'document_type',
            'email',
            'phone',
            'address',
            'province',
            'city',
            'store_name',
            'invoice_number',
            'purchase_date',
            'terms_accepted',
        ]);
    }

    /** @test */
    public function cancelled_label_cannot_register_warranty(): void
    {
        $cancelledLabel = Label::factory()->anulled()->create([
            'serial' => '2605-CNCL3-V-00000001-1',
        ]);

        $response = $this->get("/garantia/{$cancelledLabel->serial}/registrar");

        $response->assertRedirect("/p/{$cancelledLabel->serial}");
        $response->assertSessionHas('error', 'Esta etiqueta ha sido anulada.');
    }

    /** @test */
    public function warranty_calculates_end_date_based_on_product_model_warranty_years(): void
    {
        $this->label->product->productModel->update(['warranty_years' => 5]);

        $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $warranty = Warranty::where('label_id', $this->label->id)->first();

        $expectedEnd = \Carbon\Carbon::parse('2026-05-15')->addYears(5);

        $this->assertTrue(
            $warranty->warranty_end_date->eq($expectedEnd),
            "Expected {$expectedEnd->format('Y-m-d')}, got {$warranty->warranty_end_date->format('Y-m-d')}"
        );
    }

    /** @test */
    public function customer_is_reused_for_same_document_number(): void
    {
        $this->post(
            "/garantia/{$this->label->serial}/registrar",
            $this->validPayload
        );

        $secondLabel = Label::factory()->create([
            'serial' => '2605-WRNT2-V-00000001-3',
            'status' => 'available',
        ]);

        $payload = $this->validPayload;
        $payload['email'] = 'otro.email@example.com';
        $payload['phone'] = '0999888777';

        $this->post(
            "/garantia/{$secondLabel->serial}/registrar",
            $payload
        );

        $this->assertDatabaseCount('customers', 1);
    }
}
