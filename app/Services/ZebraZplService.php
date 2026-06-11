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
                'width_dots'      => 760,
                'height_dots'     => 1600,
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
        $value = str_replace(['^', '~', '\\'], ['^^', '~', '\\\\'], $value);
        $value = preg_replace(
            '/[^\x20-\x7E\x0A\x0D\xC0-\xFF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFFñÑáéíóúÁÉÍÓÚüÜ]/u',
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
    //  GENERATE FOR LABEL — Layout exacto 760x1600 (95x200mm @ 203 DPI)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Layout de 4 secciones en 760x1600 dots (95x200mm @ 203 DPI):
     *
     *   SECCIÓN 1A — Trazabilidad (con línea superior)
     *   SEPARADOR
     *   SECCIÓN 1B — Trazabilidad repetida (con 3 líneas de firma)
     *   SEPARADOR GRUESO
     *   SECCIÓN 2 — Composición técnica
     *   SEPARADOR GRUESO
     *   SECCIÓN 3 — Principal (QR + barcode + contenido)
     */
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
        $springs      = $this->sanitizeField($composition->springs ?? '');
        $foam         = $this->sanitizeField($composition->foam_description ?? '');
        $conservation = $this->sanitizeField($composition->conservation_instructions ?? '');
        $manufacturer = $this->sanitizeField($composition->manufacturer ?? '');
        $ruc          = $this->sanitizeField($composition->manufacturer_ruc ?? '');
        $address      = $this->sanitizeField($composition->manufacturer_address ?? '');
        $inen         = $this->sanitizeField($composition->inen_standard ?? 'NTE INEN 2035');
        $website      = $this->sanitizeField($composition->website ?? '');
        $legalText    = $this->sanitizeField($composition->legal_text ?? '');
        $frase2       = 'NO DESPRENDER LA ETIQUETA';

        // ── Constantes de layout ──────────────────────────────────────────
        $pw  = $this->settings->width_dots;   // 760
        $ll  = $this->settings->height_dots;  // 1600
        $mx  = $this->settings->margin_x;     // 16
        $bch = 75; // altura barcode vertical

        $col1_x = $mx;
        $col2_x = (int) round($pw / 2);

        // ── SECCIÓN 1A: TRAZABILIDAD ─────────────────────────────────────
        $y = 12;
        $sec1a = $this->buildSection1A(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $class, $plazas,
            $col1_x, $col2_x, $y
        );
        $end1a = $y + 155;

        $secSep1 = $this->separator($mx, $end1a, $pw - $mx * 2);

        // ── SECCIÓN 1B: TRAZABILIDAD REPETIDA ────────────────────────────
        $y1b = $end1a + 8;
        $sec1b = $this->buildSection1B(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $class, $plazas,
            $col1_x, $col2_x, $y1b
        );
        $end1b = $y1b + 170;

        $sepG1Y = $end1b + 2;
        $secSepG1 = $this->separatorThick($mx, $sepG1Y, $pw - $mx * 2);

        // ── SECCIÓN 2: COMPOSICIÓN TÉCNICA ───────────────────────────────
        $y2 = $sepG1Y + 10;
        $sec2 = $this->buildSection2(
            $type, $class, $measurements, $plazas, $conservation,
            $batchDate, $batchNumber,
            $cover, $springs, $foam,
            $manufacturer, $ruc, $address, $inen, $website, $operator,
            $col1_x, $col2_x, $y2
        );
        $end2 = $y2 + 240;

        $sepG2Y = $end2 + 2;
        $secSepG2 = $this->separatorThick($mx, $sepG2Y, $pw - $mx * 2);

        // ── SECCIÓN 3: PRINCIPAL ─────────────────────────────────────────
        $y3 = $sepG2Y + 10;
        $sec3 = $this->buildSection3(
            $serial, $productCode, $type,
            $modelName, $measurements, $class, $plazas,
            $qrUrl, $barcode, $legalText, $frase2,
            $mx, $y3, $bch
        );

        // ── ENSAMBLE FINAL ───────────────────────────────────────────────
        $zpl  = "^XA\n";
        $zpl .= "^PW{$pw}\n";
        $zpl .= "^LL{$ll}\n";
        $zpl .= "^LH0,0\n";
        $zpl .= "^CI28\n";
        $zpl .= "^MMT\n";
        $zpl .= $sec1a;
        $zpl .= $secSep1;
        $zpl .= $sec1b;
        $zpl .= $secSepG1;
        $zpl .= $sec2;
        $zpl .= $secSepG2;
        $zpl .= $sec3;
        $zpl .= "^XZ\n";

        return $zpl;
    }

    // ── SECCIÓN 1A ──────────────────────────────────────────────────────────

    protected function buildSection1A(
        string $serial, string $productCode,
        string $batchDate, string $batchNumber,
        string $type, string $modelName, string $measurements,
        string $class, string $plazas,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl = '';

        // Línea superior
        $zpl .= "^FO{$col1_x},{$y}^A0N,18,18^FDLote: {$batchNumber} ({$measurements}) {$class} {$plazas}^FS\n";

        $yl = $y + 25;

        // Col izq
        $zpl .= "^FO{$col1_x},{$yl}^A0N,16,16^FDN° {$serial}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 22) . "^A0N,14,14^FD{$productCode}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 42) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 62) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";

        // Col der
        $zpl .= "^FO{$col2_x},{$yl}^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 22) . "^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 42) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 64) . "^A0N,14,14^FD({$measurements}) {$class} {$plazas}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 90) . "^A0N,14,14^FDOperador Ensamble ________________^FS\n";

        return $zpl;
    }

    // ── SECCIÓN 1B ──────────────────────────────────────────────────────────

    protected function buildSection1B(
        string $serial, string $productCode,
        string $batchDate, string $batchNumber,
        string $type, string $modelName, string $measurements,
        string $class, string $plazas,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl = '';

        $yl = $y;

        // Col izq
        $zpl .= "^FO{$col1_x},{$yl}^A0N,16,16^FDN° {$serial}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 22) . "^A0N,14,14^FD{$productCode}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 42) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 62) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";

        // Col der
        $zpl .= "^FO{$col2_x},{$yl}^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 22) . "^A0N,14,14^FD{$type}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 42) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 64) . "^A0N,14,14^FD({$measurements}) {$class} {$plazas}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 90) . "^A0N,14,14^FDCerrador ____________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 110) . "^A0N,14,14^FDTrazabilidad ________________^FS\n";

        // 3 líneas de firma
        $firmY = $yl + 135;
        $firmWidth = 160;
        $zpl .= "^FO{$col1_x},{$firmY}^GB{$firmWidth},2,2^FS\n";
        $zpl .= "^FO{$col1_x}," . ($firmY + 8) . "^GB{$firmWidth},2,2^FS\n";
        $zpl .= "^FO{$col1_x}," . ($firmY + 16) . "^GB{$firmWidth},2,2^FS\n";

        return $zpl;
    }

    // ── SECCIÓN 2 ───────────────────────────────────────────────────────────

    protected function buildSection2(
        string $type, string $class, string $measurements,
        string $plazas, string $conservation,
        string $batchDate, string $batchNumber,
        string $cover, string $springs, string $foam,
        string $manufacturer, string $ruc, string $address,
        string $inen, string $website, string $operator,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl = '';

        // Título centrado
        $zpl .= "^FO{$col1_x},{$y}^A0N,20,20^FDInformacion de Composicion^FS\n";

        $yl = $y + 28;

        // Col izq
        $zpl .= "^FO{$col1_x},{$yl}^A0N,14,14^FDTipo IV: {$type}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 20) . "^A0N,14,14^FDClase {$class}: {$measurements} {$plazas}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 42) . "^A0N,13,13^FDCONDICIONES CONSERVACION^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 58) . "^A0N,13,13^FD{$conservation}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 78) . "^A0N,13,13^FD[X][^][X][=][X]^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 100) . "^A0N,14,14^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 120) . "^A0N,14,14^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 144) . "^GB100,2,2^FS\n";
        $zpl .= "^FO{$col1_x}," . ($yl + 148) . "^A0N,13,13^FDOperador ________________^FS\n";

        // Col der
        $zpl .= "^FO{$col2_x},{$yl}^A0N,14,14^FDForro: {$cover}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 20) . "^A0N,14,14^FDResortes: {$springs}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 42) . "^A0N,14,14^FDEspuma Poliuretano:^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 60) . "^A0N,14,14^FD{$foam}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 90) . "^A0N,17,17^FDHECHO EN ECUADOR^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 112) . "^A0N,12,12^FDFABRICADO POR:^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 126) . "^A0N,12,12^FD{$manufacturer}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 140) . "^A0N,12,12^FDRUC {$ruc}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($yl + 154) . "^A0N,12,12^FD{$address}^FS\n";

        // Footer
        $fy = $yl + 178;
        $zpl .= "^FO{$col1_x},{$fy}^A0N,13,13^FDOperador {$operator}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($fy + 18) . "^A0N,13,13^FD{$inen}^FS\n";
        $zpl .= "^FO{$col2_x},{$fy}^A0N,13,13^FD{$website}^FS\n";

        return $zpl;
    }

    // ── SECCIÓN 3 ───────────────────────────────────────────────────────────

    protected function buildSection3(
        string $serial, string $productCode,
        string $type, string $modelName, string $measurements,
        string $class, string $plazas, string $qrUrl, string $barcode,
        string $legalText, string $frase2,
        int $x, int $y, int $bch
    ): string {
        $zpl = '';

        // QR 25x25mm (magnification 5 ≈ 200 dots)
        $qrX = $x + 5;
        $qrY = $y;
        $zpl .= "^FO{$qrX},{$qrY}^BQN,2,5^FDQA,{$qrUrl}^FS\n";

        // Barcode vertical debajo del QR
        $bcX = $qrX + 5;
        $bcY = $qrY + 200;
        if (!empty($barcode)) {
            $zpl .= "^FO{$bcX},{$bcY}^BCR,{$bch},Y,N,N^FD{$barcode}^FS\n";
        }

        // Columna centro-derecha
        $tx = (int) round($this->settings->width_dots * 0.3);
        $tl = $y;

        $zpl .= "^FO{$tx},{$tl}^A0N,38,38^FDPARAISO^FS\n";
        $tl += 44;
        $zpl .= "^FO{$tx},{$tl}^A0N,14,14^FDDONDE EMPIEZAN TUS SUEÑOS^FS\n";
        $tl += 20;
        $zpl .= "^FO{$tx},{$tl}^GB300,2,2^FS\n";
        $tl += 10;
        $zpl .= "^FO{$tx},{$tl}^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $tl += 22;
        $zpl .= "^FO{$tx},{$tl}^A0N,18,18^FDN°: {$serial}^FS\n";
        $tl += 22;
        $zpl .= "^FO{$tx},{$tl}^A0N,14,14^FD{$productCode}^FS\n";
        $tl += 18;
        $zpl .= "^FO{$tx},{$tl}^A0N,14,14^FD{$type}^FS\n";
        $tl += 18;
        $zpl .= "^FO{$tx},{$tl}^A0N,14,14^FD({$measurements}) - {$class} {$plazas}^FS\n";
        $tl += 20;
        $zpl .= "^FO{$tx},{$tl}^A0N,28,28^FD{$modelName}^FS\n";
        $tl += 34;
        $zpl .= "^FO{$tx},{$tl}^GB300,2,2^FS\n";
        $tl += 10;
        if (!empty($legalText)) {
            $zpl .= "^FO{$tx},{$tl}^A0N,12,12^FD{$legalText}^FS\n";
            $tl += 18;
        }
        $zpl .= "^FO{$tx},{$tl}^A0N,12,12^FDEtiqueta elaborada 100% con material reciclado post-consumo^FS\n";
        $tl += 16;
        $zpl .= "^FO{$tx},{$tl}^A0N,13,13^FDCOMPROMETIDOS CON EL MEDIO AMBIENTE^FS\n";

        // Texto vertical borde derecho
        $vx = $this->settings->width_dots - 30;
        $zpl .= "^FO{$vx}," . ($y + 80) . "^A0R,14,14^FD{$frase2}^FS\n";

        return $zpl;
    }

    // ── UTILIDADES ──────────────────────────────────────────────────────────

    protected function separator(int $x, int $y, int $width): string
    {
        return "^FO{$x},{$y}^GB{$width},2,2^FS\n";
    }

    protected function separatorThick(int $x, int $y, int $width): string
    {
        return "^FO{$x},{$y}^GB{$width},6,6^FS\n";
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
