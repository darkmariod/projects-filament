<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Product;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductPageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function visiting_product_page_shows_product_details(): void
    {
        $label = Label::factory()->create([
            'serial' => '2605-TEST-V-00000001-3',
            'status' => 'available',
        ]);

        $response = $this->get("/p/{$label->serial}");

        $response->assertStatus(200);
        $response->assertSee($label->serial);
        $response->assertSee($label->product->name);
        $response->assertSee($label->product->productModel->name);
        $response->assertSee('Registrar garantía');
    }

    /** @test */
    public function invalid_serial_returns_four_oh_four(): void
    {
        $response = $this->get('/p/NONEXISTENT-SERIAL-99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function cancelled_label_shows_cancelled_badge_on_product_page(): void
    {
        $label = Label::factory()->anulled()->create([
            'serial' => '2605-CNCL-V-00000001-1',
        ]);

        $response = $this->get("/p/{$label->serial}");

        $response->assertStatus(200);
        $response->assertSee('Etiqueta anulada');
        $response->assertDontSee('Registrar garantía');
    }

    /** @test */
    public function registered_label_shows_certificate_link(): void
    {
        $warranty = Warranty::factory()->create();

        $label = $warranty->label;

        $response = $this->get("/p/{$label->serial}");

        $response->assertStatus(200);
        $response->assertSee('Garantía registrada');
        $response->assertSee('Descargar certificado');
    }

    /** @test */
    public function product_page_shows_batch_information(): void
    {
        $label = Label::factory()->create();

        $response = $this->get("/p/{$label->serial}");

        $response->assertStatus(200);
        $response->assertSee($label->labelBatch->customer_batch_number);
    }

    /** @test */
    public function product_page_redirects_warranty_form_for_registered_label(): void
    {
        $warranty = Warranty::factory()->create();
        $label = $warranty->label;

        $response = $this->get("/garantia/{$label->serial}/registrar");

        $response->assertRedirect("/p/{$label->serial}");
        $response->assertSessionHas('error', 'Esta garantía ya fue registrada.');
    }

    /** @test */
    public function warranty_form_redirects_for_cancelled_label(): void
    {
        $label = Label::factory()->anulled()->create([
            'serial' => '2605-CNCL2-V-00000001-1',
        ]);

        $response = $this->get("/garantia/{$label->serial}/registrar");

        $response->assertRedirect("/p/{$label->serial}");
        $response->assertSessionHas('error', 'Esta etiqueta ha sido anulada.');
    }

    /** @test */
    public function warranty_form_for_available_label_shows_form(): void
    {
        $label = Label::factory()->create([
            'serial' => '2605-FORM-V-00000001-3',
            'status' => 'available',
        ]);

        $response = $this->get("/garantia/{$label->serial}/registrar");

        $response->assertStatus(200);
        $response->assertSee('REGISTRO DE GARANTÍA');
        $response->assertSee($label->product->name);
        $response->assertSee($label->serial);
    }
}
