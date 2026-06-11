<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\LabelLog;
use App\Models\PrintQueue;
use App\Models\PrintQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrintQueueAgentController extends Controller
{
    /**
     * GET /api/agent/pending
     *
     * Devuelve las colas USB pendientes con items listos para imprimir.
     * El agente Windows consulta este endpoint cada N segundos.
     */
    public function pending(): JsonResponse
    {
        // Buscar colas USB en estado pending o partial
        $queues = PrintQueue::whereIn('status', ['pending', 'partial'])
            ->where('connection_type', 'usb')
            ->whereNotNull('printer_name')
            ->with(['items' => function ($q) {
                $q->whereIn('status', ['pending', 'printing'])
                    ->orderBy('sequence');
            }])
            ->get();

        if ($queues->isEmpty()) {
            return response()->json([
                'success' => true,
                'queues'  => [],
                'message' => 'No hay colas pendientes',
            ]);
        }

        $result = [];

        foreach ($queues as $queue) {
            $items = [];

            foreach ($queue->items as $item) {
                // Marcar como "printing" para evitar duplicados
                $item->update(['status' => 'printing']);

                $items[] = [
                    'item_id'      => $item->id,
                    'queue_id'     => $queue->id,
                    'sequence'     => $item->sequence,
                    'zpl_content'  => $item->zpl_content,
                ];
            }

            $result[] = [
                'queue_id'      => $queue->id,
                'batch_id'      => $queue->label_batch_id,
                'printer_name'  => $queue->printer_name,
                'total_items'   => count($items),
                'items'         => $items,
            ];

            // Marcar cola como processing
            if ($queue->status === 'pending') {
                $queue->update([
                    'status'     => 'processing',
                    'started_at' => $queue->started_at ?? now(),
                ]);
            }

            Log::info('PrintAgent: cola asignada', [
                'queue_id'   => $queue->id,
                'printer'    => $queue->printer_name,
                'items'      => count($items),
            ]);
        }

        return response()->json([
            'success' => true,
            'queues'  => $result,
        ]);
    }

    /**
     * POST /api/agent/{queueId}/item/{itemId}/complete
     *
     * Marcar un item como impreso exitosamente.
     */
    public function completeItem(int $queueId, int $itemId): JsonResponse
    {
        $item = PrintQueueItem::where('print_queue_id', $queueId)
            ->findOrFail($itemId);

        $item->markAsPrinted();

        // Actualizar label
        if ($item->label_id) {
            $item->label()->update([
                'printed_at' => now(),
                'status'     => 'printed',
            ]);
        }

        $item->printQueue->increment('printed_labels');

        Log::info('PrintAgent: item completado', [
            'queue_id' => $queueId,
            'item_id'  => $itemId,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Item #{$itemId} marcado como impreso",
        ]);
    }

    /**
     * POST /api/agent/{queueId}/item/{itemId}/failed
     *
     * Marcar un item como fallido.
     */
    public function failItem(int $queueId, int $itemId): JsonResponse
    {
        $item = PrintQueueItem::where('print_queue_id', $queueId)
            ->findOrFail($itemId);

        $item->incrementAttempt('Error reportado por el agente Windows');

        Log::warning('PrintAgent: item fallido', [
            'queue_id' => $queueId,
            'item_id'  => $itemId,
            'attempts' => $item->fresh()->attempts,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Item #{$itemId} marcado como fallido",
        ]);
    }

    /**
     * POST /api/agent/{queueId}/complete
     *
     * Marcar cola completa como terminada.
     */
    public function completeQueue(int $queueId): JsonResponse
    {
        $queue = PrintQueue::findOrFail($queueId);

        $finishedStatus = $queue->determineFinalStatus();

        $queue->update([
            'status'      => $finishedStatus,
            'finished_at' => now(),
        ]);

        // Si la cola se completó, marcar el LabelBatch como impreso
        if ($finishedStatus === 'completed') {
            $batch = $queue->labelBatch;
            if ($batch && $batch->status === 'generated') {
                $batch->update([
                    'status'     => 'printed',
                    'printed_at' => now(),
                ]);
            }

            LabelLog::create([
                'label_batch_id' => $queue->label_batch_id,
                'user_id'        => $queue->user_id ?? 1,
                'action'         => 'agent_print_completed',
                'description'    => "Impresión completada por agente Windows para cola #{$queueId}",
                'ip'             => '127.0.0.1',
                'created_at'     => now(),
            ]);
        }

        Log::info('PrintAgent: cola completada', [
            'queue_id' => $queueId,
            'status'   => $finishedStatus,
        ]);

        return response()->json([
            'success' => true,
            'status'  => $finishedStatus,
            'message' => "Cola #{$queueId} finalizada con estado: {$finishedStatus}",
        ]);
    }

    /**
     * GET /api/agent/status
     *
     * Health check simple para el agente.
     */
    public function status(): JsonResponse
    {
        $pendingQueues = PrintQueue::whereIn('status', ['pending', 'partial'])
            ->where('connection_type', 'usb')
            ->count();

        $processingQueues = PrintQueue::where('status', 'processing')
            ->where('connection_type', 'usb')
            ->count();

        return response()->json([
            'success'  => true,
            'agent'    => 'PrintQueueAgent',
            'server'   => config('app.name'),
            'pending'  => $pendingQueues,
            'processing' => $processingQueues,
            'time'     => now()->toIso8601String(),
        ]);
    }
}
