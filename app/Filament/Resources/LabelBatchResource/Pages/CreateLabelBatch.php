<?php

namespace App\Filament\Resources\LabelBatchResource\Pages;

use App\Filament\Resources\LabelBatchResource;
use App\Jobs\ProcessSingleQueue;
use App\Models\LabelLog;
use App\Models\ZebraPrintSetting;
use App\Services\PrintQueueService;
use App\Services\SerialGeneratorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateLabelBatch extends CreateRecord
{
    protected static string $resource = LabelBatchResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear e imprimir');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generación movida al modelo LabelBatch::booted() como safety net
        return $data;
    }

    protected function afterCreate(): void
    {
        $batch = $this->record;

        // ── 1. Generar etiquetas ─────────────────────────────────────────────
        $service = app(SerialGeneratorService::class);
        $result = $service->generateLabelsForBatch($batch);

        if (!$result) {
            Notification::make()
                ->title('El lote ya tenía etiquetas generadas')
                ->warning()
                ->seconds(5)
                ->send();
            return;
        }

        LabelLog::create([
            'label_batch_id' => $batch->id,
            'user_id'        => auth()->id(),
            'action'         => 'generated',
            'description'    => 'Etiquetas generadas automáticamente para lote ' . $batch->internal_batch_code,
            'ip'             => request()->ip(),
            'created_at'     => now(),
        ]);

        // ── 2. Auto-impresión ────────────────────────────────────────────────
        $this->autoPrint($batch);
    }

    /**
     * Auto-imprimir: toma la Zebra configurada, crea cola y dispara job.
     *
     * - Network: dispathea ProcessSingleQueue inmediatamente (impresión directa).
     * - USB: solo crea la cola, el agente Windows la recoge via API.
     */
    protected function autoPrint($batch): void
    {
        $setting = ZebraPrintSetting::where('active', true)->first();

        if (!$setting || !$setting->isAnyPrinterConfigured()) {
            Notification::make()
                ->title('Lote creado — pendiente de impresión')
                ->body('Etiquetas generadas. Para imprimir, configurá una impresora en Configuración Zebra.')
                ->warning()
                ->seconds(10)
                ->send();
            return;
        }

        try {
            $queueService = app(PrintQueueService::class);

            $ip = $setting->isNetworkConfigured() ? $setting->printer_ip : '';
            $port = $setting->printer_port ?? 9100;

            // Crear cola de impresión con items + ZPL pre-generado
            $queue = $queueService->createQueueForBatch(
                batch: $batch,
                ip: $ip,
                port: $port,
                userId: auth()->id(),
                connectionType: $setting->connection_type,
                printerName: $setting->printer_name,
            );

            $printerInfo = $setting->getPrinterEndpoint();

            if ($setting->isUsbConfigured()) {
                // ── USB: el agente Windows lo procesa ──────────────────────
                LabelLog::create([
                    'label_batch_id' => $batch->id,
                    'user_id'        => auth()->id() ?? 1,
                    'action'         => 'agent_queue_created',
                    'description'    => "Cola #{$queue->id} creada para agente USB: {$batch->quantity} etiquetas para {$printerInfo}",
                    'ip'             => request()->ip(),
                    'created_at'     => now(),
                ]);

                Notification::make()
                    ->title('Lote creado — en cola para impresión USB')
                    ->body("{$batch->quantity} etiquetas en cola. El agente Windows las va a imprimir en {$printerInfo}")
                    ->success()
                    ->seconds(10)
                    ->send();
            } else {
                // ── Network: dispathear job inmediatamente ─────────────────
                ProcessSingleQueue::dispatch($queue->id, useStatusCheck: true);

                Notification::make()
                    ->title('Lote creado — imprimiendo...')
                    ->body("{$batch->quantity} etiquetas en cola para {$printerInfo}")
                    ->success()
                    ->seconds(8)
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Auto-print falló al crear cola', [
                'batch_id' => $batch->id,
                'error'    => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Lote creado, error al imprimir')
                ->body('Las etiquetas se generaron pero hubo un error al crear la cola de impresión: ' . $e->getMessage())
                ->danger()
                ->seconds(10)
                ->send();
        }
    }
}
