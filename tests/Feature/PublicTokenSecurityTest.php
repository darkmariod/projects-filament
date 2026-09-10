<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Label;
use App\Services\SerialGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que pidió el cliente en la reunión del 3 de septiembre, con sus palabras:
 * que nadie que vea una etiqueta impresa pueda deducir cuál será la siguiente y
 * reclamar la garantía de un colchón que todavía no se fabricó.
 *
 * El serial sigue siendo correlativo porque producción lo necesita para
 * trazabilidad. Lo que viaja en el QR es otra cosa: un código aleatorio sin
 * relación con él. Estas pruebas fijan esa separación para que no se pierda.
 */
class PublicTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @test */
    public function the_public_code_reveals_nothing_about_the_next_one(): void
    {
        $service = app(SerialGeneratorService::class);

        $codigos = [];
        for ($i = 0; $i < 500; $i++) {
            $codigos[] = $service->generatePublicToken();
        }

        // Sin repetidos: dos colchones nunca comparten código.
        $this->assertCount(500, array_unique($codigos), 'No puede haber dos códigos iguales');

        // Sin orden: si estuvieran ordenados, verlos en secuencia daría el patrón.
        $ordenados = $codigos;
        sort($ordenados);
        $this->assertNotSame($ordenados, $codigos, 'Los códigos no deben salir en orden');

        // Sin prefijo común: un prefijo fijo recorta el espacio de búsqueda.
        $this->assertLessThan(
            3,
            strlen($this->prefijoComun($codigos)),
            'Los códigos no deben compartir un comienzo previsible'
        );
    }

    /** @test */
    public function the_public_code_uses_only_characters_nobody_confuses(): void
    {
        $codigo = app(SerialGeneratorService::class)->generatePublicToken();

        $this->assertSame(20, strlen($codigo), 'El código mide 20 caracteres');
        $this->assertMatchesRegularExpression(
            '/^[' . self::ALFABETO . ']+$/',
            $codigo,
            'Sin 0, O, 1, I ni L: se confunden al dictarlos por teléfono'
        );
    }

    /** @test */
    public function knowing_the_printed_serial_opens_nothing(): void
    {
        $label = Label::factory()->create(['status' => 'available']);

        // El serial está impreso en la etiqueta y a la vista de cualquiera.
        $this->get("/p/{$label->serial}")->assertNotFound();
        $this->get("/garantia/{$label->serial}/registrar")->assertNotFound();

        // El código del QR sí abre.
        $this->get("/p/{$label->public_token}")->assertOk();
    }

    /** @test */
    public function an_invented_code_opens_nothing(): void
    {
        Label::factory()->count(3)->create();

        foreach (['AAAAAAAAAAAAAAAAAAAA', '23456789234567892345', 'ZZZZZZZZZZZZZZZZZZZZ'] as $inventado) {
            $this->get("/p/{$inventado}")->assertNotFound();
        }
    }

    /** @test */
    public function the_qr_carries_the_public_code_and_never_the_serial(): void
    {
        $label = Label::factory()->create();

        $this->assertStringContainsString($label->public_token, $label->qr_url);
        $this->assertStringNotContainsString($label->serial, $label->qr_url);
    }

    /** @test */
    public function public_lookups_are_capped_so_nobody_can_sweep_the_system(): void
    {
        $label = Label::factory()->create(['status' => 'available']);

        // El límite es de 60 por minuto y por IP.
        for ($i = 0; $i < 60; $i++) {
            $this->get("/p/{$label->public_token}");
        }

        $this->get("/p/{$label->public_token}")->assertStatus(429);
    }

    private function prefijoComun(array $codigos): string
    {
        $prefijo = array_shift($codigos);

        foreach ($codigos as $codigo) {
            while ($prefijo !== '' && ! str_starts_with($codigo, $prefijo)) {
                $prefijo = substr($prefijo, 0, -1);
            }
        }

        return $prefijo;
    }
}
