<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\ZebraPrintSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZebraZplService
{
    protected ZebraPrintSetting $settings;

    public function __construct(?ZebraPrintSetting $settings = null)
    {
        $this->settings = $settings ?? ZebraPrintSetting::where('active', true)->first()
            ?? new ZebraPrintSetting([
                'dpi'            => 203,
                'label_width_mm' => 95,
                'label_height_mm' => 200,
                'width_dots'     => 760,
                'height_dots'    => 1600,
                'margin_x'       => 16,
                'margin_y'       => 12,
                'qr_size'        => 5,
                'barcode_height' => 80,
                'printer_port'   => 9100,
                'chunk_size'     => 500,
            ]);
    }

    public function isLogoVisible(): bool
    {
        return $this->settings->show_logo;
    }

    /**
     * Sanitizar un string para uso seguro en comandos ZPL.
     *
     * Escapa caracteres de control ZPL (^, ~, \), elimina no imprimibles,
     * y trunca a maxLength si excede.
     */
    public function sanitizeField(string $value, int $maxLength = 100): string
    {
        // 1. Escapar caracteres de control ZPL: ^, ~, \
        $value = str_replace(['^', '~', '\\'], ['^^', '~', '\\\\'], $value);
        // 2. Eliminar caracteres no imprimibles (excepto saltos de línea)
        $value = preg_replace('/[^\x20-\x7E\x0A\x0D\xC0-\xFF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFFñÑáéíóúÁÉÍÓÚüÜ]/u', '', $value);
        // 3. Truncar a maxLength si excede
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength - 3) . '...';
        }
        return $value;
    }

    /**
     * Validar un código de barras para Code 128.
     *
     * @return array<int, string> Lista de errores (vacía si es válido)
     */
    public function validateBarcode(string $barcode): array
    {
        $errors = [];
        if (preg_match('/[^\x20-\x7E]/', $barcode)) {
            $errors[] = "El código de barras contiene caracteres no válidos para Code 128: '$barcode'";
        }
        if (strlen($barcode) > 48) {
            $errors[] = "El código de barras excede los 48 caracteres máximos";
        }
        if (empty(trim($barcode))) {
            $errors[] = "El código de barras está vacío";
        }
        return $errors;
    }

    /**
     * Enviar ZPL directamente a la impresora Zebra por TCP/IP.
     *
     * La Zebra ZT411 escucha en el puerto 9100 por defecto (protocolo RAW).
     * Se conecta, envía el ZPL, espera respuesta y cierra.
     *
     * @param  string  $zpl        Código ZPL completo a imprimir
     * @param  string  $ip         Dirección IP de la impresora
     * @param  int     $port       Puerto TCP (default: 9100)
     * @param  int     $timeout    Timeout en segundos
     * @return array{success: bool, message: string}
     */
    public function sendToPrinter(string $zpl, string $ip, int $port = 9100, int $timeout = 30): array
    {
        try {
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "No se pudo conectar a {$ip}:{$port} — {$errstr} ({$errno})",
                ];
            }

            // Configurar timeout de lectura/escritura
            stream_set_timeout($socket, $timeout);

            // Enviar ZPL completo
            $written = fwrite($socket, $zpl, strlen($zpl));

            if ($written === false || $written !== strlen($zpl)) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => 'Error al enviar datos a la impresora',
                ];
            }

            // Esperar un momento para que la impresora procese
            usleep(500_000); // 500ms

            // Leer respuesta si hay (algunas Zebras devuelven estado)
            $response = '';
            if (!$this->isSocketTimedOut($socket)) {
                $response = stream_get_contents($socket);
            }

            fclose($socket);

            $message = "Impresión enviada correctamente a {$ip}:{$port}";
            if (!empty($response)) {
                $message .= " | Respuesta: " . trim($response);
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            Log::error('Zebra TCP print failed', [
                'ip'      => $ip,
                'port'    => $port,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al imprimir: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar ZPL a la impresora usando la configuración activa.
     */
    public function sendToConfiguredPrinter(string $zpl): array
    {
        if (!$this->settings->isNetworkConfigured()) {
            return [
                'success' => false,
                'message' => 'No hay configuración de red para la impresora activa. Configurá la IP en ZebraPrintSettings.',
            ];
        }

        return $this->sendToPrinter(
            $zpl,
            $this->settings->printer_ip,
            $this->settings->printer_port
        );
    }

    /**
     * Generar ZPL en chunks y enviar cada bloque a la impresora.
     * Ideal para lotes grandes (1000+, 10000+, 20000 etiquetas).
     *
     * @param  LabelBatch $batch    Lote a imprimir
     * @param  callable|null $onChunk Callback opcional: fn(int $chunkNumber, int $totalChunks) => void
     * @return array{success: bool, message: string, printed: int, total: int}
     */
    public function printBatchChunked(LabelBatch $batch, ?callable $onChunk = null, ?int $userId = null, ?string $ip = null): array
    {
        $totalLabels = $batch->labels()
            ->where('status', '!=', 'anulled')
            ->count();

        if ($totalLabels === 0) {
            return [
                'success' => true,
                'message' => 'No hay etiquetas pendientes en este lote',
                'printed' => 0,
                'total'   => 0,
            ];
        }

        if (!$this->settings->isNetworkConfigured()) {
            return [
                'success' => false,
                'message' => 'Configurá la IP de la impresora en ZebraPrintSettings antes de imprimir por red',
                'printed' => 0,
                'total'   => $totalLabels,
            ];
        }

        $chunkSize = $this->settings->chunk_size ?? 500;
        $totalChunks = (int) ceil($totalLabels / $chunkSize);
        $printedCount = 0;
        $errors = [];

        $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->chunk($chunkSize, function ($labels) use (&$printedCount, &$errors, $totalLabels, $totalChunks, $batch, $onChunk) {
                $chunkNumber = (int) ceil($printedCount / $this->settings->chunk_size) + 1;

                try {
                    DB::beginTransaction();

                    // Generar ZPL para este chunk
                    $zpl = $this->generateForLabels($labels);
                    $zpl .= "^XA^FS^XZ\n"; // Feed final

                    // Enviar a la impresora
                    $result = $this->sendToConfiguredPrinter($zpl);

                    if (!$result['success']) {
                        DB::rollBack();
                        $errors[] = "Chunk {$chunkNumber}: " . $result['message'];
                        Log::warning('Print batch chunk failed, rolled back', [
                            'batch_id'    => $batch->id,
                            'chunk'       => $chunkNumber,
                            'chunk_size'  => $labels->count(),
                            'error'       => $result['message'],
                        ]);
                        return false; // Detener chunking
                    }

                    // Marcar como impresas
                    $now = now();
                    Label::whereIn('id', $labels->pluck('id'))
                        ->update([
                            'printed_at' => $now,
                            'status'     => 'printed',
                        ]);

                    DB::commit();

                    $printedCount += $labels->count();

                    // Callback de progreso
                    if ($onChunk) {
                        $onChunk($chunkNumber, $totalChunks, $printedCount, $totalLabels);
                    }

                    // Pausa entre chunks para no saturar la impresora
                    if ($printedCount < $totalLabels) {
                        usleep(200_000); // 200ms
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Chunk {$chunkNumber}: " . $e->getMessage();
                    Log::error('Print batch chunk exception, rolled back', [
                        'batch_id'   => $batch->id,
                        'chunk'      => $chunkNumber,
                        'chunk_size' => $labels->count(),
                        'error'      => $e->getMessage(),
                    ]);
                    return false;
                }
            });

        // Marcar batch si se imprimió todo
        if ($printedCount > 0) {
            $allPrinted = ($printedCount >= $totalLabels);
            $batch->update([
                'status'     => $allPrinted ? 'printed' : 'generated',
                'printed_at' => $allPrinted ? now() : $batch->printed_at,
            ]);

            $userId = $userId ?? request()?->user()?->id ?? 1;
            $ip = $ip ?? request()?->ip() ?? '127.0.0.1';

            LabelLog::create([
                'label_batch_id' => $batch->id,
                'user_id'        => $userId,
                'action'         => 'printed_network',
                'description'    => "Impreso por red: {$printedCount} de {$totalLabels} etiquetas"
                    . (!empty($errors) ? " | Errores: " . implode('; ', $errors) : ''),
                'ip'             => $ip,
                'created_at'     => now(),
            ]);
        }

        $success = empty($errors);

        return [
            'success' => $success,
            'message' => $success
                ? "{$printedCount} de {$totalLabels} etiquetas impresas correctamente"
                : "Impresas {$printedCount}/{$totalLabels}. Errores: " . implode('; ', $errors),
            'printed' => $printedCount,
            'total'   => $totalLabels,
        ];
    }

    public function mmToDots(float $mm): int
    {
        $dotsPerMm = match (true) {
            $this->settings->dpi >= 600 => 24,
            $this->settings->dpi >= 300 => 12,
            default                     => 8,
        };

        return (int) round($mm * $dotsPerMm);
    }

    public function generateForLabel(Label $label): string
    {
        $label->load(['product.productModel.category', 'product.technicalComposition', 'labelBatch']);

        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        $pw  = $this->settings->width_dots;
        $ll  = $this->settings->height_dots;
        $mx  = $this->settings->margin_x;
        $my  = $this->settings->margin_y;
        $qrs = $this->settings->qr_size;
        $bch = $this->settings->barcode_height;

        $serial      = $this->sanitizeField($label->serial);
        $qrUrl       = $this->sanitizeField($label->qr_url, 200);
        $productCode = $this->sanitizeField($product->product_code);
        $productName = $this->sanitizeField($product->name);
        $modelName   = $this->sanitizeField($model->name ?? '');
        $measurements = $this->sanitizeField($product->measurements_text ?? '');
        $batchNumber = $this->sanitizeField($batch->customer_batch_number ?? '');
        $batchDate   = $batch->customer_batch_date?->format('d/m/Y') ?? '';
        $operator    = $this->sanitizeField($batch->operator ?? '');
        $type        = $this->sanitizeField($model->type ?? 'Colchón');
        $class       = $this->sanitizeField($model->class ?? '');
        $barcode     = $this->sanitizeField($product->barcode ?? '', 48);

        $cover       = $this->sanitizeField($composition->cover_material ?? '');
        $springs     = $this->sanitizeField($composition->springs ?? '');
        $foam        = $this->sanitizeField($composition->foam_description ?? '');
        $conservation = $this->sanitizeField($composition->conservation_instructions ?? '');
        $manufacturer = $this->sanitizeField($composition->manufacturer ?? '');
        $ruc         = $this->sanitizeField($composition->manufacturer_ruc ?? '');
        $address     = $this->sanitizeField($composition->manufacturer_address ?? '');
        $inen        = $this->sanitizeField($composition->inen_standard ?? 'NTE INEN 2035');
        $website     = $this->sanitizeField($composition->website ?? '');
        $legalText   = $this->sanitizeField($composition->legal_text ?? '');

        // ── SECCIÓN 1: TRAZABILIDAD (aparece dos veces) ───────────────────
        $sec1_y  = $my;
        $col1_x  = $mx;
        $col2_x  = $mx + 400;

        $sec1 = $this->buildSection1(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $operator,
            $col1_x, $col2_x, $sec1_y
        );

        // Segunda repetición de trazabilidad
        $sec1b_y = $sec1_y + 220;
        $sec1b   = $this->buildSection1(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $operator,
            $col1_x, $col2_x, $sec1b_y
        );

        // ── SECCIÓN 2: COMPOSICIÓN TÉCNICA ───────────────────────────────
        $sec2_y = $sec1b_y + 240;
        $sec2   = $this->buildSection2(
            $type, $class, $measurements, $conservation,
            $batchDate, $batchNumber, $operator,
            $cover, $springs, $foam,
            $manufacturer, $ruc, $address, $inen, $website,
            $col1_x, $col2_x, $sec2_y
        );

        // ── SECCIÓN 3: PRINCIPAL CON QR ───────────────────────────────────
        $sec3_y = $sec2_y + 560;
        $sec3   = $this->buildSection3(
            $serial, $productCode, $productName, $type,
            $modelName, $measurements, $qrUrl, $barcode,
            $legalText, $col1_x, $sec3_y, $qrs, $bch
        );

        // ── ENSAMBLE FINAL ZPL ────────────────────────────────────────────
        $zpl = "^XA\n";
        $zpl .= "^PW{$pw}\n";
        $zpl .= "^LL{$ll}\n";
        $zpl .= "^LH0,0\n";
        $zpl .= "^CI28\n";
        $zpl .= $sec1;
        $zpl .= $this->separator($mx, $sec1b_y - 5, $pw - ($mx * 2));
        $zpl .= $sec1b;
        $zpl .= $this->separator($mx, $sec2_y - 5, $pw - ($mx * 2));
        $zpl .= $sec2;
        $zpl .= $this->separator($mx, $sec3_y - 5, $pw - ($mx * 2));
        $zpl .= $sec3;
        $zpl .= "^XZ\n";

        return $zpl;
    }

    protected function buildSection1(
        string $serial, string $productCode,
        string $batchDate, string $batchNumber,
        string $type, string $modelName, string $measurements,
        string $operator,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl  = '';
        $zpl .= "^FO{$col1_x},{$y}^A0N,18,18^FDN: {$serial}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 22) . "^A0N,16,16^FD{$productCode}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 42) . "^A0N,16,16^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 62) . "^A0N,16,16^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$col2_x},{$y}^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 22) . "^A0N,16,16^FD{$type}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 42) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 62) . "^A0N,16,16^FD({$measurements})^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 90) . "^A0N,14,14^FDOperador: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 110) . "^A0N,14,14^FDEnsamble: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 130) . "^A0N,14,14^FDCerrador: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 160) . "^A0N,14,14^FDTrazabilidad^FS\n";

        return $zpl;
    }

    protected function buildSection2(
        string $type, string $class, string $measurements,
        string $conservation,
        string $batchDate, string $batchNumber, string $operator,
        string $cover, string $springs, string $foam,
        string $manufacturer, string $ruc, string $address,
        string $inen, string $website,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl  = '';

        $zpl .= "^FO{$col1_x},{$y}^A0N,20,20^FDInformacion de Composicion^FS\n";

        $y1 = $y + 28;
        $zpl .= "^FO{$col1_x},{$y1}^A0N,16,16^FDTipo IV: {$type}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 20) . "^A0N,16,16^FDClase {$class}: {$measurements}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 40) . "^A0N,14,14^FDCONDICIONES PARA SU CONSERVACION^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 58) . "^A0N,14,14^FD{$conservation}^FS\n";

        $zpl .= "^FO{$col1_x}," . ($y1 + 100) . "^A0N,16,16^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 120) . "^A0N,16,16^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 140) . "^A0N,28,28^FD{$operator}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 172) . "^A0N,14,14^FDOperador^FS\n";

        $zpl .= "^FO{$col2_x},{$y1}^A0N,16,16^FDForro: {$cover}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 22) . "^A0N,16,16^FDResortes: {$springs}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 44) . "^A0N,16,16^FD{$foam}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 76) . "^A0N,18,18^FDHECHO EN ECUADOR^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 100) . "^A0N,13,13^FDFABRICADO POR:^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 116) . "^A0N,13,13^FD{$manufacturer}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 132) . "^A0N,13,13^FDRUC: {$ruc}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 148) . "^A0N,13,13^FD{$address}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 176) . "^A0N,14,14^FD{$inen}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 194) . "^A0N,14,14^FD{$website}^FS\n";

        return $zpl;
    }

    protected function buildSection3(
        string $serial, string $productCode,
        string $productName, string $type,
        string $modelName, string $measurements,
        string $qrUrl, string $barcode,
        string $legalText,
        int $x, int $y, int $qrSize, int $barcodeHeight
    ): string {
        $zpl = '';

        $qr_x      = $x;
        $qr_y      = $y;
        $text_x    = $x + 180;
        $barcode_x = $x;

        $zpl .= "^FO{$qr_x},{$qr_y}^BQN,2,{$qrSize}^FDQA,{$qrUrl}^FS\n";

        if ($this->settings->show_logo) {
            $zpl .= "^FO{$text_x},{$y}^A0N,36,36^FDPARAISO^FS\n";
            $zpl .= "^FO{$text_x}," . ($y + 42) . "^A0N,14,14^FDDONDE EMPIEZAN TUS SUENOS^FS\n";
        }
        $zpl .= "^FO{$text_x}," . ($y + 64) . "^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 84) . "^A0N,20,20^FDN: {$serial}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 108) . "^A0N,18,18^FD{$productCode}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 130) . "^A0N,16,16^FD{$type}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 152) . "^A0N,26,26^FD{$modelName}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 184) . "^A0N,18,18^FD({$measurements})^FS\n";

        $bar_y = $y + 220;
        if (!empty($barcode)) {
            $zpl .= "^FO{$barcode_x},{$bar_y}^BCN,{$barcodeHeight},Y,N,N^FD{$barcode}^FS\n";
        }

        $legal_y = $bar_y + $barcodeHeight + 20;
        $zpl .= "^FO{$x},{$legal_y}^A0N,13,13^FD{$legalText}^FS\n";

        $nodesp_x = $x + $this->settings->width_dots - 30;
        $nodesp_y = $y + 50;
        $zpl .= "^FO{$nodesp_x},{$nodesp_y}^A0R,14,14^FDNO DESPRENDER LA ETIQUETA^FS\n";

        return $zpl;
    }

    protected function separator(int $x, int $y, int $width): string
    {
        return "^FO{$x},{$y}^GB{$width},2,2^FS\n";
    }

    /**
     * Generar ZPL para un batch completo (TODO en memoria).
     *
     * ⚠️ No modifica el estado de las etiquetas ni del batch.
     * Eso lo maneja el caller después de confirmar que la impresión fue exitosa.
     *
     * ⚠️ Para batches > 1000 etiquetas, usar generateForBatchChunkedDownload()
     * o printBatchChunked() para evitar problemas de memoria/timeout.
     */
    public function generateForBatch(LabelBatch $batch): string
    {
        $labels = $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->get();

        $zplFull = '';

        foreach ($labels as $label) {
            $zplFull .= $this->generateForLabel($label);
            $zplFull .= "\n";
        }

        return $zplFull;
    }

    /**
     * Generar ZPL para un conjunto de etiquetas (sin marcar como impresas).
     */
    public function generateForLabels(iterable $labels): string
    {
        $zplFull = '';

        foreach ($labels as $label) {
            $zplFull .= $this->generateForLabel($label);
            $zplFull .= "\n";
        }

        return $zplFull;
    }

    /**
     * Generar ZPL chunked y devolver iterador de archivos.
     *
     * Para batches grandes (5000+), devuelve múltiples archivos ZPL
     * en vez de uno monstruoso. Cada chunk tiene $chunkSize etiquetas.
     *
     * @return \Generator<int, array{filename: string, zpl: string}>
     */
    public function generateForBatchChunked(LabelBatch $batch, int $chunkSize = 500): \Generator
    {
        $chunkIndex = 0;
        $baseFilename = pathinfo($this->getFilenameForBatch($batch), PATHINFO_FILENAME);

        $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->chunk($chunkSize, function ($labels) use (&$chunkIndex, $baseFilename, $batch) {
                $chunkIndex++;
                $zpl = $this->generateForLabels($labels);

                // Marcar como impresas
                Label::whereIn('id', $labels->pluck('id'))
                    ->update(['printed_at' => now()]);

                yield [
                    'filename' => "{$baseFilename}-parte{$chunkIndex}.zpl",
                    'zpl'      => $zpl,
                ];
            });

        // Marcar batch como impreso
        if ($chunkIndex > 0) {
            $batch->update([
                'status'     => 'printed',
                'printed_at' => now(),
            ]);
        }
    }

    public function getFilenameForBatch(LabelBatch $batch): string
    {
        return 'etiquetas-' . $batch->internal_batch_code . '.zpl';
    }

    public function getFilenameForLabel(Label $label): string
    {
        return 'etiqueta-' . $label->serial . '.zpl';
    }

    /**
     * Verificar si un socket TCP timed out.
     */
    protected function isSocketTimedOut($socket): bool
    {
        if (!is_resource($socket)) {
            return true;
        }
        $meta = stream_get_meta_data($socket);

        return $meta['timed_out'] ?? false;
    }
}
