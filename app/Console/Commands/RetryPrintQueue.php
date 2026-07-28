<?php

namespace App\Console\Commands;

use App\Models\PrintQueue;
use App\Services\PrintQueueService;
use Illuminate\Console\Command;

class RetryPrintQueue extends Command
{
    protected $signature   = 'print:retry {queue_id : ID de la cola a reintentar}';
    protected $description = 'Reintenta las etiquetas fallidas de una cola de impresión';

    public function handle(PrintQueueService $service): void
    {
        $queueId = (int) $this->argument('queue_id');

        $queue = PrintQueue::find($queueId);

        if (!$queue) {
            $this->error("Cola #{$queueId} no encontrada.");
            return;
        }

        $failedCount = $queue->items()->where('status', 'failed')->count();

        if ($failedCount === 0) {
            $this->info("No hay items fallidos en la cola #{$queueId}.");
            return;
        }

        $result = $service->retryFailed($queue);

        $this->info("{$result['reset']} items reseteados a pending en cola #{$queueId}.");
        $this->line("El scheduler procesará automáticamente dentro de 1 minuto.");

        // Ejecutar procesamiento inmediato para no esperar el scheduler
        // autoPause: false → el reintento explícito procesa todo, no pausa
        $this->line("Procesando inmediatamente...");
        $processResult = $service->processQueue($queue, autoPause: false);

        $this->info("Reintento completado: {$processResult['printed']}/{$processResult['total']} impresas, {$processResult['failed']} fallidas.");
    }
}
