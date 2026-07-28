<?php

namespace App\Console\Commands;

use App\Models\PrintQueue;
use App\Services\PrintQueueService;
use App\Services\ZebraZplService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPrintQueue extends Command
{
    protected $signature   = 'print:process';
    protected $description = 'Procesa la cola de impresión: envía item por item a la Zebra (TCP/IP o USB) con verificación de estado';

    public function handle(PrintQueueService $service): void
    {
        $queues = PrintQueue::whereIn('status', ['pending', 'partial', 'paused'])
            ->where('connection_type', '!=', 'usb') // USB lo maneja el agente Windows
            ->orderBy('created_at')
            ->get();

        if ($queues->isEmpty()) {
            return;
        }

        $this->info("Colas pendientes: {$queues->count()}");
        Log::info('ProcessPrintQueue: processing queues', [
            'queue_count' => $queues->count(),
        ]);

        foreach ($queues as $queue) {
            $printerId = $queue->connection_type === 'usb'
                ? $queue->printer_name
                : "{$queue->zebra_ip}:{$queue->zebra_port}";

            // For paused queues, check if printer is back online first
            if ($queue->status === 'paused') {
                $zebraService = app(ZebraZplService::class);

                if ($queue->connection_type === 'network') {
                    $status = $zebraService->checkPrinterStatus($queue->zebra_ip, $queue->zebra_port ?? 9100);
                    $printerReady = ($status === ZebraZplService::STATUS_READY);
                } elseif ($queue->connection_type === 'usb') {
                    $status = $zebraService->checkUsbPrinterStatus($queue->printer_name);
                    $printerReady = ($status === ZebraZplService::STATUS_READY);
                } else {
                    $status = 'unknown';
                    $printerReady = false;
                }

                if (! $printerReady) {
                    $this->warn("  ⏸ Cola #{$queue->id} — {$printerId} aún en pausa, impresora no disponible");
                    Log::info("PrintQueue #{$queue->id} skipped (printer still unavailable)", [
                        'queue_id'        => $queue->id,
                        'printer'         => $printerId,
                        'connection_type' => $queue->connection_type,
                        'printer_status'  => $status ?? 'unknown',
                    ]);
                    continue;
                }

                $service->resume($queue);
                $this->line("  ▶ Cola #{$queue->id} reanudada — impresora disponible");
            }

            $this->line("Procesando cola #{$queue->id} — {$printerId}");

            $result = $queue->connection_type === 'network'
                ? $service->processQueueWithStatusCheck($queue)
                : $service->processQueue($queue);

            $this->line("  Procesados: {$result['processed']}, "
                . "Impresas: {$result['printed']}, "
                . "Fallidas: {$result['failed']}, "
                . "Total: {$result['total']}");

            Log::info("PrintQueue #{$queue->id} processed", [
                'queue_id'        => $queue->id,
                'processed'       => $result['processed'],
                'printed'         => $result['printed'],
                'failed'          => $result['failed'],
                'total'           => $result['total'],
                'paused'          => $result['paused'] ?? false,
                'printer'         => $printerId,
                'connection_type' => $queue->connection_type,
            ]);

            if ($result['paused']) {
                $this->warn("  ⏸ Cola #{$queue->id} PAUSADA por error de conexión");
            } elseif ($result['failed'] > 0) {
                $this->warn("  {$result['failed']} etiquetas fallaron");
            }

            if ($result['printed'] === $result['total']) {
                $this->info("  ✓ Cola #{$queue->id} completada");
            }
        }
    }
}
