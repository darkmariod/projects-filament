<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyCertificateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function certificate_page_shows_for_registered_warranty(): void
    {
        $warranty = Warranty::factory()->create();
        $label = $warranty->label;

        $response = $this->get("/garantia/{$label->serial}/certificado");

        $response->assertStatus(200);
        $response->assertSee($label->serial);
    }

    /** @test */
    public function no_warranty_redirects_to_product_page(): void
    {
        $label = Label::factory()->create([
            'serial' => '2605-NOWRNT-V-00000001-3',
            'status' => 'available',
        ]);

        $response = $this->get("/garantia/{$label->serial}/certificado");

        $response->assertRedirect("/p/{$label->serial}");
    }

    /** @test */
    public function invalid_serial_returns_four_oh_four(): void
    {
        $response = $this->get('/garantia/NONEXISTENT-99999/certificado');

        $response->assertStatus(404);
    }

    /** @test */
    public function certificate_download_returns_pdf(): void
    {
        $warranty = Warranty::factory()->create();
        $label = $warranty->label;

        $response = $this->get("/garantia/{$label->serial}/certificado?download=1");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'certificado-garantia-' . $label->serial . '.pdf',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    /** @test */
    public function certificate_shows_customer_and_product_info(): void
    {
        $warranty = Warranty::factory()->create();
        $label = $warranty->label;
        $customer = $warranty->customer;

        $response = $this->get("/garantia/{$label->serial}/certificado");

        $response->assertStatus(200);
        $response->assertSee($customer->first_name);
        $response->assertSee($label->product->name);
    }

    /** @test */
    public function warranty_confirmation_page_shows_download_button(): void
    {
        $warranty = Warranty::factory()->create();
        $label = $warranty->label;

        $response = $this->get("/garantia/{$label->serial}/certificado");

        $response->assertStatus(200);
        $response->assertSee('download');
    }
}
