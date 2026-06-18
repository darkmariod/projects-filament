<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\PrintQueueItem;
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
                'dpi'             => 203,
                'label_width_mm'  => 95,
                'label_height_mm' => 200,
                'width_dots'      => 1594,
                'height_dots'     => 760,
                'margin_x'        => 16,
                'margin_y'        => 12,
                'qr_size'         => 5,
                'barcode_height'  => 75,
                'printer_port'    => 9100,
                'chunk_size'      => 500,
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SANITIZACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    public function sanitizeField(string $value, int $maxLength = 100): string
    {
        $value = str_replace(['^', '~', '\\'], ['^^', '~~', '\\\\'], $value);
        $value = preg_replace(
            '/[^\x20-\x7E\x0A\x0D\xC0-\xFF]/u',
            '',
            $value
        );
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength - 3) . '...';
        }
        return $value;
    }

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

    // ─────────────────────────────────────────────────────────────────────────
    //  ESTADO DE LA IMPRESORA VÍA SGD (Standard Gateway Directive)
    // ─────────────────────────────────────────────────────────────────────────
    //
    //  Envía: ! U1 getvar "media.status"
    //  Lee respuesta con timeout 5 segundos
    //
    //  Respuestas:
    //    "ready"        → impresora lista
    //    "out of paper" → sin papel
    //    "head open"    → tapa abierta
    //    "paused"       → impresora en pausa
    //    "" / timeout   → no_response
    // ─────────────────────────────────────────────────────────────────────────

    public const STATUS_READY       = 'ready';
    public const STATUS_PAPER_OUT   = 'out_of_paper';
    public const STATUS_HEAD_OPEN   = 'head_open';
    public const STATUS_PAUSED      = 'paused';
    public const STATUS_OFFLINE     = 'offline';
    public const STATUS_NO_RESPONSE = 'no_response';
    public const STATUS_ERROR       = 'error';

    public const STATUS_MESSAGES = [
        self::STATUS_READY       => 'Impresora lista',
        self::STATUS_PAPER_OUT   => 'La impresora no tiene papel. Cargá papel y la cola se reanudará automáticamente.',
        self::STATUS_HEAD_OPEN   => 'La tapa de la impresora está abierta. Cerrala para continuar.',
        self::STATUS_PAUSED      => 'La impresora está en pausa.',
        self::STATUS_OFFLINE     => 'No se puede conectar con la impresora. Verificá que esté encendida y conectada.',
        self::STATUS_NO_RESPONSE => 'La impresora no respondió al comando de estado.',
        self::STATUS_ERROR       => 'Error desconocido en la impresora.',
    ];

    /**
     * Verificar estado de la Zebra vía comando SGD por socket TCP.
     *
     * Envía: ! U1 getvar "media.status"
     * Lee la respuesta de texto plano.
     *
     * @return string  ready | out_of_paper | head_open | paused | no_response | error
     */
    public function checkPrinterStatus(string $ip, int $port = 9100, int $timeout = 5): string
    {
        try {
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

            if (!$socket) {
                Log::warning('Zebra SGD: no se pudo conectar', [
                    'ip'   => $ip,
                    'port' => $port,
                    'error' => "$errstr ($errno)",
                ]);
                return self::STATUS_OFFLINE;
            }

            stream_set_timeout($socket, $timeout);
            stream_set_blocking($socket, true);

            // Enviar comando SGD
            fwrite($socket, "! U1 getvar \"media.status\"\r\n");

            // Leer respuesta línea por línea
            $response = '';
            $start = microtime(true);

            while (microtime(true) - $start < $timeout) {
                $byte = fread($socket, 1);
                if ($byte === false || $byte === '') {
                    break;
                }
                $response .= $byte;
                // Terminar al encontrar newline
                if ($byte === "\n") {
                    break;
                }
            }

            fclose($socket);

            $response = trim($response);

            if (empty($response)) {
                Log::info('Zebra SGD: sin respuesta (timeout)', [
                    'ip' => $ip, 'port' => $port,
                ]);
                return self::STATUS_NO_RESPONSE;
            }

            Log::info('Zebra SGD: respuesta recibida', [
                'ip'       => $ip,
                'port'     => $port,
                'response' => $response,
            ]);

            return match (true) {
                str_contains($response, 'ready')              => self::STATUS_READY,
                str_contains($response, 'out of paper')       => self::STATUS_PAPER_OUT,
                str_contains($response, 'media out')          => self::STATUS_PAPER_OUT,
                str_contains($response, 'head open')          => self::STATUS_HEAD_OPEN,
                str_contains($response, 'paused')             => self::STATUS_PAUSED,
                default                                       => self::STATUS_ERROR,
            };

        } catch (\Throwable $e) {
            Log::error('Zebra SGD: excepción', [
                'ip'    => $ip,
                'port'  => $port,
                'error' => $e->getMessage(),
            ]);
            return self::STATUS_NO_RESPONSE;
        }
    }

    /**
     * Verificar estado de impresora USB vía lpstat.
     */
    public function checkUsbPrinterStatus(string $printerName): string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: Get-Printer vía PowerShell
                $command = sprintf(
                    'powershell -Command "(Get-Printer -Name \'%s\').PrinterStatus -eq 3" 2>&1',
                    $printerName
                );
                $output = shell_exec($command);
                $output = trim($output ?? '');

                if ($output === 'True') {
                    return self::STATUS_READY;
                }
                if ($output === 'False' || str_contains($output, 'not found')) {
                    return self::STATUS_OFFLINE;
                }

                return self::STATUS_ERROR;
            }

            // Linux/Mac: lpstat
            $command = sprintf('lpstat -p %s 2>&1', escapeshellarg($printerName));
            $output = shell_exec($command);

            if ($output === null || $output === false) {
                return self::STATUS_NO_RESPONSE;
            }

            $output = trim($output);

            if (str_contains($output, 'idle')) {
                return self::STATUS_READY;
            }
            if (str_contains($output, 'disabled') || str_contains($output, 'stopped')) {
                if (str_contains($output, 'paper') || str_contains($output, 'media')) {
                    return self::STATUS_PAPER_OUT;
                }
                return self::STATUS_PAUSED;
            }
            if (str_contains($output, 'not found')) {
                return self::STATUS_OFFLINE;
            }

            return self::STATUS_ERROR;

        } catch (\Throwable $e) {
            Log::error('Zebra USB status: excepción', [
                'printer' => $printerName,
                'error'   => $e->getMessage(),
            ]);
            return self::STATUS_NO_RESPONSE;
        }
    }

    /**
     * Obtener mensaje de error legible para un status.
     */
    public function getStatusMessage(string $status): string
    {
        return self::STATUS_MESSAGES[$status] ?? 'Estado desconocido de la impresora.';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ENVÍO TCP/IP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enviar ZPL por socket TCP/IP.
     */
    public function sendSingleLabel(string $zpl, string $ip, int $port = 9100, int $timeout = 10): array
    {
        // Si la configuración activa indica USB, redirigir
        if ($this->settings->isUsbConfigured()) {
            return $this->sendToUsbPrinter($zpl);
        }

        try {
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "No se pudo conectar a {$ip}:{$port} — {$errstr} ({$errno})",
                ];
            }

            stream_set_timeout($socket, $timeout);
            stream_set_blocking($socket, true);

            $written = fwrite($socket, $zpl, strlen($zpl));

            if ($written === false || $written !== strlen($zpl)) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => 'Error al enviar datos a la impresora',
                ];
            }

            fclose($socket);

            Log::info('Zebra TCP: etiqueta enviada correctamente', [
                'ip'      => $ip,
                'port'    => $port,
                'written' => $written,
            ]);

            return [
                'success' => true,
                'message' => "Etiqueta enviada correctamente a {$ip}:{$port}",
            ];

        } catch (\Throwable $e) {
            Log::error('Zebra TCP: error al enviar', [
                'ip'    => $ip,
                'port'  => $port,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al imprimir: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar ZPL a impresora USB.
     * - Linux/Mac: usa lp (CUPS)
     * - Windows: usa copy /b a ruta UNC del printer share
     */
    public function sendToUsbPrinter(string $zpl): array
    {
        $printerName = $this->settings->printer_name;

        if (empty($printerName)) {
            return [
                'success' => false,
                'message' => 'No hay nombre de impresora USB configurado',
            ];
        }

        try {
            // Escribir ZPL a archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'zpl_');
            file_put_contents($tempFile, $zpl);

            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: copy /b a la impresora compartida
                $command = sprintf(
                    'copy /b %s "\\\\localhost\\%s" > NUL 2>&1',
                    $tempFile,
                    $printerName
                );

                $output = shell_exec($command);
                unlink($tempFile);

                if ($output === null) {
                    // Fallback: intentar escritura directa al path UNC
                    $printerPath = '\\\\localhost\\' . $printerName;
                    $handle = @fopen($printerPath, 'wb');
                    if ($handle) {
                        fwrite($handle, $zpl);
                        fclose($handle);

                        Log::info('Zebra USB Windows: enviado por fopen UNC', [
                            'printer' => $printerName,
                        ]);

                        return [
                            'success' => true,
                            'message' => "Etiqueta enviada a {$printerName} (USB Windows)",
                        ];
                    }

                    return [
                        'success' => false,
                        'message' => 'No se pudo ejecutar el comando. Asegurate de compartir la impresora y que shell_exec esté habilitado.',
                    ];
                }

                Log::info('Zebra USB Windows: etiqueta enviada por copy', [
                    'printer' => $printerName,
                    'output'  => trim($output ?? ''),
                ]);

                return [
                    'success' => true,
                    'message' => "Etiqueta enviada a {$printerName} (USB Windows)",
                ];
            }

            // Linux/Mac: CUPS lp
            $command = sprintf(
                'lp -d %s -o raw %s 2>&1',
                escapeshellarg($printerName),
                escapeshellarg($tempFile)
            );

            $output = shell_exec($command);
            unlink($tempFile);

            if ($output === null) {
                return [
                    'success' => false,
                    'message' => 'No se pudo ejecutar el comando lp. ¿CUPS está instalado?',
                ];
            }

            if (str_contains($output, 'request id')) {
                Log::info('Zebra USB: etiqueta enviada a CUPS', [
                    'printer' => $printerName,
                    'output'  => trim($output),
                ]);

                return [
                    'success' => true,
                    'message' => "Etiqueta enviada a {$printerName} (USB)",
                ];
            }

            Log::warning('Zebra USB: lp devolvió resultado inesperado', [
                'printer' => $printerName,
                'output'  => $output,
            ]);

            return [
                'success' => false,
                'message' => 'Error al enviar a USB: ' . trim($output),
            ];

        } catch (\Throwable $e) {
            Log::error('Zebra USB: excepción al enviar', [
                'printer' => $printerName,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al imprimir por USB: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar ZPL a la impresora configurada (usa settings de la instancia).
     */
    public function sendToConfiguredPrinter(string $zpl): array
    {
        if ($this->settings->isUsbConfigured()) {
            return $this->sendToUsbPrinter($zpl);
        }

        $ip = $this->settings->printer_ip ?? '';
        $port = $this->settings->printer_port ?? 9100;

        if (empty($ip)) {
            Log::error('sendToConfiguredPrinter: IP no configurada', [
                'setting_id' => $this->settings->id,
            ]);
            return [
                'success' => false,
                'message' => 'La impresora no tiene IP configurada',
            ];
        }

        return $this->sendSingleLabel($zpl, $ip, $port);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ENVÍO CON VERIFICACIÓN DE ESTADO (usando SGD)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enviar etiqueta con verificación de estado PRE impresión usando SGD.
     *
     * 1. Consulta estado de la impresora (checkPrinterStatus)
     * 2. Si no está ready → devuelve error específico
     * 3. Envía el ZPL
     *
     * @return array{success: bool, message: string, status?: string}
     */
    public function sendWithSgdCheck(string $zpl, string $ip, int $port = 9100, int $timeout = 10): array
    {
        // ── PRE-CHECK ─────────────────────────────────────────────────────
        $status = $this->checkPrinterStatus($ip, $port);

        if ($status !== self::STATUS_READY) {
            $message = $this->getStatusMessage($status);

            Log::warning('Zebra SGD: pre-check falló', [
                'ip'     => $ip,
                'port'   => $port,
                'status' => $status,
            ]);

            return [
                'success' => false,
                'message' => $message,
                'status'  => $status,
            ];
        }

        // ── ENVIAR ────────────────────────────────────────────────────────
        return $this->sendSingleLabel($zpl, $ip, $port, $timeout);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CONVERSIÓN
    // ─────────────────────────────────────────────────────────────────────────

    public function mmToDots(float $mm): int
    {
        $dotsPerMm = match (true) {
            $this->settings->dpi >= 600 => 24,
            $this->settings->dpi >= 300 => 12,
            default                     => 8,
        };

        return (int) round($mm * $dotsPerMm);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GENERATE FOR LABEL — Layout LANDSCAPE 1594x760 (200x95mm @ 203 DPI)
    // ─────────────────────────────────────────────────────────────────────────
    //
    //  La etiqueta FÍSICA es 95mm × 200mm. Se imprime rotada 90° (landscape).
    //  Por eso en ZPL: PW = 200mm en dots (≈1594), LL = 95mm en dots (≈760).
    //
    //  ┌─────────────────────────────────────────────────────────────────┐
    //  │ ZONA A (x:10-420)           │ ZONA B (x:430-1560)              │
    //  │ Composición técnica         │ Trazabilidad (B1 + B2)            │
    //  ├─────────────────────────────┴───────────────────────────────────┤
    //  │ ZONA C (y:495-750) — QR | Logo | Legal | NO DESPRENDER         │
    //  └─────────────────────────────────────────────────────────────────┘
    // ─────────────────────────────────────────────────────────────────────────

    public function generateForLabel(Label $label): string
    {
        $label->load(['product.productModel', 'product.technicalComposition', 'labelBatch']);

        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        // ── Sanitizar ─────────────────────────────────────────────────────
        $serial        = $this->sanitizeField($label->serial);
        $qrUrl         = $this->sanitizeField($label->qr_url, 200);
        $productCode   = $this->sanitizeField($product->product_code);
        $modelName     = $this->sanitizeField($model->name ?? '');
        $measurements  = $this->sanitizeField($product->measurements_text ?? '');
        $batchNumber   = $this->sanitizeField($batch->customer_batch_number ?? '');
        $batchDate     = $batch->customer_batch_date?->format('d/m/Y') ?? '';
        $operator      = $this->sanitizeField($batch->operator ?? '');
        $type          = $this->sanitizeField($model->type ?? 'Colchón');
        $class         = $this->sanitizeField($product->class ?? ($model->class ?? ''));
        $plazas        = $this->sanitizeField($product->plazas ?? '');
        $barcode       = $this->sanitizeField($label->barcode ?? '', 48);

        $cover        = $this->sanitizeField($composition->cover_material ?? '');
        $foam         = $this->sanitizeField($composition->foam_description ?? '');
        $conservation = $this->sanitizeField($composition->conservation_instructions ?? '');
        $manufacturer = $this->sanitizeField($composition->manufacturer ?? '');
        $ruc          = $this->sanitizeField($composition->manufacturer_ruc ?? '');
        $address      = $this->sanitizeField($composition->manufacturer_address ?? '');
        $inen         = $this->sanitizeField($composition->inen_standard ?? 'NTE INEN 2035');
        $website      = $this->sanitizeField($composition->website ?? '');
        $legalText    = $this->sanitizeField($composition->legal_text ?? '', 400);
        $warrantyText = $model->warranty_years ? $this->sanitizeField("Garantía: {$model->warranty_years} años") : '';
        $frase2       = 'NO DESPRENDER LA ETIQUETA';

        // ── ENSAMBLE ──────────────────────────────────────────────────────
        $zpl  = "^XA\n";
        $zpl .= "^PW1594\n";
        $zpl .= "^LL760\n";
        $zpl .= "^MNW\n";
        $zpl .= "^MTT\n";
        $zpl .= "^MMT\n";
        $zpl .= "^PR4,4\n";
        $zpl .= "^LH0,0\n";
        $zpl .= "^CI28\n";

        // Separadores estructurales
        $zpl .= "^FO425,10^GB2,480,2^FS\n";      // vertical Zona A | Zona B
        $zpl .= "^FO430,250^GB1130,2,2^FS\n";    // horizontal B1 | B2
        $zpl .= "^FO10,493^GB1574,4,4^FS\n";     // grueso horizontal C
        $zpl .= "^FO1530,493^GB2,262,2^FS\n";    // vertical antes de NO DESPRENDER

        $zpl .= $this->buildZonaA(
            $type, $modelName, $class, $measurements, $plazas,
            $conservation, $batchDate, $batchNumber,
            $serial, $operator, $inen, $website,
            $cover, $foam, $manufacturer, $ruc, $address, $warrantyText
        );

        $zpl .= $this->buildZonaB(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $class, $plazas
        );

        $zpl .= $this->buildZonaC(
            $qrUrl, $barcode, $productCode,
            $serial, $productCode, $type, $measurements, $class, $plazas,
            $modelName, $legalText, $frase2
        );

        $zpl .= "^XZ\n";

        return $zpl;
    }

    // ── ZONA A: Composición técnica (x:10-420, y:10-490) ────────────────────

    protected function buildZonaA(
        string $type, string $modelName, string $class, string $measurements,
        string $plazas, string $conservation,
        string $batchDate, string $batchNumber,
        string $serial, string $operator, string $inen, string $website,
        string $cover, string $foam, string $manufacturer, string $ruc,
        string $address, string $warrantyText
    ): string {
        $zpl = '';

        // ── Título ────────────────────────────────────────────────────────
        $zpl .= "^FO10,10^A0N,22,22^FDInformacion de Composicion^FS\n";
        $yl = 38;

        // ═══════════════════════════════════════════════════════════════════
        //  COLUMNA IZQUIERDA (x=10)
        // ═══════════════════════════════════════════════════════════════════

        $x1 = 10;

        $zpl .= "^FO{$x1},{$yl}^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$x1}," . ($yl + 20) . "^A0N,14,14^FD{$class}: {$measurements} {$plazas}^FS\n";
        $zpl .= "^FO{$x1}," . ($yl + 42) . "^A0N,13,13^FDCONDICIONES PARA SU CONSERVACION^FS\n";
        $zpl .= "^FO{$x1}," . ($yl + 60) . "^A0N,13,13^FD{$conservation}^FS\n";

        // Iconos de lavado (texto estilizado)
        $zpl .= "^FO{$x1}," . ($yl + 86) . "^A0N,13,13^FD☒ NO LAVAR  ☒ NO BLANQ  ☒ NO SECAR  ☒ NO PLANCH^FS\n";

        $zpl .= "^FO{$x1}," . ($yl + 112) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$x1}," . ($yl + 132) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";

        // Línea separadora
        $zpl .= "^FO{$x1}," . ($yl + 154) . "^GB150,2,2^FS\n";

        // Serial grande
        $zpl .= "^FO{$x1}," . ($yl + 162) . "^A0N,28,28^FD{$serial}^FS\n";

        // Operador + INEN
        $zpl .= "^FO{$x1}," . ($yl + 198) . "^A0N,13,13^FDOperador: {$operator}    {$inen}^FS\n";

        if (!empty($website)) {
            $zpl .= "^FO{$x1}," . ($yl + 218) . "^A0N,13,13^FD{$website}^FS\n";
        }

        // ═══════════════════════════════════════════════════════════════════
        //  COLUMNA DERECHA (x=220)
        // ═══════════════════════════════════════════════════════════════════

        $x2 = 220;
        $ry = $yl;

        // Forro: split por \n (BUG 2)
        $coverLines = explode("\n", $cover);
        $zpl .= "^FO{$x2},{$ry}^A0N,13,13^FDForro: {$coverLines[0]}^FS\n";
        $ry += 20;
        for ($i = 1; $i < count($coverLines); $i++) {
            $zpl .= "^FO{$x2},{$ry}^A0N,13,13^FD{$coverLines[$i]}^FS\n";
            $ry += 16;
        }

        $zpl .= "^FO{$x2},{$ry}^A0N,13,13^FDEspuma Poliuretano:^FS\n";
        $ry += 20;

        // Foam: split por \n (BUG 2)
        $foamLines = explode("\n", $foam);
        foreach (array_filter($foamLines, fn($l) => trim($l) !== '') as $fl) {
            $zpl .= "^FO{$x2},{$ry}^A0N,13,13^FD" . trim($fl) . "^FS\n";
            $ry += 16;
        }

        $zpl .= "^FO{$x2},{$ry}^A0N,17,17^FDHECHO EN ECUADOR^FS\n";
        $ry += 22;
        $zpl .= "^FO{$x2},{$ry}^A0N,12,12^FDFABRICADO POR:^FS\n";
        $ry += 14;
        $zpl .= "^FO{$x2},{$ry}^A0N,12,12^FD{$manufacturer}^FS\n";
        $ry += 14;

        if (!empty($ruc)) {
            $zpl .= "^FO{$x2},{$ry}^A0N,12,12^FDRUC {$ruc}^FS\n";
            $ry += 14;
        }

        if (!empty($warrantyText)) {
            $zpl .= "^FO{$x2},{$ry}^A0N,12,12^FD{$warrantyText}^FS\n";
            $ry += 14;
        }

        if (!empty($address)) {
            $zpl .= "^FO{$x2},{$ry}^A0N,12,12^FD{$address}^FS\n";
        }

        return $zpl;
    }

    // ── ZONA B: Trazabilidad (x:430-1560, y:10-490) ────────────────────────

    protected function buildZonaB(
        string $serial, string $productCode,
        string $batchDate, string $batchNumber,
        string $type, string $modelName, string $measurements,
        string $class, string $plazas
    ): string {
        $zpl = '';

        // ═══════════════════════════════════════════════════════════════════
        //  BLOQUE B1 (y=10 a y=240)
        // ═══════════════════════════════════════════════════════════════════

        $x  = 435;
        $y1 = 10;

        $zpl .= "^FO{$x},{$y1}^A0N,18,18^FDN°: {$serial}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 24) . "^A0N,14,14^FD{$productCode}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 44) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 64) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 88) . "^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 112) . "^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 132) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 154) . "^A0N,14,14^FD({$measurements}) {$class} {$plazas}^FS\n";
        $zpl .= "^FO{$x}," . ($y1 + 178) . "^A0N,14,14^FDOperador Ensamble: _______________^FS\n";

        // ═══════════════════════════════════════════════════════════════════
        //  BLOQUE B2 (y=260 a y=490)
        // ═══════════════════════════════════════════════════════════════════

        $y2 = 260;

        $zpl .= "^FO{$x},{$y2}^A0N,18,18^FDN°: {$serial}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 24) . "^A0N,14,14^FD{$productCode}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 44) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 64) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 88) . "^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 112) . "^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 132) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 154) . "^A0N,14,14^FD({$measurements}) {$class} {$plazas}^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 178) . "^A0N,14,14^FDCerrador: ____________________^FS\n";
        $zpl .= "^FO{$x}," . ($y2 + 200) . "^A0N,14,14^FDTrazabilidad: ________________^FS\n";

        return $zpl;
    }

    // ── ZONA C: Franja inferior (y:495-750) ────────────────────────────────

    protected function buildZonaC(
        string $qrUrl, string $barcode, string $barcodeProductCode,
        string $serial, string $productCode, string $type,
        string $measurements, string $class, string $plazas,
        string $modelName, string $legalText, string $frase2
    ): string {
        $zpl = '';

        // ═══════════════════════════════════════════════════════════════════
        //  C1 — QR + Barcode (x:15-200)
        // ═══════════════════════════════════════════════════════════════════

        $zpl .= "^FO15,505^BQN,2,4^FDQA,{$qrUrl}^FS\n";

        if (!empty($barcode)) {
            // ^BCN = Code 128 normal orientation, height 60 dots
            $zpl .= "^FO15,650^BCN,60,N,N,N^FD{$barcode}^FS\n";
        }

        $zpl .= "^FO15,715^A0N,14,14^FD{$barcodeProductCode}^FS\n";

        // ═══════════════════════════════════════════════════════════════════
        //  C2 — Logo PARAÍSO + info (x:210-900)
        // ═══════════════════════════════════════════════════════════════════

        $cx = 210;
        $cy = 505;

        $zpl .= "^FO{$cx},{$cy}^A0N,55,55^FDPARAISO^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 60) . "^A0N,14,14^FDDONDE EMPIEZAN TUS SUEÑOS^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 78) . "^GB680,2,2^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 90) . "^A0N,18,18^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 114) . "^A0N,16,16^FDN°: {$serial}^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 134) . "^A0N,14,14^FD{$productCode}^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 152) . "^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 170) . "^A0N,14,14^FD({$measurements}) - {$class} {$plazas}^FS\n";
        $zpl .= "^FO{$cx}," . ($cy + 192) . "^A0N,26,26^FD{$modelName}^FS\n";

        // ═══════════════════════════════════════════════════════════════════
        //  C3 — Legal text (x:905-1520)
        // ═══════════════════════════════════════════════════════════════════

        $lx = 905;
        $ly = 505;

        if (!empty($legalText)) {
            $zpl .= "^FO{$lx},{$ly}^A0N,11,11^FD{$legalText}^FS\n";
        }

        $zpl .= "^FO{$lx}," . ($ly + 170) . "^A0N,11,11^FDEtiqueta elaborada 100% con material reciclado post-consumo^FS\n";
        $zpl .= "^FO{$lx}," . ($ly + 186) . "^A0N,12,12^FDCOMPROMETIDOS CON EL MEDIO AMBIENTE^FS\n";

        // ═══════════════════════════════════════════════════════════════════
        //  C4 — Texto vertical "NO DESPRENDER LA ETIQUETA" (x:1540)
        // ═══════════════════════════════════════════════════════════════════

        $zpl .= "^FO1540,500^A0R,14,14^FD{$frase2}^FS\n";

        return $zpl;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GENERACIÓN PARA COLECCIÓN / BATCH
    // ─────────────────────────────────────────────────────────────────────────

    public function generateForLabels(iterable $labels): string
    {
        $zplFull = '';
        foreach ($labels as $label) {
            $zplFull .= $this->generateForLabel($label);
            $zplFull .= "\n";
        }
        return $zplFull;
    }

    public function generateForBatch(LabelBatch $batch): string
    {
        $labels = $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->get();

        return $this->generateForLabels($labels);
    }

    public function generateForBatchChunked(LabelBatch $batch, int $chunkSize = 500): \Generator
    {
        $chunkIndex = 0;
        $baseFilename = pathinfo($this->getFilenameForBatch($batch), PATHINFO_FILENAME);

        $batch->labels()
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->chunk($chunkSize, function ($labels) use (&$chunkIndex, $baseFilename) {
                $chunkIndex++;
                $zpl = $this->generateForLabels($labels);

                yield [
                    'filename' => "{$baseFilename}-parte{$chunkIndex}.zpl",
                    'zpl'      => $zpl,
                ];
            });
    }

    public function generateZplForItem(Label $label): string
    {
        return $this->generateForLabel($label);
    }

    // ── Filenames ──────────────────────────────────────────────────────────

    public function getFilenameForBatch(LabelBatch $batch): string
    {
        return 'etiquetas-' . $batch->internal_batch_code . '.zpl';
    }

    public function getFilenameForLabel(Label $label): string
    {
        return 'etiqueta-' . $label->serial . '.zpl';
    }
}
