<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\PrintQueue;
use App\Models\ZebraPrintSetting;
use App\Services\ZebraZplService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPrintQueue extends Command
{
    protected $signature   = 'print:process';
    protected $description = 'Procesa la cola de impresión: genera ZPL por chunks y envía a la Zebra por TCP/IP';

    public function handle(): void
    {
        $jobs = PrintQueue::where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        if ($jobs->isEmpty()) {
            return;
        }

        $settings = ZebraPrintSetting::where('active', true)->first();
        $service  = new ZebraZplService($settings);
        $chunkSize = $settings?->chunk_size ?? 500;

        foreach ($jobs as $job) {
            $this->processJob($job, $service, $chunkSize);
        }
    }

    protected function processJob(PrintQueue $job, ZebraZplService $service, int $chunkSize): void
    {
        $batch = $job->labelBatch;

        if (!$batch) {
            $this->failJob($job, 'El lote asociado no existe.');
            return;
        }

        $this->info("Trabajo #{$job->id}: lote {$batch->internal_batch_code} → {$job->zebra_ip}:{$job->zebra_port}");

        $job->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            $totalLabels = $batch->labels()
                ->whereNull('printed_at')
                ->where('status', '!=', 'anulled')
                ->count();

            if ($totalLabels === 0) {
                $this->info("  Sin etiquetas pendientes, marcando como completado.");
                $job->update([
                    'status'       => 'completed',
                    'total_labels' => 0,
                    'sent_labels'  => 0,
                    'finished_at'  => now(),
                ]);
                return;
            }

            $printedCount = 0;
            $totalChunks  = (int) ceil($totalLabels / $chunkSize);
            $chunkIndex   = 0;
            $errors       = [];

            $batch->labels()
                ->whereNull('printed_at')
                ->where('status', '!=', 'anulled')
                ->orderBy('sequence_number')
                ->chunk($chunkSize, function ($labels) use (
                    $service, $job, $batch, &$printedCount, $totalLabels,
                    &$chunkIndex, $totalChunks, &$errors
                ) {
                    $chunkIndex++;

                    try {
                        DB::beginTransaction();

                        // 1. Generar ZPL para este chunk
                        $zpl = $service->generateForLabels($labels);

                        // 2. Enviar a la Zebra por TCP/IP
                        $socket = @fsockopen($job->zebra_ip, $job->zebra_port, $errno, $errstr, 10);

                        if (!$socket) {
                            throw new \Exception(
                                "Chunk {$chunkIndex}/{$totalChunks}: no se pudo conectar a "
                                . "{$job->zebra_ip}:{$job->zebra_port} — {$errstr}"
                            );
                        }

                        fwrite($socket, $zpl);
                        fclose($socket);

                        // 3. Marcar etiquetas como impresas
                        $now = now();
                        Label::whereIn('id', $labels->pluck('id'))
                            ->update([
                                'printed_at' => $now,
                                'status'     => 'printed',
                            ]);

                        $printedCount += $labels->count();
                        $job->update(['sent_labels' => $printedCount]);

                        DB::commit();

                        $this->line(
                            "  [{$chunkIndex}/{$totalChunks}] "
                            . "{$labels->count()} etiquetas enviadas "
                            . "({$printedCount}/{$totalLabels})"
                        );

                        // 4. Pausa entre chunks para no saturar la Zebra
                        if ($printedCount < $totalLabels) {
                            usleep(200_000);
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Print queue chunk exception, rolled back', [
                            'job_id'     => $job->id,
                            'chunk'      => $chunkIndex,
                            'chunk_size' => $labels->count(),
                            'error'      => $e->getMessage(),
                        ]);
                        throw $e; // Re-lanzar para que el catch exterior marque el job como failed
                    }
                });

            if (!empty($errors)) {
                throw new \Exception(implode('; ', $errors));
            }

            // 5. Marcar lote como impreso
            $batch->update([
                'status'     => 'printed',
                'printed_at' => now(),
            ]);

            // 6. Registro de auditoría
            LabelLog::create([
                'label_batch_id' => $batch->id,
                'user_id'        => $job->user_id,
                'action'         => 'printed_queue',
                'description'    => "Impreso por cola: lote {$batch->internal_batch_code}, "
                    . "{$printedCount} etiquetas enviadas a {$job->zebra_ip}",
                'ip'             => '127.0.0.1',
                'created_at'     => now(),
            ]);

            // 7. Finalizar trabajo
            $job->update([
                'status'       => 'completed',
                'total_labels' => $totalLabels,
                'sent_labels'  => $printedCount,
                'finished_at'  => now(),
            ]);

            $this->info("  ✓ Trabajo #{$job->id} completado: {$printedCount}/{$totalLabels} etiquetas.");

        } catch (\Exception $e) {
            $this->failJob($job, $e->getMessage());
        }
    }

    protected function failJob(PrintQueue $job, string $error): void
    {
        $job->update([
            'status'        => 'failed',
            'error_message' => $error,
            'finished_at'   => now(),
        ]);

        $this->error("  ✗ Trabajo #{$job->id} falló: {$error}");
    }
}
