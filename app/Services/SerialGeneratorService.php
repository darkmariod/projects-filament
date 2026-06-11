<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SerialGeneratorService
{
    protected string $line = 'V';

    public function generateForBatch(LabelBatch $batch): array
    {
        $product  = Product::findOrFail($batch->product_id);
        $quantity = $batch->quantity;
        $date     = $batch->customer_batch_date;

        $yymm        = $date->format('ym');
        $productCode = strtoupper($product->product_code);

        $lastSequence = $this->getLastSequence($productCode, $yymm);

        $serials = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $lastSequence++;
            $sequence = str_pad($lastSequence, 8, '0', STR_PAD_LEFT);
            $dv       = $this->calculateDV($yymm, $productCode, $this->line, $sequence);
            $serial   = "{$yymm}-{$productCode}-{$this->line}-{$sequence}-{$dv}";

            while (Label::where('serial', $serial)->exists()) {
                $lastSequence++;
                $sequence = str_pad($lastSequence, 8, '0', STR_PAD_LEFT);
                $dv       = $this->calculateDV($yymm, $productCode, $this->line, $sequence);
                $serial   = "{$yymm}-{$productCode}-{$this->line}-{$sequence}-{$dv}";
            }

            $serials[] = [
                'serial'          => $serial,
                'sequence_number' => $lastSequence,
            ];
        }

        return $serials;
    }

    protected function getLastSequence(string $productCode, string $yymm): int
    {
        $last = Label::where('serial', 'like', "{$yymm}-{$productCode}-%")
            ->orderBy('sequence_number', 'desc')
            ->first();

        return $last ? (int) $last->sequence_number : 0;
    }

    protected function calculateDV(string $yymm, string $productCode, string $line, string $sequence): int
    {
        $data = $yymm . $productCode . $line . $sequence;
        $data = preg_replace('/[^0-9]/', '', $data);

        if (empty($data)) {
            return 0;
        }

        $sum = 0;
        $multiplier = 2;

        for ($i = strlen($data) - 1; $i >= 0; $i--) {
            $digit  = (int) $data[$i];
            $result = $digit * $multiplier;

            if ($result > 9) {
                $result -= 9;
            }

            $sum += $result;
            $multiplier = ($multiplier === 2) ? 1 : 2;
        }

        $dv = (10 - ($sum % 10)) % 10;

        return $dv;
    }

    /**
     * Genera un serial y sequence_number para un producto específico (creación individual).
     */
    public function generateForProduct(int $productId): array
    {
        $product = Product::findOrFail($productId);
        $yymm = now()->format('ym');
        $productCode = strtoupper($product->product_code);

        $lastSequence = $this->getLastSequence($productCode, $yymm);
        $nextSequence = $lastSequence + 1;
        $sequence = str_pad($nextSequence, 8, '0', STR_PAD_LEFT);
        $dv = $this->calculateDV($yymm, $productCode, $this->line, $sequence);
        $serial = "{$yymm}-{$productCode}-{$this->line}-{$sequence}-{$dv}";

        // Evitar colisiones (misma lógica que generateForBatch)
        while (Label::where('serial', $serial)->exists()) {
            $nextSequence++;
            $sequence = str_pad($nextSequence, 8, '0', STR_PAD_LEFT);
            $dv = $this->calculateDV($yymm, $productCode, $this->line, $sequence);
            $serial = "{$yymm}-{$productCode}-{$this->line}-{$sequence}-{$dv}";
        }

        return [
            'serial'          => $serial,
            'sequence_number' => $nextSequence,
        ];
    }

    public function buildQrUrl(string $serial): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/p/' . rawurlencode($serial);
    }

    public function generateLabelsForBatch(LabelBatch $batch): bool
    {
        return DB::transaction(function () use ($batch) {
            if ($batch->labels()->count() > 0) {
                return false;
            }

            $product = Product::findOrFail($batch->product_id);
            $serials = $this->generateForBatch($batch);

            $labelsToInsert = [];
            $now            = now();

            foreach ($serials as $item) {
                $qrUrl = $this->buildQrUrl($item['serial']);

                $labelsToInsert[] = [
                    'label_batch_id'  => $batch->id,
                    'product_id'      => $batch->product_id,
                    'serial'          => $item['serial'],
                    'sequence_number' => $item['sequence_number'],
                    'barcode'         => $product->barcode,
                    'qr_url'          => $qrUrl,
                    'zpl_generated'   => null,
                    'status'          => 'available',
                    'printed_at'      => null,
                    'registered_at'   => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            Label::insert($labelsToInsert);

            $batch->update([
                'generated_at' => $now,
                'status'       => 'generated',
            ]);

            return true;
        });
    }
}
