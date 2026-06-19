<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use App\Models\ZebraPrintSetting;

/**
 * ZebraZplRenderer — Generación pura de ZPL para etiquetas portrait 760×1594.
 *
 * Responsabilidad ÚNICA: producir el string ZPL a partir de un Label.
 * Sin lógica de envío, sin estado de impresora, sin IO.
 *
 * Layout Portrait (95×200mm @203dpi = 760×1594 dots):
 *
 *   ┌──────────────────────────────────┐ y:0
 *   │  STICKER 1A — Trazabilidad      │ y:10  — Operador Ensamble
 *   ├──────────────────────────────────┤ y:194 (separador 2px)
 *   │  STICKER 1B — Trazabilidad      │ y:200 — Cerrador + Trazabilidad
 *   ╞══════════════════════════════════╡ y:400 (separador 4px)
 *   │  SECCIÓN 2 — Composición        │ y:408
 *   │  ┌──────────┬────────────────┐  │
 *   │  │ izq x:10 │ der x:386      │  │
 *   │  └──────────┴────────────────┘  │
 *   ╞══════════════════════════════════╡ y:754 (separador 4px)
 *   │  SECCIÓN 3 — Principal         │ y:762
 *   │  QR + BC + PARAISO + legal      │
 *   └──────────────────────────────────┘ y:~1570
 */
class ZebraZplRenderer
{
    // ── Layout constants (portrait 760×1594) ────────────────────────────────
    private const WIDTH_DOTS  = 760;
    private const HEIGHT_DOTS = 1594;

    // Stickers
    private const S1_X       = 10;
    private const S1A_Y      = 10;
    private const S1B_Y      = 200;
    private const SEP_FINE_Y = 194;
    private const SEP_THICK1 = 400;   // entre sticker 1B y sección 2

    // Sección 2 — Composición
    private const S2_TITLE_Y  = 408;
    private const S2_LINE_Y   = 432;
    private const S2_COL_LX   = 10;
    private const S2_COL_RX   = 386;
    private const S2_COL_SY   = 438;
    private const S2_VLINE_X  = 380;
    private const S2_VLINE_Y  = 436;
    private const S2_VLINE_H  = 310;
    private const SEP_THICK2  = 754;

    // Sección 3 — Principal
    private const S3_Y          = 762;
    private const S3_QR_X       = 10;
    private const S3_QR_Y       = 768;
    private const S3_QR_MAG     = 5;
    private const S3_BC_Y       = 940;
    private const S3_BC_HEIGHT  = 70;
    private const S3_PROD_Y     = 1020;
    private const S3_PARA_X     = 175;
    private const S3_PARA_Y     = 768;
    private const S3_SEP_Y      = 1040;
    private const S3_LEGAL_X    = 10;
    private const S3_LEGAL_Y    = 1050;
    private const S3_MAX_LINE   = 60;       // chars por línea legal text

    // ── Estado ──────────────────────────────────────────────────────────────
    protected ZebraPrintSetting $settings;

    public function __construct(ZebraPrintSetting $settings)
    {
        $this->settings = $settings;
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═════════════════════════════════════════════════════════════════════════

    public function render(Label $label): string
    {
        $label->load(['product.productModel', 'product.technicalComposition', 'labelBatch']);

        $data = $this->extractData($label);
        $zpl  = new ZplBuilder();

        $zpl->header(self::WIDTH_DOTS, self::HEIGHT_DOTS);

        // ── Separadores estructurales ──────────────────────────────────────
        $zpl->line(10, self::SEP_FINE_Y, 740, 2);                          // entre 1A y 1B
        $zpl->line(10, self::SEP_THICK1, 740, 4);                          // entre 1B y S2
        $zpl->line(10, self::SEP_THICK2, 740, 4);                          // entre S2 y S3

        // ── Secciones ──────────────────────────────────────────────────────
        $this->buildSticker($zpl, $data, self::S1A_Y, true, [
            ['label' => 'Operador Ensamble: ', 'lineX' => 220, 'lineW' => 280],
        ]);

        $this->buildSticker($zpl, $data, self::S1B_Y, false, [
            ['label' => 'Cerrador: ',        'lineX' => 120, 'lineW' => 280],
            ['label' => 'Trazabilidad: ',     'lineX' => 150, 'lineW' => 280],
        ]);

        $this->buildComposicion($zpl, $data);
        $this->buildPrincipal($zpl, $data);

        return $zpl->close();
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  STICKER 1A / 1B  —  Trazabilidad (unificada)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Construye un sticker de trazabilidad.
     *
     * @param  array  $data        Datos extraídos del label
     * @param  int    $startY      Y inicial
     * @param  bool   $showHeader  Si muestra línea "Lote: ..." al inicio
     * @param  array  $signatures  [$sig] cada sig: ['label'=>string, 'lineX'=>int, 'lineW'=>int]
     */
    private function buildSticker(ZplBuilder $zpl, array $data, int $startY, bool $showHeader, array $signatures): void
    {
        $x = self::S1_X;
        $y = $startY;

        if ($showHeader) {
            $zpl->text($x, $y, 16,
                "Lote: {$data['lote_nro']} ({$data['measurements']}) {$data['class']} {$data['plazas']}"
            );
            $y += 22;
        }

        $zpl->text($x, $y, 18, "N°: {$data['serial']}");         $y += 24;
        $zpl->text($x, $y, 14, $data['productCode']);            $y += 18;
        $zpl->text($x, $y, 14, "Fecha: {$data['batchDate']}");   $y += 18;
        $zpl->text($x, $y, 14, "Lote: {$data['lote_nro']}");     $y += 22;
        $zpl->text($x, $y, 17, 'CONTROL DE CALIDAD');            $y += 22;
        $zpl->text($x, $y, 14, $data['type']);                   $y += 18;
        $zpl->text($x, $y, 19, $data['modelName']);              $y += 24;
        $zpl->text($x, $y, 14, "({$data['measurements']}) {$data['class']} {$data['plazas']}");
        $y += 22;

        foreach ($signatures as $sig) {
            $zpl->text($x, $y, 14, $sig['label']);
            $zpl->line($sig['lineX'], $y, $sig['lineW'], 2);
            $y += 26; // 14 (font) + 4 gap + 2 (line) + 6 padding
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  SECCIÓN 2  —  Información de Composición  (2 columnas)
    // ═════════════════════════════════════════════════════════════════════════

    private function buildComposicion(ZplBuilder $zpl, array $data): void
    {
        $zpl->text(self::S2_COL_LX, self::S2_TITLE_Y + 2, 20, 'Informacion de Composicion');
        $zpl->line(self::S2_COL_LX, self::S2_LINE_Y, 740, 1);

        // Separador vertical entre columnas
        $zpl->line(self::S2_VLINE_X, self::S2_VLINE_Y, 2, self::S2_VLINE_H);

        $this->buildColIzquierda($zpl, $data);
        $this->buildColDerecha($zpl, $data);
    }

    private function buildColIzquierda(ZplBuilder $zpl, array $data): void
    {
        $x = self::S2_COL_LX;
        $y = self::S2_COL_SY;

        $zpl->text($x, $y, 14, $data['type']);                           $y += 18;
        $zpl->text($x, $y, 14, "{$data['class']}: {$data['measurements']} {$data['plazas']}"); $y += 18;
        $zpl->text($x, $y, 13, 'CONDICIONES CONSERVACION');               $y += 16;

        if (!empty($data['conservation'])) {
            $zpl->text($x, $y, 13, $data['conservation']);                $y += 16;
        }

        $zpl->text($x, $y, 14, "Fecha: {$data['batchDate']}");            $y += 18;
        $zpl->text($x, $y, 14, "Lote: {$data['lote_nro']}");              $y += 20;
        $zpl->line($x, $y, 150, 2);                                        $y += 8;
        $zpl->text($x, $y, 28, $data['serial']);                          $y += 34;
        $zpl->text($x, $y, 13, 'Operador: ');                              // label
        $zpl->line($x + 120, $y, 150, 2);                                  $y += 18;
        $zpl->text($x, $y, 13, "{$data['operator']}   {$data['inen']}");  $y += 17;

        if (!empty($data['website'])) {
            $zpl->text($x, $y, 12, $data['website']);
        }
    }

    private function buildColDerecha(ZplBuilder $zpl, array $data): void
    {
        $x = self::S2_COL_RX;
        $y = self::S2_COL_SY;

        // ── Forro (cover multilínea) ──────────────────────────────────────
        $coverLines = $this->splitMultiline($data['cover'] ?? '', 35);
        if (!empty($coverLines)) {
            foreach (array_slice($coverLines, 0, 2) as $line) {
                $zpl->text($x, $y, 14, "Forro: {$line}");                    $y += 17;
            }
        } else {
            $zpl->text($x, $y, 14, "Forro: {$data['cover']}");              $y += 17;
        }

        // ── Springs / Resortes ────────────────────────────────────────────
        if (!empty($data['springs'])) {
            $zpl->text($x, $y, 14, $data['springs']);                        $y += 17;
        }

        // ── Espuma (foam multilínea) ──────────────────────────────────────
        $zpl->text($x, $y, 14, 'Espuma Poliuretano:');                       $y += 18;

        $foamLines = $this->splitMultiline($data['foam'] ?? '', 35);
        foreach (array_slice($foamLines, 0, 4) as $line) {
            $zpl->text($x, $y, 13, $line);                                    $y += 16;
        }

        // ── Hecho en Ecuador ──────────────────────────────────────────────
        $y += 4;
        $zpl->text($x, $y, 17, 'HECHO EN ECUADOR');                          $y += 22;
        $zpl->text($x, $y, 12, 'FABRICADO POR:');                             $y += 16;
        $zpl->text($x, $y, 12, $data['manufacturer']);                        $y += 16;

        if (!empty($data['ruc'])) {
            $zpl->text($x, $y, 12, "RUC {$data['ruc']}");                     $y += 16;
        }

        if (!empty($data['warrantyText'])) {
            $zpl->text($x, $y, 12, $data['warrantyText']);                    $y += 16;
        }

        if (!empty($data['address'])) {
            $addrLines = $this->splitMultiline($data['address'], 30);
            foreach (array_slice($addrLines, 0, 2) as $line) {
                $zpl->text($x, $y, 11, $line);                                $y += 14;
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  SECCIÓN 3  —  QR + Barcode + PARAISO + Legal
    // ═════════════════════════════════════════════════════════════════════════

    private function buildPrincipal(ZplBuilder $zpl, array $data): void
    {
        // ── QR Code ──────────────────────────────────────────────────────
        if (!empty($data['qrUrl'])) {
            $zpl->qrCode(self::S3_QR_X, self::S3_QR_Y, self::S3_QR_MAG, $data['qrUrl']);
        }

        // ── Barcode Code128 ──────────────────────────────────────────────
        if (!empty($data['barcode'])) {
            $zpl->barcode128(self::S3_QR_X, self::S3_BC_Y, self::S3_BC_HEIGHT, $data['barcode']);
        }
        $zpl->text(self::S3_QR_X, self::S3_PROD_Y, 14, $data['productCode']);

        // ── PARAISO + info ───────────────────────────────────────────────
        $cx = self::S3_PARA_X;
        $cy = self::S3_PARA_Y;

        $zpl->text($cx, $cy, 55, 'PARAISO');
        $zpl->text($cx, $cy + 58, 14, 'DONDE EMPIEZAN TUS SUEÑOS');
        $zpl->line($cx, $cy + 78, 560, 2);
        $zpl->text($cx, $cy + 88, 18, 'CONTROL DE CALIDAD');
        $zpl->text($cx, $cy + 112, 16, "N°: {$data['serial']}");
        $zpl->text($cx, $cy + 132, 14, $data['productCode']);
        $zpl->text($cx, $cy + 150, 14, $data['type']);
        $zpl->text($cx, $cy + 168, 14, "({$data['measurements']}) - {$data['class']} {$data['plazas']}");
        $zpl->text($cx, $cy + 194, 26, $data['modelName']);

        // ── Separador antes del texto legal ──────────────────────────────
        $zpl->line(10, self::S3_SEP_Y, 740, 2);

        // ── Legal text (word-wrapped) ────────────────────────────────────
        $ly = self::S3_LEGAL_Y;
        $lx = self::S3_LEGAL_X;

        if (!empty($data['legalText'])) {
            foreach ($this->wordWrap($data['legalText'], self::S3_MAX_LINE) as $line) {
                $zpl->text($lx, $ly, 11, $line);
                $ly += 14;
                if ($ly > self::S3_LEGAL_Y + 100) break;
            }
            $ly += 4;
        }

        $zpl->text($lx, $ly, 11, 'Etiqueta elaborada 100% con material reciclado post-consumo'); $ly += 15;
        $zpl->text($lx, $ly, 12, 'COMPROMETIDOS CON EL MEDIO AMBIENTE');                         $ly += 18;
        $zpl->text($lx, $ly, 14, 'NO DESPRENDER LA ETIQUETA');
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  DATA EXTRACTION + SANITIZATION
    // ═════════════════════════════════════════════════════════════════════════

    private function extractData(Label $label): array
    {
        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        return [
            'serial'        => $this->sanitize($label->serial),
            'qrUrl'         => $this->sanitize($label->qr_url ?? '', 200),
            'productCode'   => $this->sanitize($product->product_code ?? ''),
            'modelName'     => $this->sanitize($model->name ?? ''),
            'measurements'  => $this->sanitize($product->measurements_text ?? ''),
            'lote_nro'      => $this->sanitize($batch->customer_batch_number ?? ''),
            'batchDate'     => $batch->customer_batch_date?->format('d/m/Y') ?? '',
            'operator'      => $this->sanitize($batch->operator ?? ''),
            'type'          => $this->sanitize($model->type ?? ''),
            'class'         => $this->sanitize($product->class ?? ($model->class ?? '')),
            'plazas'        => $this->sanitize($product->plazas ?? ''),
            'barcode'       => $this->sanitize($label->barcode ?? '', 48),
            'cover'         => $composition->cover_material ?? '',
            'foam'          => $composition->foam_description ?? '',
            'springs'       => $composition->springs ?? '',
            'conservation'  => $this->sanitize($composition->conservation_instructions ?? ''),
            'manufacturer'  => $this->sanitize($composition->manufacturer ?? ''),
            'ruc'           => $this->sanitize($composition->manufacturer_ruc ?? ''),
            'address'       => $this->sanitize($composition->manufacturer_address ?? ''),
            'inen'          => $this->sanitize($composition->inen_standard ?? 'NTE INEN 2035'),
            'website'       => $this->sanitize($composition->website ?? ''),
            'legalText'     => $this->sanitize($composition->legal_text ?? '', 400),
            'warrantyText'  => $model->warranty_years
                ? "Garantía: {$model->warranty_years} años"
                : '',
        ];
    }

    /**
     * Sanitizar un valor para ZPL.
     *
     * - Escapa ^, ~, \ (caracteres de control ZPL)
     * - Filtra caracteres no imprimibles/no latinos
     * - Trunca con "..." si excede maxLength
     */
    public function sanitize(string $value, int $maxLength = 100): string
    {
        // Escapar controles ZPL
        $value = str_replace(['^', '~', '\\'], ['^^', '~~', '\\\\'], $value);

        // Permitir: imprimibles ASCII (0x20-0x7E), extendido latino (0xC0-0xFF),
        // ñÑáéíóúÁÉÍÓÚüÜàèìòùÀÈÌÒÙ
        $value = preg_replace(
            '/[^\x20-\x7E\xC0-\xFFñÑáéíóúÁÉÍÓÚüÜàèìòùÀÈÌÒÙ]/u',
            '',
            $value
        );

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength - 3) . '...';
        }

        return trim($value);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Dividir un texto multilínea en un array de líneas.
     *
     * 1. Split por \n
     * 2. Si es 1 línea, split por doble espacio o " - " seguido de dígito
     */
    private function splitMultiline(string $value, int $maxLen = 35): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $value = str_replace(['\\n', '\n'], "\n", $value);
        $parts = preg_split('/\n|\r\n|\r/', $value);

        if (count($parts) <= 1) {
            $parts = preg_split('/\s{2,}|\s+-\s+(?=\d)/', $value);
        }

        $lines = [];
        foreach ($parts as $part) {
            $part = trim($this->sanitize($part, $maxLen));
            if ($part !== '') {
                $lines[] = $part;
            }
        }

        return $lines ?: [trim($this->sanitize($value, $maxLen))];
    }

    /**
     * Word-wrap para texto legal (UTF‑8 safe).
     */
    private function wordWrap(string $text, int $maxChars): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $words  = explode(' ', $text);
        $lines  = [];
        $line   = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : "{$line} {$word}";
            if (mb_strlen($candidate) > $maxChars) {
                if ($line !== '') {
                    $lines[] = $line;
                }
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }
}
