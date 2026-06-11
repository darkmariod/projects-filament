<?php

namespace App\Jobs;

use App\Models\PrintQueue;
use App\Models\LabelLog;
use App\Services\PrintQueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSingleQueue implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $queueId,
        public bool $useStatusCheck = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PrintQueueService $service): void
    {
        $queue = PrintQueue::find($this->queueId);

        if (!$queue) {
            Log::warning('ProcessSingleQueue: cola no encontrada', [
                'queue_id' => $this->queueId,
            ]);
            return;
        }

        // Solo procesar colas que estén en pending o partial
        if (!in_array($queue->status, ['pending', 'partial'], true)) {
            Log::info('ProcessSingleQueue: cola no procesable', [
                'queue_id' => $queue->id,
                'status'   => $queue->status,
            ]);
            return;
        }

        Log::info('ProcessSingleQueue iniciado', [
            'queue_id' => $queue->id,
            'printer'  => "{$queue->zebra_ip}:{$queue->zebra_port}",
            'use_status_check' => $this->useStatusCheck,
        ]);

        if ($this->useStatusCheck) {
            $result = $service->processQueueWithStatusCheck($queue);
        } else {
            $result = $service->processQueue($queue);
        }

        Log::info('ProcessSingleQueue completado', [
            'queue_id' => $queue->id,
            'result'   => $result,
        ]);

        // Log de auditoría
        if ($result['paused']) {
            LabelLog::create([
                'label_batch_id' => $queue->label_batch_id,
                'user_id'        => $queue->user_id ?? 1,
                'action'         => 'auto_print_paused',
                'description'    => "Auto-impresión pausada en item #{$result['processed']}: {$queue->error_message}",
                'ip'             => '127.0.0.1',
                'created_at'     => now(),
            ]);
        } elseif ($result['printed'] === $result['total']) {
            LabelLog::create([
                'label_batch_id' => $queue->label_batch_id,
                'user_id'        => $queue->user_id ?? 1,
                'action'         => 'auto_print_completed',
                'description'    => "Auto-impresión completada: {$result['printed']}/{$result['total']} etiquetas",
                'ip'             => '127.0.0.1',
                'created_at'     => now(),
            ]);
        } elseif ($result['failed'] > 0) {
            LabelLog::create([
                'label_batch_id' => $queue->label_batch_id,
                'user_id'        => $queue->user_id ?? 1,
                'action'         => 'auto_print_partial',
                'description'    => "Auto-impresión parcial: {$result['printed']} impresas, {$result['failed']} fallidas de {$result['total']}",
                'ip'             => '127.0.0.1',
                'created_at'     => now(),
            ]);
        }
    }
}
