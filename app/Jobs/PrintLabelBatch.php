<?php

namespace App\Jobs;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\ZebraPrintSetting;
use App\Services\ZebraZplService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PrintLabelBatch implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public LabelBatch $batch,
        public ?int $startFrom = null,
        public ?int $chunkSize = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ZebraZplService $service): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $settings = ZebraPrintSetting::where('active', true)->first();

        if (!$settings || !$settings->isAnyPrinterConfigured()) {
            Log::error('PrintLabelBatch: Zebra sin configurar', [
                'batch_id' => $this->batch->id,
            ]);
            $this->fail(new \RuntimeException('Zebra printer is not configured'));
            return;
        }

        $service = new ZebraZplService($settings);
        $chunkSize = $this->chunkSize ?? ($settings->chunk_size ?? 500);

        $labels = $this->batch->labels()
            ->whereNull('printed_at')
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number');

        if ($this->startFrom !== null) {
            $labels->where('sequence_number', '>=', $this->startFrom);
        }

        $labels->chunk($chunkSize, function ($chunk) use ($service, $settings) {
            if ($this->batch()->cancelled()) {
                return false;
            }

            $zpl = $service->generateForLabels($chunk);
            $result = $service->sendToConfiguredPrinter($zpl);

            if (!$result['success']) {
                Log::error('PrintLabelBatch: chunk failed', [
                    'batch_id' => $this->batch->id,
                    'error'    => $result['message'],
                ]);
                $this->fail(new \RuntimeException($result['message']));
                return false;
            }

            $now = now();
            Label::whereIn('id', $chunk->pluck('id'))
                ->update([
                    'printed_at' => $now,
                    'status'     => 'printed',
                ]);

            // Pausa entre chunks
            usleep(200_000);

            return true;
        });

        // Marcar batch como impreso
        $this->batch->update([
            'status'     => 'printed',
            'printed_at' => now(),
        ]);

        LabelLog::create([
            'label_batch_id' => $this->batch->id,
            'user_id'        => $this->batch->generated_by_user_id ?? 1,
            'action'         => 'printed_queue',
            'description'    => "Impreso por job queue: lote {$this->batch->internal_batch_code}",
            'ip'             => '127.0.0.1',
            'created_at'     => now(),
        ]);
    }
}
