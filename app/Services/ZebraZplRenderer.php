<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use App\Models\ZebraPrintSetting;

/**
 * ZebraZplRenderer — Generación pura de ZPL para etiquetas landscape 1594×760.
 *
 * Responsabilidad ÚNICA: producir el string ZPL a partir de un Label.
 * Sin lógica de envío, sin estado de impresora, sin IO.
 *
 * Layout landscape (200×95mm @203dpi ≈ 1594×760 dots):
 * - Bloque izquierdo superior: composición.
 * - Bloque derecho superior: dos stickers de control de calidad.
 * - Bloque inferior: QR, código de barras, marca, datos principales y texto legal.
 */
class ZebraZplRenderer
{
    // ── Layout constants (landscape 1594×760) ───────────────────────────────
    private const WIDTH_DOTS  = 1594;
    private const HEIGHT_DOTS = 760;

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

        // Structural separators copied from the physical 200×95mm landscape label.
        $zpl->box(425, 10, 2, 480, 2);
        $zpl->box(430, 250, 1130, 2, 2);
        $zpl->box(10, 493, 1574, 4, 4);
        $zpl->box(1530, 493, 2, 262, 2);

        $this->buildComposition($zpl, $data);
        $this->buildQualitySticker($zpl, $data, 10, true, 'Operador Ensamble: _______________');
        $this->buildQualitySticker($zpl, $data, 260, false, 'Cerrador: ____________________', 'Trazabilidad: ________________');
        $this->buildMainLabel($zpl, $data);

        return $zpl->close();
    }

    private function buildComposition(ZplBuilder $zpl, array $data): void
    {
        $zpl->text(10, 10, 22, 'Informacion de Composicion');
        $zpl->text(10, 38, 14, "Tipo IV: {$data['type']}");
        $zpl->text(10, 58, 14, "{$data['class']}: {$data['measurements']} {$data['plazas']}");
        $zpl->text(10, 80, 13, 'CONDICIONES PARA SU CONSERVACION');

        $y = 98;
        foreach (array_slice($this->splitMultiline($data['conservation'] ?? '', 52), 0, 2) as $line) {
            $zpl->text(10, $y, 13, $line);
            $y += 18;
        }

        $zpl->text(10, 150, 14, "Fecha: {$data['batchDate']}");
        $zpl->text(10, 170, 14, "Lote: {$data['lote_nro']}");
        $zpl->box(10, 192, 150, 2, 2);
        $zpl->text(10, 200, 28, $data['serial']);
        $zpl->text(10, 236, 13, "Operador: {$data['operator']}    {$data['inen']}");

        if (!empty($data['website'])) {
            $zpl->text(10, 256, 13, $data['website']);
        }

        $this->buildCompositionRightColumn($zpl, $data);
    }

    private function buildCompositionRightColumn(ZplBuilder $zpl, array $data): void
    {
        $x = 220;
        $y = 38;

        $coverLines = $this->splitMultiline($data['cover'] ?? '', 34);
        if (!empty($coverLines)) {
            $first = array_shift($coverLines);
            $zpl->text($x, $y, 14, "Forro: {$first}");
            $y += 20;
            foreach (array_slice($coverLines, 0, 2) as $line) {
                $zpl->text($x, $y, 14, $line);
                $y += 20;
            }
        }

        if (!empty($data['springs'])) {
            $zpl->text($x, $y, 14, $data['springs']);
            $y += 20;
        }

        $zpl->text($x, $y, 14, 'Espuma Poliuretano:');
        $y += 20;

        foreach (array_slice($this->splitMultiline($data['foam'] ?? '', 35), 0, 3) as $line) {
            $zpl->text($x, $y, 13, $line);
            $y += 18;
        }

        $y += 2;
        $zpl->text($x, $y, 17, 'HECHO EN ECUADOR');
        $y += 22;
        $zpl->text($x, $y, 12, 'FABRICADO POR:');
        $y += 16;
        $zpl->text($x, $y, 12, $data['manufacturer']);
        $y += 14;

        if (!empty($data['ruc'])) {
            $zpl->text($x, $y, 12, "RUC {$data['ruc']}");
            $y += 14;
        }

        if (!empty($data['warrantyText'])) {
            $zpl->text($x, $y, 12, $data['warrantyText']);
            $y += 14;
        }

        if (!empty($data['address'])) {
            foreach (array_slice($this->splitMultiline($data['address'], 36), 0, 2) as $line) {
                $zpl->text($x, $y, 12, $line);
                $y += 14;
            }
        }
    }

    private function buildQualitySticker(
        ZplBuilder $zpl,
        array $data,
        int $startY,
        bool $firstSticker,
        string $signatureLine,
        ?string $secondSignatureLine = null,
    ): void {
        $x = 435;
        $y = $startY;

        $zpl->text($x, $y, 18, "N°: {$data['serial']}");
        $zpl->text($x, $y + 24, 14, $data['productCode']);
        $zpl->text($x, $y + 44, 14, "Fecha: {$data['batchDate']}");
        $zpl->text($x, $y + 64, 14, "Lote: {$data['lote_nro']}");
        $zpl->text($x, $y + 88, 16, 'CONTROL DE CALIDAD');
        $zpl->text($x, $y + 112, 14, "Tipo IV: {$data['type']}");
        $zpl->text($x, $y + 132, 18, $data['modelName']);
        $zpl->text($x, $y + 154, 14, "({$data['measurements']}) {$data['class']} {$data['plazas']}");
        $zpl->text($x, $y + 178, 14, $signatureLine);

        if ($secondSignatureLine !== null) {
            $zpl->text($x, $y + 200, 14, $secondSignatureLine);
        }
    }

    private function buildMainLabel(ZplBuilder $zpl, array $data): void
    {
        if (!empty($data['qrUrl'])) {
            $zpl->qrCode(15, 505, 4, $data['qrUrl']);
        }

        if (!empty($data['barcode'])) {
            $zpl->barcode128(15, 650, 60, $data['barcode']);
        }

        $zpl->text(15, 715, 14, $data['productCode']);

        $zpl->text(210, 505, 55, 'PARAISO');
        $zpl->text(210, 565, 14, 'DONDE EMPIEZAN TUS SUEÑOS');
        $zpl->box(210, 583, 680, 2, 2);
        $zpl->text(210, 595, 18, 'CONTROL DE CALIDAD');
        $zpl->text(210, 619, 16, "N°: {$data['serial']}");
        $zpl->text(210, 639, 14, $data['productCode']);
        $zpl->text(210, 657, 14, "Tipo IV: {$data['type']}");
        $zpl->text(210, 675, 14, "({$data['measurements']}) - {$data['class']} {$data['plazas']}");
        $zpl->text(210, 697, 26, $data['modelName']);

        $this->buildLegalText($zpl, $data);
        $zpl->rotatedText(1540, 500, 14, 14, 'NO DESPRENDER LA ETIQUETA');
    }

    private function buildLegalText(ZplBuilder $zpl, array $data): void
    {
        $x = 905;
        $y = 505;

        if (!empty($data['legalText'])) {
            foreach (array_slice($this->wordWrap($data['legalText'], 56), 0, 8) as $line) {
                $zpl->text($x, $y, 11, $line);
                $y += 14;
            }
            $y += 10;
        }

        $zpl->text($x, 675, 11, 'Etiqueta elaborada 100% con material reciclado post-consumo');
        $zpl->text($x, 691, 12, 'COMPROMETIDOS CON EL MEDIO AMBIENTE');
    }

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
            'legalText'     => $this->sanitize($composition->legal_text ?? '', 500),
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
