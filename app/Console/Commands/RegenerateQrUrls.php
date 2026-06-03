<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Services\SerialGeneratorService;
use Illuminate\Console\Command;

class RegenerateQrUrls extends Command
{
    protected $signature = 'labels:regenerate-qr-urls';
    protected $description = 'Regenera la URL del QR de todas las etiquetas usando APP_URL actual';

    public function handle(SerialGeneratorService $serialService): int
    {
        $total = Label::count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        Label::chunk(100, function ($labels) use ($serialService, $bar, &$updated) {
            foreach ($labels as $label) {
                $newUrl = $serialService->buildQrUrl($label->serial);
                if ($label->qr_url !== $newUrl) {
                    $label->qr_url = $newUrl;
                    $label->saveQuietly();
                    $updated++;
                }
            }
            $bar->advance(count($labels));
        });

        $bar->finish();
        $this->newLine();
        $this->info("QR URLs regeneradas. {$updated} de {$total} etiquetas actualizadas.");

        return Command::SUCCESS;
    }
}
