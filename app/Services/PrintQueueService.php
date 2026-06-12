<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\PrintQueue;
use App\Models\PrintQueueItem;
use App\Services\ZebraZplService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintQueueService
{
    public function __construct(
        protected ZebraZplService $zebraService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    //  CREAR COLA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crear una cola de impresión para un lote completo.
     * Genera un PrintQueue + un PrintQueueItem por cada label.
     *
     * @param  string  $ip              Dirección IP (vacio para USB)
     * @param  int     $port            Puerto (default 9100)
     * @param  int|null  $userId        Usuario que crea la cola
     * @param  string|null $connectionType  'network'|'usb' (default 'network')
     * @param  string|null $printerName     Nombre de impresora USB
     */
    public function createQueueForBatch(
        LabelBatch $batch,
        string $ip = '',
        int $port = 9100,
        ?int $userId = null,
        ?string $connectionType = null,
        ?string $printerName = null
    ): PrintQueue {
        $userId ??= auth()->id() ?? 1;
        $connectionType ??= 'network';

        $labels = $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->get();

        $total = $labels->count();

        DB::beginTransaction();

        try {
            $queue = PrintQueue::create([
                'label_batch_id'  => $batch->id,
                'user_id'         => $userId,
                'zebra_ip'        => $ip,
                'zebra_port'      => $port,
                'connection_type' => $connectionType,
                'printer_name'    => $printerName,
                'status'          => 'pending',
                'total_labels'    => $total,
                'printed_labels'  => 0,
                'failed_labels'   => 0,
            ]);

            // Pre-generar ZPL para cada etiqueta y crear items
            $items = [];
            foreach ($labels as $i => $label) {
                $zpl = $this->zebraService->generateZplForItem($label);

                $items[] = [
                    'print_queue_id' => $queue->id,
                    'label_id'       => $label->id,
                    'sequence'       => $i + 1,
                    'zpl_content'    => $zpl,
                    'status'         => 'pending',
                    'attempts'       => 0,
                    'max_attempts'   => 3,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            // Bulk insert items
            PrintQueueItem::insert($items);

            // Log de auditoría
            LabelLog::create([
                'label_batch_id' => $batch->id,
                'user_id'        => $userId,
                'action'         => 'print_queue_created',
                'description'    => "Cola de impresión #{$queue->id} creada: {$total} etiquetas para {$ip}:{$port}",
                'ip'             => request()->ip() ?? '127.0.0.1',
                'created_at'     => now(),
            ]);

            DB::commit();

            return $queue;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando cola de impresión', [
                'batch_id' => $batch->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PROCESAR COLA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Procesar una cola: verifica estado de la impresora según el tipo de
     * conexión y envía item por item.
     *
     * - USB: checkUsbPrinterStatus() antes + sendToUsbPrinter() por item
     * - Network: checkPrinterStatus() antes + sendWithSgdCheck() por item
     * - Si la impresora está offline antes de empezar → pausa printer_offline
     * - Items que fallan por ZPL/datos → se marcan como falla y continúa
     * - Items que fallan por conexión (refused/timeout) → pausa la cola
     *
     * @param  bool  $autoPause  Si true (default), pausa la cola en errores de conexión.
     *                           Si false, marca como failed y continúa (útil para reintentos).
     *
     * @return array{processed: int, printed: int, failed: int, total: int, paused: bool}
     */
    public function processQueue(PrintQueue $queue, bool $autoPause = true): array
    {
        if ($queue->isUsbConnection()) {
            // ── USB ────────────────────────────────────────────────────────

            $status = $this->zebraService->checkUsbPrinterStatus($queue->printer_name);

            if ($status !== ZebraZplService::STATUS_READY) {
                $message = $this->zebraService->getStatusMessage($status);

                if ($autoPause) {
                    $this->pause($queue, $message, 'printer_offline');
                }

                $totalItems = $queue->items()
                    ->whereIn('status', ['pending', 'printing'])
                    ->count();

                return [
                    'processed' => 0,
                    'printed'   => 0,
                    'failed'    => 0,
                    'total'     => $totalItems,
                    'paused'    => $autoPause,
                ];
            }

            return $this->processItems(
                $queue,
                fn($item, $queue) => $this->zebraService->sendToUsbPrinter(
                    zpl: $item->zpl_content,
                ),
                $autoPause,
            );
        }

        // ── Network (default) ──────────────────────────────────────────────

        $status = $this->zebraService->checkPrinterStatus($queue->zebra_ip, $queue->zebra_port);

        if ($status !== ZebraZplService::STATUS_READY) {
            $message = $this->zebraService->getStatusMessage($status);

            if ($autoPause) {
                $this->pause($queue, $message, 'printer_offline');
            }

            $totalItems = $queue->items()
                ->whereIn('status', ['pending', 'printing'])
                ->count();

            return [
                'processed' => 0,
                'printed'   => 0,
                'failed'    => 0,
                'total'     => $totalItems,
                'paused'    => $autoPause,
            ];
        }

        return $this->processItems(
            $queue,
            fn($item, $queue) => $this->zebraService->sendWithSgdCheck(
                zpl: $item->zpl_content,
                ip: $queue->zebra_ip,
                port: $queue->zebra_port,
                timeout: 10,
            ),
            $autoPause,
        );
    }

    /**
     * Procesar una cola CON verificación de estado de la impresora.
     *
     * @deprecated Usar processQueue() en su lugar — ahora siempre verifica estado.
     *
     * @return array{processed: int, printed: int, failed: int, total: int, paused: bool}
     */
    public function processQueueWithStatusCheck(PrintQueue $queue, bool $autoPause = true): array
    {
        Log::warning('processQueueWithStatusCheck() está deprecado, usar processQueue()');

        return $this->processQueue($queue);
    }

    /**
     * Procesar items de una cola con la función de envío dada.
     * Factor común entre los distintos modos de conexión.
     *
     * Reglas:
     * - Solo procesa items pending o printing (no reprocesa printed/cancelled)
     * - Máximo 3 intentos por item
     * - Items que fallan por ZPL/datos se marcan como falla y continúa al siguiente
     * - Excepciones de conexión (refused/timeout) pausan la cola con printer_offline
     * - Nunca retrocede: items ya printed se saltan
     */
    protected function processItems(
        PrintQueue $queue,
        callable $sendFn,
        bool $autoPause = true,
    ): array {
        $queue->update([
            'status'     => 'processing',
            'started_at' => $queue->started_at ?? now(),
        ]);

        $items = $queue->items()
            ->whereIn('status', ['pending', 'printing'])
            ->orderBy('sequence')
            ->get();

        Log::info('PrintQueue processing started', [
            'queue_id' => $queue->id,
            'batch_id' => $queue->label_batch_id,
            'items'    => $items->count(),
            'printer'  => $queue->isUsbConnection()
                ? "USB: {$queue->printer_name}"
                : "{$queue->zebra_ip}:{$queue->zebra_port}",
        ]);

        $processed = 0;
        $printed   = 0;
        $failed    = 0;
        $total     = $items->count();
        $paused    = false;

        foreach ($items as $item) {
            $processed++;

            try {
                $result = $sendFn($item, $queue);

                if ($result['success']) {
                    // ✅ Item impreso
                    $item->markAsPrinted();
                    $item->label()->update([
                        'printed_at' => now(),
                        'status'     => 'printed',
                    ]);
                    $printed++;
                    $queue->increment('printed_labels');
                } else {
                    // ❌ Item falló — NO pausar, solo marcar falla y continuar
                    $item->incrementAttempt($result['message']);
                    $failed++;
                    $queue->increment('failed_labels');

                    Log::warning('PrintQueueItem falló', [
                        'item_id'  => $item->id,
                        'attempts' => $item->fresh()->attempts,
                        'error'    => $result['message'],
                    ]);
                }
            } catch (\Throwable $e) {
                $item->incrementAttempt($e->getMessage());
                $failed++;
                $queue->increment('failed_labels');

                Log::error('PrintQueueItem exception', [
                    'item_id' => $item->id,
                    'error'   => $e->getMessage(),
                ]);

                if ($autoPause) {
                    // ⏸ PAUSA solo si es error de conexión (no ZPL/datos)
                    $msg = strtolower($e->getMessage());
                    if (str_contains($msg, 'connection refused')
                        || str_contains($msg, 'timeout')
                        || str_contains($msg, 'could not connect')
                    ) {
                        $this->pause($queue, $e->getMessage(), 'printer_offline');
                        $paused = true;
                        break;
                    }
                }
            }

            // Pequeña pausa entre etiquetas para no saturar la Zebra
            usleep(50_000); // 50ms
        }

        // Si se pausó, no recalcular estado final
        if (!$paused) {
            $finalStatus = $queue->determineFinalStatus();
            $queue->update([
                'status'      => $finalStatus,
                'finished_at' => now(),
            ]);

            if ($finalStatus === 'completed') {
                LabelLog::create([
                    'label_batch_id' => $queue->label_batch_id,
                    'user_id'        => $queue->user_id,
                    'action'         => 'print_queue_completed',
                    'description'    => "Cola #{$queue->id} completada: {$printed}/{$total} impresas",
                    'ip'             => '127.0.0.1',
                    'created_at'     => now(),
                ]);

                // Marcar el LabelBatch como impreso si se completó todo
                $batch = $queue->labelBatch;
                if ($batch && $batch->status === 'generated') {
                    $batch->update([
                        'status'     => 'printed',
                        'printed_at' => now(),
                    ]);
                }
            }
        }

        return [
            'processed' => $processed,
            'printed'   => $printed,
            'failed'    => $failed,
            'total'     => $total,
            'paused'    => $paused,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PAUSAR
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pausar una cola con mensaje de error y razón específica.
     *
     * @param  string      $reason       Mensaje de error visible
     * @param  string|null $pauseReason  Razón estructurada (printer_offline, etc.)
     */
    public function pause(PrintQueue $queue, string $reason, ?string $pauseReason = null): void
    {
        $update = [
            'status'        => 'paused',
            'finished_at'   => now(),
            'error_message' => $reason,
        ];

        if ($pauseReason !== null) {
            $update['pause_reason'] = $pauseReason;
        }

        $queue->update($update);

        LabelLog::create([
            'label_batch_id' => $queue->label_batch_id,
            'user_id'        => $queue->user_id,
            'action'         => 'print_queue_paused',
            'description'    => "Cola #{$queue->id} pausada: {$reason}",
            'ip'             => request()->ip() ?? '127.0.0.1',
            'created_at'     => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  REINTENTAR FALLIDAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resetear items failed a pending para reintentarlos.
     */
    public function retryFailed(PrintQueue $queue): array
    {
        $items = $queue->items()->where('status', 'failed')->get();

        foreach ($items as $item) {
            $item->resetForRetry();
        }

        // Volver la cola a processing o pending según corresponda
        $queue->update([
            'status'      => 'partial',
            'finished_at' => null,
        ]);

        LabelLog::create([
            'label_batch_id' => $queue->label_batch_id,
            'user_id'        => $queue->user_id,
            'action'         => 'print_queue_retry',
            'description'    => "Reintento de cola #{$queue->id}: {$items->count()} items reseteados",
            'ip'             => request()->ip() ?? '127.0.0.1',
            'created_at'     => now(),
        ]);

        return [
            'reset' => $items->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  REANUDAR COLA PAUSADA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Reanudar una cola pausada: resetea los items fallidos a pending,
     * limpia error_message y pause_reason, y la pone en partial para
     * que el scheduler la retome.
     */
    public function resume(PrintQueue $queue): void
    {
        // Resetear items failed a pending
        $failedItems = $queue->items()->where('status', 'failed')->get();
        foreach ($failedItems as $item) {
            $item->resetForRetry();
        }

        // Limpiar error y pause_reason, poner como partial
        $queue->update([
            'status'        => 'partial',
            'finished_at'   => null,
            'error_message' => null,
            'pause_reason'  => null,
        ]);

        LabelLog::create([
            'label_batch_id' => $queue->label_batch_id,
            'user_id'        => $queue->user_id,
            'action'         => 'print_queue_resumed',
            'description'    => "Cola #{$queue->id} reanudada: {$failedItems->count()} items reseteados para reintento",
            'ip'             => request()->ip() ?? '127.0.0.1',
            'created_at'     => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CANCELAR PENDIENTES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cancelar items pendientes de una cola.
     */
    public function cancelPending(PrintQueue $queue): array
    {
        $count = $queue->items()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $finalStatus = $queue->determineFinalStatus();
        $queue->update([
            'status'      => $finalStatus,
            'finished_at' => $finalStatus === 'failed' || $finalStatus === 'completed' ? now() : null,
        ]);

        return ['cancelled' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CREAR COLA DE UNA SOLA ETIQUETA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crear una cola de impresión con UNA sola etiqueta.
     * Útil para impresión individual desde el UI de etiquetas (cobro, reimpresión).
     *
     * @param  string      $zpl              ZPL ya generado
     * @param  int         $labelId          ID de la etiqueta
     * @param  string      $connectionType   'network'|'usb'
     * @param  string|null $printerName      Nombre de impresora USB
     * @param  string      $ip               IP para red
     * @param  int         $port             Puerto para red
     * @param  int|null    $userId           Usuario que crea la cola
     */
    public function createSingleQueue(
        string $zpl,
        int $labelId,
        string $connectionType = 'usb',
        ?string $printerName = null,
        string $ip = '',
        int $port = 9100,
        ?int $userId = null,
    ): PrintQueue {
        $userId ??= auth()->id() ?? 1;

        DB::beginTransaction();

        try {
            $queue = PrintQueue::create([
                'label_batch_id'  => null,
                'user_id'         => $userId,
                'zebra_ip'        => $ip,
                'zebra_port'      => $port,
                'connection_type' => $connectionType,
                'printer_name'    => $printerName,
                'status'          => 'pending',
                'total_labels'    => 1,
                'printed_labels'  => 0,
                'failed_labels'   => 0,
            ]);

            PrintQueueItem::create([
                'print_queue_id' => $queue->id,
                'label_id'       => $labelId,
                'sequence'       => 1,
                'zpl_content'    => $zpl,
                'status'         => 'pending',
                'attempts'       => 0,
                'max_attempts'   => 3,
            ]);

            if ($labelId) {
                LabelLog::create([
                    'label_id'    => $labelId,
                    'user_id'     => $userId,
                    'action'      => 'single_print_queued',
                    'description' => "Cola individual #{$queue->id} creada para reimpresión",
                    'ip'          => request()->ip() ?? '127.0.0.1',
                    'created_at'  => now(),
                ]);
            }

            DB::commit();

            return $queue;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando cola individual', [
                'label_id' => $labelId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ENVIAR UNA ETIQUETA (helper directo)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enviar ZPL de una sola etiqueta a la Zebra.
     * Útil para test o reimpresiones individuales.
     */
    public function sendSingleLabel(string $zpl, string $ip, int $port = 9100): array
    {
        return $this->zebraService->sendSingleLabel($zpl, $ip, $port);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ESTADO DE LA COLA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtener estado detallado de una cola.
     */
    public function getQueueStatus(PrintQueue $queue): array
    {
        $queue->loadCount([
            'items as total'        => fn($q) => $q,
            'items as pending'      => fn($q) => $q->where('status', 'pending'),
            'items as printing'     => fn($q) => $q->where('status', 'printing'),
            'items as printed'      => fn($q) => $q->where('status', 'printed'),
            'items as failed'       => fn($q) => $q->where('status', 'failed'),
            'items as cancelled'    => fn($q) => $q->where('status', 'cancelled'),
        ]);

        return [
            'queue_id'       => $queue->id,
            'status'         => $queue->status,
            'zebra'          => "{$queue->zebra_ip}:{$queue->zebra_port}",
            'total'          => $queue->total,
            'pending'        => $queue->pending,
            'printing'       => $queue->printing,
            'printed'        => $queue->printed,
            'failed'         => $queue->failed,
            'cancelled'      => $queue->cancelled,
            'progress_pct'   => $queue->total > 0
                ? round(($queue->printed / $queue->total) * 100)
                : 0,
            'started_at'     => $queue->started_at,
            'finished_at'    => $queue->finished_at,
            'error_message'  => $queue->error_message,
            'items'          => $queue->items()->with('label')->orderBy('sequence')->get()->map(fn($item) => [
                'id'            => $item->id,
                'sequence'      => $item->sequence,
                'serial'        => $item->label?->serial,
                'status'        => $item->status,
                'attempts'      => $item->attempts,
                'max_attempts'  => $item->max_attempts,
                'error_message' => $item->error_message,
                'printed_at'    => $item->printed_at,
            ]),
        ];
    }
}
