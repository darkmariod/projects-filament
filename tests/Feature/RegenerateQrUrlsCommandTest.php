<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\RegenerateQrUrls;
use App\Models\Label;
use App\Services\SerialGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateQrUrlsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function backfill_generates_token_and_rebuilds_qr_url_for_existing_labels(): void
    {
        // Simula las 67 etiquetas pre-existentes a la Fase 1:
        // sin public_token y con qr_url basada en el serial (esquema viejo).
        $label = Label::factory()->create([
            'public_token' => null,
        ]);

        // qr_url vieja basada en serial (formato legado)
        $oldSerialUrl = 'http://localhost/p/' . $label->serial;
        $label->update(['qr_url' => $oldSerialUrl]);

        $this->artisan(RegenerateQrUrls::class)
            ->expectsOutputToContain('Backfill completado')
            ->assertExitCode(0);

        $label->refresh();

        $this->assertNotNull($label->public_token);
        $this->assertSame(20, strlen($label->public_token));
        // Ahora la URL usa el TOKEN, no el serial
        $this->assertStringContainsString('/p/' . $label->public_token, $label->qr_url);
        $this->assertStringNotContainsString('/p/' . $label->serial, $label->qr_url);
    }

    /** @test */
    public function backfill_preserves_existing_token(): void
    {
        // Una etiqueta ya migrada (con token) NO debe que se le regenere el token.
        $label = Label::factory()->create([
            'public_token' => 'EXISTINGTOKEN123456789',
            'qr_url'       => 'http://localhost/p/EXISTINGTOKEN123456789',
        ]);

        $this->artisan(RegenerateQrUrls::class)
            ->assertExitCode(0);

        $label->refresh();

        $this->assertSame('EXISTINGTOKEN123456789', $label->public_token);
        $this->assertStringContainsString('/p/EXISTINGTOKEN123456789', $label->qr_url);
    }
}
