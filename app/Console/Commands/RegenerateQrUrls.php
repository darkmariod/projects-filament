<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Services\SerialGeneratorService;
use Illuminate\Console\Command;

class RegenerateQrUrls extends Command
{
    protected $signature = 'labels:regenerate-qr-urls';
    protected $description = 'Genera tokens públicos faltantes y regenera la URL del QR de todas las etiquetas usando APP_URL actual';

    public function handle(SerialGeneratorService $serialService): int
    {
        $total        = Label::count();
        $bar          = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $gotToken = 0;

        Label::chunk(100, function ($labels) use ($serialService, $bar, &$updated, &$gotToken) {
            foreach ($labels as $label) {
                // 1) Generar token público si falta (etiquetas pre-existentes a la Fase 1)
                if (empty($label->public_token)) {
                    $label->public_token = $serialService->generatePublicToken();
                    $gotToken++;
                }

                // 2) Reconstruir la URL pública con el token (nuevo esquema seguro)
                $newUrl = $serialService->buildPublicUrl($label->public_token);
                if ($label->qr_url !== $newUrl) {
                    $label->qr_url = $newUrl;
                    $updated++;
                }

                // Guardar solo si hubo algún cambio real
                if ($label->isDirty('public_token') || $label->isDirty('qr_url')) {
                    $label->saveQuietly();
                }
            }
            $bar->advance(count($labels));
        });

        $bar->finish();
        $this->newLine();
        $this->info("Backfill completado. {$gotToken} tokens generados, {$updated} QR URLs actualizadas de {$total} etiquetas.");

        return Command::SUCCESS;
    }
}
