<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use App\Models\ZebraPrintSetting;

/**
 * ZebraZplRenderer — Generación pura de ZPL para etiquetas portrait 760×1600.
 *
 * Responsabilidad ÚNICA: producir el string ZPL a partir de un Label.
 * Sin lógica de envío, sin estado de impresora, sin IO.
 *
 * Layout portrait (95×200mm @203dpi ≈ 760×1600 dots), de arriba hacia abajo:
 * - Sticker 1: control de calidad (Ensamble).
 * - Sticker 2: control de calidad (Cerrador / Trazabilidad).
 * - Fila de firmas en blanco.
 * - Información de composición técnica (dos columnas).
 * - Bloque principal: QR, código de barras, marca y texto legal.
 */
class ZebraZplRenderer
{
    // ── Layout constants (portrait 760×1600) ────────────────────────────────
    private const WIDTH_DOTS  = 760;
    private const HEIGHT_DOTS = 1600;

    private const MARGIN_X = 15;

    protected ZebraPrintSetting $settings;

    public function __construct(ZebraPrintSetting $settings)
    {
        $this->settings = $settings;
    }

    public function render(Label $label): string
    {
        $label->load(['product.productModel', 'product.technicalComposition', 'labelBatch']);

        $data = $this->extractData($label);
        $zpl  = new ZplBuilder();

        $zpl->header(self::WIDTH_DOTS, self::HEIGHT_DOTS);

        $this->buildQualitySticker($zpl, $data, 15, 'Operador', 'Ensamble');
        $zpl->box(self::MARGIN_X, 100, 730, 2, 2);

        $this->buildQualitySticker($zpl, $data, 115, 'Operador', 'Cerrador', 'Trazabilidad');
        $zpl->box(self::MARGIN_X, 210, 730, 2, 2);

        $this->buildSignatureRow($zpl, 230);
        $zpl->box(10, 295, 740, 4, 4);

        $this->buildComposition($zpl, $data, 315);
        $zpl->box(10, 620, 740, 4, 4);

        $this->buildMainLabel($zpl, $data, 640);

        return $zpl->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  STICKERS (repetidos x2)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildQualitySticker(
        ZplBuilder $zpl,
        array $data,
        int $y,
        string $signatureLabel,
        string $signatureRole,
        ?string $secondSignatureRole = null,
    ): void {
        $col1 = self::MARGIN_X;
        $col2 = 300;
        $col3 = 610;

        $zpl->text($col1, $y, 13, "N°: {$data['serial']}");
        $zpl->text($col1, $y + 22, 12, $data['productCode']);
        $zpl->text($col1, $y + 40, 12, "Fecha: {$data['batchDate']}");
        $zpl->text($col1, $y + 58, 12, "Lote: {$data['lote_nro']}");

        $zpl->text($col2, $y, 14, 'CONTROL DE CALIDAD');
        $zpl->text($col2, $y + 20, 12, "Tipo IV: {$data['type']}");
        $zpl->text($col2, $y + 38, 16, $data['modelName']);
        $zpl->text($col2, $y + 60, 12, "({$data['measurements']}) {$data['class']} {$data['plazas']}");

        $zpl->text($col3, $y, 11, $signatureLabel);
        $zpl->text($col3, $y + 16, 11, $signatureRole);
        $zpl->box($col3, $y + 34, 110, 2, 2);

        if ($secondSignatureRole !== null) {
            $zpl->text($col3, $y + 44, 11, $secondSignatureRole);
            $zpl->box($col3, $y + 62, 110, 2, 2);
        }
    }

    private function buildSignatureRow(ZplBuilder $zpl, int $y): void
    {
        $zpl->box(60, $y, 180, 2, 2);
        $zpl->box(290, $y, 180, 2, 2);
        $zpl->box(520, $y, 180, 2, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  COMPOSICION TECNICA
    // ─────────────────────────────────────────────────────────────────────────

    private function buildComposition(ZplBuilder $zpl, array $data, int $startY): void
    {
        $zpl->text(220, $startY, 18, 'Informacion de Composicion');

        $leftX  = self::MARGIN_X;
        $rightX = 400;
        $y      = $startY + 32;

        // ── Columna izquierda ────────────────────────────────────────────
        $ly = $y;
        $zpl->text($leftX, $ly, 13, "Tipo IV: {$data['type']}");
        $ly += 18;
        $zpl->text($leftX, $ly, 13, "{$data['class']}: {$data['measurements']} {$data['plazas']}");
        $ly += 20;
        $zpl->text($leftX, $ly, 11, 'CONDICIONES PARA SU CONSERVACION');
        $ly += 16;

        foreach (array_slice($this->splitMultiline($data['conservation'] ?? '', 48), 0, 2) as $line) {
            $zpl->text($leftX, $ly, 11, $line);
            $ly += 16;
        }

        // Textile care symbols strip (do not wash / bleach / tumble dry / iron / dry clean)
        $icons = $this->zplGraphic('care-icons.gfa');
        if ($icons !== null) {
            $ly += 4;
            $zpl->raw("^FO{$leftX},{$ly}{$icons}^FS\n");
            $ly += 52;
        }

        $ly += 8;
        $zpl->text($leftX, $ly, 12, "Fecha: {$data['batchDate']}");
        $ly += 16;
        $zpl->text($leftX, $ly, 12, "Lote: {$data['lote_nro']}");
        $ly += 20;
        $zpl->box($leftX, $ly, 150, 2, 2);
        $ly += 10;
        // El serial se escala según su largo para no invadir la columna derecha (x>=400).
        $serialFont = $this->fitFont($data['serial'], 375, 26, 14);
        $zpl->text($leftX, $ly, $serialFont, $data['serial']);
        $ly += $serialFont + 8;
        $zpl->text($leftX, $ly, 11, "Operador: {$data['operator']}   {$data['inen']}");
        $ly += 16;

        if (!empty($data['website'])) {
            $zpl->text($leftX, $ly, 11, $data['website']);
        }

        // ── Columna derecha ──────────────────────────────────────────────
        $ry = $y;
        $coverLines = $this->splitMultiline($data['cover'] ?? '', 30);
        if (!empty($coverLines)) {
            $first = array_shift($coverLines);
            $zpl->text($rightX, $ry, 13, "Forro: {$first}");
            $ry += 18;
            foreach (array_slice($coverLines, 0, 2) as $line) {
                $zpl->text($rightX, $ry, 13, $line);
                $ry += 18;
            }
        }

        if (!empty($data['springs'])) {
            $zpl->text($rightX, $ry, 13, $data['springs']);
            $ry += 18;
        }

        $zpl->text($rightX, $ry, 13, 'Espuma Poliuretano:');
        $ry += 18;

        foreach (array_slice($this->splitMultiline($data['foam'] ?? '', 32), 0, 3) as $line) {
            $zpl->text($rightX, $ry, 11, $line);
            $ry += 16;
        }

        $ry += 4;
        $zpl->text($rightX, $ry, 15, 'HECHO EN ECUADOR');
        $ry += 20;
        $zpl->text($rightX, $ry, 11, 'FABRICADO POR:');
        $ry += 15;
        $zpl->text($rightX, $ry, 11, $data['manufacturer']);
        $ry += 14;

        if (!empty($data['ruc'])) {
            $zpl->text($rightX, $ry, 11, "RUC {$data['ruc']}");
            $ry += 14;
        }

        if (!empty($data['warrantyText'])) {
            $zpl->text($rightX, $ry, 11, $data['warrantyText']);
            $ry += 14;
        }

        if (!empty($data['address'])) {
            foreach (array_slice($this->splitMultiline($data['address'], 34), 0, 2) as $line) {
                $zpl->text($rightX, $ry, 11, $line);
                $ry += 14;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BLOQUE PRINCIPAL (QR + barcode + marca + legal)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildMainLabel(ZplBuilder $zpl, array $data, int $startY): void
    {
        $leftX  = self::MARGIN_X;
        $rightX = 400;

        if (!empty($data['qrUrl'])) {
            // Magnification 6: bigger QR modules scan reliably on thermal print
            $zpl->qrCode($leftX, $startY, 6, $data['qrUrl']);
        }

        $barcodeY = $startY + 250;
        if (!empty($data['barcode'])) {
            $zpl->barcode128($leftX, $barcodeY, 70, $data['barcode'], 1);
        }

        $zpl->text($leftX, $barcodeY + 88, 12, $data['productCode']);

        $ry = $startY;
        $logo = $this->logoZpl();

        if ($logo !== null) {
            $zpl->raw("^FO{$rightX},{$ry}{$logo}^FS\n");
            $ry += 104;
        } else {
            $zpl->text($rightX, $ry, 44, 'PARAISO');
            $ry += 50;
        }

        $zpl->text($rightX, $ry, 11, 'DONDE EMPIEZAN TUS SUEÑOS');
        $ry += 16;
        $zpl->box($rightX, $ry, 440, 2, 2);
        $ry += 12;
        $zpl->text($rightX, $ry, 16, 'CONTROL DE CALIDAD');
        $ry += 22;
        $zpl->text($rightX, $ry, 14, "N°: {$data['serial']}");
        $ry += 18;
        $zpl->text($rightX, $ry, 12, $data['productCode']);
        $ry += 16;
        $zpl->text($rightX, $ry, 12, "Tipo IV: {$data['type']}");
        $ry += 20;
        $zpl->text($rightX, $ry, 26, $data['modelName']);
        $ry += 34;
        $zpl->text($rightX, $ry, 13, "({$data['measurements']}) - {$data['class']} {$data['plazas']}");
        $ry += 30;

        $this->buildLegalText($zpl, $data, $rightX, $ry);

        $zpl->rotatedText(self::WIDTH_DOTS - 25, $startY, 14, 14, 'NO DESPRENDER LA ETIQUETA');
    }

    /**
     * ZPL ^GFA graphic of the Paraiso logo (320x90 dots), pre-generated
     * from the brand PNG. Returns null when the resource is missing so the
     * renderer can fall back to plain text.
     */
    private function logoZpl(): ?string
    {
        return $this->zplGraphic('paraiso-logo.gfa');
    }

    /**
     * Load a pre-generated ^GFA graphic from resources/zpl.
     */
    private function zplGraphic(string $filename): ?string
    {
        $path = resource_path('zpl/' . $filename);

        if (!is_file($path)) {
            return null;
        }

        $data = trim((string) file_get_contents($path));

        return $data !== '' ? $data : null;
    }

    private function buildLegalText(ZplBuilder $zpl, array $data, int $x, int $y): void
    {
        if (!empty($data['legalText'])) {
            foreach (array_slice($this->wordWrap($data['legalText'], 50), 0, 6) as $line) {
                $zpl->text($x, $y, 10, $line);
                $y += 13;
            }
            $y += 8;
        }

        $zpl->text($x, $y, 10, 'Etiqueta elaborada 100% con material reciclado post-consumo');
        $y += 13;
        $zpl->text($x, $y, 11, 'COMPROMETIDOS CON EL MEDIO AMBIENTE');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DATA EXTRACTION / HELPERS
    // ─────────────────────────────────────────────────────────────────────────

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
            'type'          => $this->withoutPrefix($this->sanitize($model->type ?? ''), 'Tipo IV:'),
            'class'         => $this->normalizeClass($this->sanitize($product->class ?? ($model->class ?? ''))),
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
            'legalText'     => $this->sanitize(preg_replace('/\s+/', ' ', $composition->legal_text ?? '') ?? '', 500),
            'warrantyText'  => $model->warranty_years
                ? "Garantía: {$model->warranty_years} años"
                : '',
        ];
    }

    public function sanitize(string $value, int $maxLength = 100): string
    {
        $value = str_replace(['^', '~', '\\'], ['^^', '~~', '\\\\'], $value);

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

    /**
     * Calcula el tamaño de fuente A0 más grande que entra en $maxWidth dots,
     * acotado entre $min y $max. En A0N el ancho de glifo ≈ alto de fuente,
     * así que se aproxima por (maxWidth / nº caracteres).
     */
    private function fitFont(string $text, int $maxWidth, int $max, int $min): int
    {
        $len = max(1, mb_strlen(trim($text)));
        $fit = (int) floor($maxWidth / $len);

        return max($min, min($max, $fit));
    }

    private function normalizeClass(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^Clase\s+/i', $value) === 1) {
            return $value;
        }

        return "Clase {$value}";
    }

    private function withoutPrefix(string $value, string $prefix): string
    {
        return trim(preg_replace('/^' . preg_quote($prefix, '/') . '\\s*/i', '', $value) ?? $value);
    }
}
