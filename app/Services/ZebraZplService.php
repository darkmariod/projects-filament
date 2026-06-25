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

    protected ZebraZplRenderer $renderer;

    public function __construct(
        ?ZebraPrintSetting $settings = null,
        ?ZebraZplRenderer $renderer = null,
    ) {
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

        $this->renderer = $renderer ?? new ZebraZplRenderer($this->settings);
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
    //  GENERATE FOR LABEL
    //
    //  Layout PORTRAIT 760 × 1600 dots  (95mm × 200mm @ 203 DPI)
    //
    //  ^PW760   = ancho impresión = ancho físico etiqueta (95mm)
    //  ^LL1600  = largo impresión = largo físico etiqueta (200mm)
    //
    //  ┌───────────────────────────────────────────────────────────┐
    //  │  STICKER 1 (Ensamble)              y:15-100               │
    //  ├───────────────────────────────────────────────────────────┤
    //  │  STICKER 2 (Cerrador/Trazabilidad) y:115-210             │
    //  ├───────────────────────────────────────────────────────────┤
    //  │  FILA DE FIRMAS                    y:230                  │
    //  ╞═══════════════════════════════════════════════════════════╡
    //  │  INFORMACION DE COMPOSICION        y:315-530             │
    //  │  col-izq x:15            │  col-der x:400                 │
    //  ╞═══════════════════════════════════════════════════════════╡
    //  │  BLOQUE PRINCIPAL                  y:550+                 │
    //  │  QR+BC x:15  │  PARAISO+info x:400  │  NO DESPR x:735    │
    //  └───────────────────────────────────────────────────────────┘
    // ─────────────────────────────────────────────────────────────────────────

    public function generateForLabel(Label $label): string
    {
        return $this->renderer->render($label);
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
