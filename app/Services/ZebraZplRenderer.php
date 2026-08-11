<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use App\Models\Product;
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
        $label->load([
            'product.productModel.category',
            'product.technicalComposition',
            'labelBatch.generatedBy',
        ]);

        $data = $this->extractData($label);
        $zpl  = new ZplBuilder();

        $zpl->header(self::WIDTH_DOTS, self::HEIGHT_DOTS);

        // El bloque de calidad termina cerca de y=126; los separadores van justo
        // debajo para no dejar espacios vacíos entre secciones.
        $this->buildQualityBlock($zpl, $data, 15);
        $zpl->box(self::MARGIN_X, 140, 730, 2, 2);

        $this->buildSignatureRow($zpl, 158);
        $zpl->box(10, 195, 740, 4, 4);

        $this->buildComposition($zpl, $data, 215);
        $zpl->box(10, 700, 740, 4, 4);

        $this->buildMainLabel($zpl, $data, 730);

        return $zpl->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BLOQUE DE CONTROL DE CALIDAD (único — datos una sola vez)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Un solo bloque de control de calidad: la cabecera del producto y los datos
     * de trazabilidad aparecen UNA vez. En la columna derecha se apilan las tres
     * firmas: Operador/Ensamble, Cerrador y Trazabilidad. Espaciado amplio para
     * que ninguna línea se encime.
     */
    private function buildQualityBlock(ZplBuilder $zpl, array $data, int $y): void
    {
        $col1 = self::MARGIN_X;
        $col2 = 300;
        $col3 = 610;

        // Columna izquierda: código + datos del lote (una sola vez)
        $zpl->text($col1, $y, 12, $data['productCode']);
        $zpl->text($col1, $y + 18, 11, "Operador: {$data['operator']}");
        $zpl->text($col1, $y + 34, 11, "Lote: {$data['lote_nro']}");
        $zpl->text($col1, $y + 50, 11, "Fecha: {$data['batchDate']}");
        // Trazabilidad (una sola vez)
        $zpl->text($col1, $y + 72, 11, "Serial: {$data['serial']}");
        $zpl->text($col1, $y + 88, 11, "Seq: {$data['sequence_number']}");

        // Columna central: control de calidad (una sola vez)
        $zpl->text($col2, $y, 14, 'CONTROL DE CALIDAD');
        $zpl->text($col2, $y + 20, 12, "Tipo IV: {$data['type']}");
        $zpl->text($col2, $y + 38, 16, $data['modelName']);
        $zpl->text($col2, $y + 60, 12, "({$data['measurements']}) {$data['class']} {$data['plazas']}");
        // Trazabilidad (una sola vez)
        $zpl->text($col2, $y + 84, 11, "Lote: {$data['internal_batch_code']}");
        $zpl->text($col2, $y + 100, 11, "Gen: {$data['generated_by_name']}");

        // Columna derecha: los tres responsables. Bajo cada rótulo va el nombre
        // registrado en el lote y debajo la línea para la firma manual.
        $firmas = [
            ['Operador / Ensamble', $data['operator']],
            ['Cerrador',            $data['closer']],
            ['Trazabilidad',        $data['tracer']],
        ];

        $fy = $y;
        foreach ($firmas as [$rotulo, $nombre]) {
            $zpl->text($col3, $fy, 11, $rotulo);
            if ($nombre !== '') {
                $zpl->text($col3, $fy + 13, 11, $nombre);
            }
            $zpl->box($col3, $fy + 26, 135, 2, 2);
            $fy += 38;
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
        $zpl->text($leftX, $ly, 14, "Tipo IV: {$data['type']}");
        $ly += 24;
        $zpl->text($leftX, $ly, 14, "{$data['class']}: {$data['measurements']} {$data['plazas']}");
        $ly += 26;
        $zpl->text($leftX, $ly, 12, 'CONDICIONES PARA SU CONSERVACION');
        $ly += 22;

        foreach (array_slice($this->splitMultiline($data['conservation'] ?? '', 48), 0, 2) as $line) {
            $zpl->text($leftX, $ly, 12, $line);
            $ly += 20;
        }

        // Textile care symbols strip (do not wash / bleach / tumble dry / iron / dry clean)
        $icons = $this->zplGraphic('care-icons.gfa');
        if ($icons !== null) {
            $ly += 8;
            $zpl->raw("^FO{$leftX},{$ly}{$icons}^FS\n");
            $ly += 64;
        }

        $ly += 10;
        $zpl->text($leftX, $ly, 13, "Fecha: {$data['batchDate']}");
        $ly += 22;
        $zpl->text($leftX, $ly, 13, "Lote: {$data['lote_nro']}");
        $ly += 26;
        $zpl->box($leftX, $ly, 150, 2, 2);
        $ly += 14;
        // El serial se escala según su largo para no invadir la columna derecha (x>=400).
        $serialFont = $this->fitFont($data['serial'], 375, 28, 14);
        $zpl->text($leftX, $ly, $serialFont, $data['serial']);
        $ly += $serialFont + 12;
        $zpl->text($leftX, $ly, 12, "Operador: {$data['operator']}   {$data['inen']}");
        $ly += 22;

        if (!empty($data['website'])) {
            $zpl->text($leftX, $ly, 12, $data['website']);
        }

        // ── Columna derecha ──────────────────────────────────────────────
        $ry = $y;
        $coverLines = $this->splitMultiline($data['cover'] ?? '', 30);
        if (!empty($coverLines)) {
            $first = array_shift($coverLines);
            $zpl->text($rightX, $ry, 14, "Forro: {$first}");
            $ry += 24;
            foreach (array_slice($coverLines, 0, 2) as $line) {
                $zpl->text($rightX, $ry, 14, $line);
                $ry += 24;
            }
        }

        if (!empty($data['springs'])) {
            $zpl->text($rightX, $ry, 14, $data['springs']);
            $ry += 24;
        }

        $zpl->text($rightX, $ry, 14, 'Espuma Poliuretano:');
        $ry += 24;

        foreach (array_slice($this->splitMultiline($data['foam'] ?? '', 32), 0, 3) as $line) {
            $zpl->text($rightX, $ry, 12, $line);
            $ry += 20;
        }

        $ry += 12;
        $zpl->text($rightX, $ry, 16, 'HECHO EN ECUADOR');
        $ry += 26;
        $zpl->text($rightX, $ry, 12, 'FABRICADO POR:');
        $ry += 20;
        $zpl->text($rightX, $ry, 12, $data['manufacturer']);
        $ry += 18;

        if (!empty($data['ruc'])) {
            $zpl->text($rightX, $ry, 12, "RUC {$data['ruc']}");
            $ry += 18;
        }

        if (!empty($data['warrantyText'])) {
            $zpl->text($rightX, $ry, 12, $data['warrantyText']);
            $ry += 18;
        }

        if (!empty($data['address'])) {
            foreach (array_slice($this->splitMultiline($data['address'], 34), 0, 2) as $line) {
                $zpl->text($rightX, $ry, 12, $line);
                $ry += 18;
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
            // Magnification 8: ~33mm QR modules scan reliably on thermal print
            $zpl->qrCode($leftX, $startY, 8, $data['qrUrl']);
        }

        $barcodeY = $startY + 330;
        if (!empty($data['barcode'])) {
            $zpl->barcode128($leftX, $barcodeY, 90, $data['barcode'], 1);
        }

        $zpl->text($leftX, $barcodeY + 108, 13, $data['productCode']);

        $ry = $startY;

        // Logo fallback chain: product image → category logo → paraiso-logo.gfa → text
        $logoZpl = null;

        // 1. Try product image
        if (!empty($data['image'])) {
            $converter = new ImageToZplConverter();
            $logoZpl = $converter->convert($data['image']);
        }

        // 2. Try category logo
        if ($logoZpl === null && !empty($data['category_logo'])) {
            $converter = new ImageToZplConverter();
            $logoZpl = $converter->convert($data['category_logo']);
        }

        // 3. Try paraiso-logo.gfa
        if ($logoZpl === null) {
            $logoZpl = $this->logoZpl();
        }

        // 4. Text fallback
        if ($logoZpl !== null) {
            $zpl->raw("^FO{$rightX},{$ry}{$logoZpl}^FS\n");
            $ry += 118;
        } else {
            $zpl->text($rightX, $ry, 44, 'PARAISO');
            $ry += 56;
        }

        $zpl->text($rightX, $ry, 12, 'DONDE EMPIEZAN TUS SUEÑOS');
        $ry += 20;
        $zpl->box($rightX, $ry, 340, 2, 2);
        $ry += 18;
        $zpl->text($rightX, $ry, 18, 'CONTROL DE CALIDAD');
        $ry += 28;
        $zpl->text($rightX, $ry, 15, "N°: {$data['serial']}");
        $ry += 22;
        $zpl->text($rightX, $ry, 13, $data['productCode']);
        $ry += 20;
        $zpl->text($rightX, $ry, 13, "Tipo IV: {$data['type']}");
        $ry += 26;
        $zpl->text($rightX, $ry, 30, $data['modelName']);
        $ry += 42;
        $zpl->text($rightX, $ry, 15, "({$data['measurements']}) - {$data['class']} {$data['plazas']}");
        $ry += 36;

        $this->buildLegalText($zpl, $data, $rightX, $ry);

        $zpl->rotatedText(self::WIDTH_DOTS - 25, $startY, 16, 16, 'NO DESPRENDER LA ETIQUETA');
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
            foreach (array_slice($this->wordWrap($data['legalText'], 48), 0, 6) as $line) {
                $zpl->text($x, $y, 11, $line);
                $y += 16;
            }
        }

        // Eco footer anchored at the bottom edge, like the client reference
        $footY = self::HEIGHT_DOTS - 92;
        $zpl->text(120, $footY, 12, 'Etiqueta elaborada 100% con material reciclado post-consumo');
        $zpl->text(150, $footY + 22, 13, 'COMPROMETIDOS CON EL MEDIO AMBIENTE');
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
            'serial'              => $this->sanitize($label->serial),
            'qrUrl'               => $this->sanitize($label->qr_url ?? '', 200),
            'productCode'         => $this->sanitize($product->product_code ?? ''),
            'modelName'           => $this->sanitize($model->name ?? ''),
            'measurements'        => $this->sanitize($product->measurements_text ?? ''),
            'lote_nro'            => $this->sanitize($batch->customer_batch_number ?? ''),
            'batchDate'           => $batch->customer_batch_date?->format('d/m/Y') ?? '',
            'operator'            => $this->sanitize($batch->operator ?? ''),
            'closer'              => $this->sanitize($batch->closer ?? ''),
            'tracer'              => $this->sanitize($batch->tracer ?? ''),
            'type'                => $this->withoutPrefix($this->sanitize($model->type ?? ''), 'Tipo IV:'),
            'class'               => $this->normalizeClass($this->sanitize($product->class ?? ($model->class ?? ''))),
            'plazas'              => $this->sanitize($product->plazas ?? ''),
            'barcode'             => $this->sanitize($label->barcode ?? '', 48),
            'cover'               => $composition->cover_material ?? '',
            'foam'                => $composition->foam_description ?? '',
            'springs'             => $composition->springs ?? '',
            'conservation'        => $this->sanitize($composition->conservation_instructions ?? ''),
            'manufacturer'        => $this->sanitize($composition->manufacturer ?? ''),
            'ruc'                 => $this->sanitize($composition->manufacturer_ruc ?? ''),
            'address'             => $this->sanitize($composition->manufacturer_address ?? ''),
            'inen'                => $this->sanitize($composition->inen_standard ?? 'NTE INEN 2035'),
            'website'             => $this->sanitize($composition->website ?? ''),
            'legalText'           => $this->sanitize(
                preg_replace('/\s+/', ' ', str_replace(['\\n', '\\r', '\\t', '\\'], ' ', $composition->legal_text ?? '')) ?? '',
                500
            ),
            'warrantyText'        => $model->warranty_years
                ? "Garantía: {$model->warranty_years} años"
                : '',
            'image'               => $product->image ?? null,
            'category_logo'       => $model->category->logo ?? null,
            // Traceability fields
            'generated_by_name'   => $this->sanitize($batch->generatedBy->name ?? ''),
            'sequence_number'     => $label->sequence_number ?? '',
            'internal_batch_code' => $this->sanitize($batch->internal_batch_code ?? ''),
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

    /**
     * Render a test label with optional product data.
     * If product is provided, shows category logo and product info.
     * If not, shows basic test label (retrocompatible).
     */
    public function renderTestLabel(?Product $product = null): string
    {
        $zpl = new ZplBuilder();
        $zpl->header(self::WIDTH_DOTS, self::HEIGHT_DOTS);

        // Header
        $zpl->text(200, 50, 24, 'ETIQUETA DE PRUEBA');

        if ($product !== null) {
            // Load relationships for logo fallback
            $product->load('productModel.category');
            $model = $product->productModel;
            $category = $model->category;

            // Logo section (y: 100-220) — use relative paths for convert()
            $logoZpl = null;
            if (!empty($product->image)) {
                $converter = new ImageToZplConverter();
                $logoZpl = $converter->convert($product->image);
            } elseif (!empty($category->logo)) {
                $converter = new ImageToZplConverter();
                $logoZpl = $converter->convert($category->logo);
            } elseif (file_exists(resource_path('zpl/paraiso-logo.gfa'))) {
                $logoZpl = $this->zplGraphic('paraiso-logo.gfa');
            }

            if ($logoZpl !== null) {
                $zpl->raw("^FO220,100{$logoZpl}^FS\n");
            } else {
                $zpl->text(300, 140, 20, 'PARAISO');
            }

            // Product data (y: 240-350)
            $y = 240;
            $zpl->text(50, $y, 14, "Modelo: {$model->name}");
            $y += 24;
            $zpl->text(50, $y, 14, "Codigo: {$product->product_code}");
            $y += 24;
            $zpl->text(50, $y, 14, "Medidas: {$product->measurements_text}");
            $y += 24;
            $zpl->text(50, $y, 14, "Categoria: {$category->name}");
        } else {
            // Basic test label without product
            $zpl->text(200, 150, 18, 'Sin producto seleccionado');
        }

        // Connection info (y: 400) — use correct model fields
        $zpl->text(50, 400, 12, "Conexion: {$this->settings->connection_type}");
        if ($this->settings->isNetworkConfigured()) {
            $zpl->text(50, 420, 12, "IP: {$this->settings->getPrinterEndpoint()}");
        } elseif ($this->settings->isUsbConfigured()) {
            $zpl->text(50, 420, 12, "USB: {$this->settings->printer_name}");
        }

        // Date/time (y: 460)
        $zpl->text(50, 460, 12, 'Fecha: ' . now()->format('d/m/Y H:i:s'));

        // Border (visible rectangle for alignment verification)
        $zpl->box(10, 10, self::WIDTH_DOTS - 20, self::HEIGHT_DOTS - 20, 4);

        return $zpl->close();
    }
}
