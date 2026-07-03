<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use Barryvdh\DomPDF\Facade\Pdf;

class LabelPdfService
{
    public function generateForLabel(Label $label): string
    {
        $label->load([
            'product.productModel',
            'product.technicalComposition',
            'labelBatch',
        ]);

        $html = $this->buildLabelHtml($label);
        $pdf  = Pdf::loadHTML($html)
            ->setPaper([0, 0, 269.29, 566.93], 'portrait');

        return $pdf->output();
    }

    public function generateForBatch(LabelBatch $batch): string
    {
        $labels = $batch->labels()
            ->whereNull('printed_at')
            ->where('status', '!=', 'anulled')
            ->orderBy('sequence_number')
            ->with([
                'product.productModel',
                'product.technicalComposition',
                'labelBatch',
            ])
            ->get();

        $html = '';
        foreach ($labels as $label) {
            $html .= $this->buildLabelHtml($label);
        }

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, 269.29, 566.93], 'portrait')
            ->output();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BUILD HTML
    // ─────────────────────────────────────────────────────────────────────────

    protected function buildLabelHtml(Label $label): string
    {
        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        $serial       = e($label->serial);
        $qrUrl        = $label->qr_url;
        $productCode  = e($product->product_code);
        $modelName    = e($model->name ?? '');
        $measurements = e($product->measurements_text ?? '');
        $type         = e($model->type ?? '');
        $class        = e($product->class ?? ($model->class ?? ''));
        $plazas       = e($product->plazas ?? '');
        $batchNumber  = e($batch->customer_batch_number ?? '');
        $batchDate    = $batch->customer_batch_date?->format('d/m/Y') ?? '';
        $operator     = e($batch->operator ?? '');
        $barcodeNum   = e($label->barcode ?? '');

        $resolveNewlines = fn(string $v): string => nl2br(e(str_replace(['\\n', '\n'], "\n", $v)));

        $cover        = $resolveNewlines($composition->cover_material ?? '');
        $springs      = e($composition->springs ?? '');
        $foam         = $resolveNewlines($composition->foam_description ?? '');
        $conservation = $resolveNewlines($composition->conservation_instructions ?? '');
        $manufacturer = e($composition->manufacturer ?? '');
        $ruc          = e($composition->manufacturer_ruc ?? '');
        $address      = e($composition->manufacturer_address ?? '');
        $inen         = e($composition->inen_standard ?? 'NTE INEN 2035');
        $website      = e($composition->website ?? '');
        $legalText    = e($composition->legal_text ?? '');
        $warrantyText = $model->warranty_years ? "Garantía: {$model->warranty_years} años" : '';

        $resortesHtml = $springs ? '<div>Resortes: ' . $springs . '</div>' : '';
        $rucHtml      = $ruc ? '<div>RUC ' . $ruc . '</div>' : '';

        $qrBase64 = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(120)
                ->generate($qrUrl)
        );

        // Brand logo: image when available, text fallback otherwise
        $logoHtml = '<div class="muy-grande">PARAISO</div>';
        $logoPath = resource_path('images/paraiso-logo.png');
        if (is_file($logoPath)) {
            $logoBase64 = base64_encode((string) file_get_contents($logoPath));
            $logoHtml = '<img src="data:image/png;base64,' . $logoBase64 . '" style="width:110px;">';
        }

        // Textile care symbols strip (matches the ZPL label)
        $careHtml = '';
        $carePath = resource_path('images/care-icons.png');
        if (is_file($carePath)) {
            $careBase64 = base64_encode((string) file_get_contents($carePath));
            $careHtml = '<div style="margin-top:1px;"><img src="data:image/png;base64,' . $careBase64 . '" style="width:80px;"></div>';
        }

        $html = <<<HTML
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 8px; line-height: 1.15; }
            .etiqueta { width: 100%; page-break-after: always; }

            /* ── Secciones ── */
            .seccion { padding: 3px 5px; }
            .sep { border-top: 1px solid #000; margin: 2px 0; }
            .sep-grueso { border-top: 3px solid #000; margin: 2px 0; }

            /* ── Layout (tablas: dompdf no soporta flex) ── */
            table.fila { width: 100%; border-collapse: collapse; }
            table.fila td { vertical-align: top; }
            td.col { width: 50%; }

            /* ── Tipografía ── */
            .negrita { font-weight: bold; }
            .grande { font-size: 13px; font-weight: bold; }
            .titulo-sec { font-weight: bold; font-size: 10px; text-align: center; margin-bottom: 2px; }
            .legal { font-size: 6px; margin-top: 2px; }

            /* ── Firmas ── */
            .linea-firma { border-bottom: 1px solid #000; width: 90px; margin-top: 2px; display: inline-block; }

            .barcode-num { font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 2px; margin-top: 2px; }
            .ambiental { font-size: 6.5px; margin-top: 1px; }
        </style>
        <div class="etiqueta">

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 1A — TRAZABILIDAD
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <div class="negrita" style="margin-bottom:2px;">Lote: {$batchNumber} ({$measurements}) {$class} {$plazas}</div>
                <table class="fila"><tr>
                    <td class="col">
                        <div>N°: <span class="negrita">{$serial}</span></div>
                        <div>{$productCode}</div>
                        <div>Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                    </td>
                    <td class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>{$type}</div>
                        <div class="negrita">{$modelName}</div>
                        <div>({$measurements}) {$class} {$plazas}</div>
                        <div style="margin-top:2px;">Operador Ensamble: <span class="linea-firma"></span></div>
                    </td>
                </tr></table>
            </div>

            <div class="sep"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 1B — TRAZABILIDAD REPETIDA (con firmas)
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <table class="fila"><tr>
                    <td class="col">
                        <div>N°: <span class="negrita">{$serial}</span></div>
                        <div>{$productCode}</div>
                        <div>Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                    </td>
                    <td class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>{$type}</div>
                        <div class="negrita">{$modelName}</div>
                        <div>({$measurements}) {$class} {$plazas}</div>
                        <div style="margin-top:2px;">Cerrador: <span class="linea-firma"></span></div>
                        <div style="margin-top:2px;">Trazabilidad: <span class="linea-firma"></span></div>
                    </td>
                </tr></table>
                <table class="fila" style="margin-top:3px;"><tr>
                    <td style="width:33%; padding-right:8px;"><div style="border-bottom:1px solid #000;">&nbsp;</div></td>
                    <td style="width:33%; padding-right:8px;"><div style="border-bottom:1px solid #000;">&nbsp;</div></td>
                    <td style="width:33%;"><div style="border-bottom:1px solid #000;">&nbsp;</div></td>
                </tr></table>
            </div>

            <div class="sep-grueso"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 2 — COMPOSICION TECNICA
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <div class="titulo-sec">Informacion de Composicion</div>
                <table class="fila"><tr>
                    <td class="col">
                        <div>{$type}</div>
                        <div>{$class}: {$measurements} {$plazas}</div>
                        <div style="margin-top:2px; font-weight:bold;">CONDICIONES CONSERVACION</div>
                        <div>{$conservation}</div>
                        {$careHtml}
                        <div style="margin-top:2px;">Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                        <div style="margin-top:3px;">
                            <span style="display:inline-block; border-bottom:1px solid #000; width:100px;"></span>
                        </div>
                        <div>Operador: ________________</div>
                    </td>
                    <td class="col">
                        <div>Forro: {$cover}</div>
                        {$resortesHtml}
                        <div>Espuma Poliuretano:</div>
                        <div>{$foam}</div>
                        <div style="margin-top:2px; font-weight:bold;">HECHO EN ECUADOR</div>
                        <div style="margin-top:1px;">FABRICADO POR:</div>
                        <div>{$manufacturer}</div>
                        {$rucHtml}
                        <div>{$warrantyText}</div>
                        <div>{$address}</div>
                    </td>
                </tr></table>
                <table class="fila" style="margin-top:3px;"><tr>
                    <td>Operador {$operator}</td>
                    <td style="text-align:center;">{$inen}</td>
                    <td style="text-align:right;">{$website}</td>
                </tr></table>
            </div>

            <div class="sep-grueso"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 3 — PRINCIPAL CON QR (tabla: QR izq, marca der)
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <table class="fila"><tr>
                    <td style="width:115px; text-align:center; vertical-align:top;">
                        <img src="data:image/svg+xml;base64,{$qrBase64}" style="width:100px; height:100px;">
                        <div class="barcode-num">{$barcodeNum}</div>
                    </td>
                    <td style="text-align:center; vertical-align:top; padding-left:5px;">
                        {$logoHtml}
                        <div style="font-size:7px;">DONDE EMPIEZAN TUS SUEÑOS</div>
                        <hr style="margin:2px 0;">
                        <div style="font-weight:bold;">CONTROL DE CALIDAD</div>
                        <div style="margin-top:1px;">N°: <span class="negrita">{$serial}</span></div>
                        <div>{$productCode}</div>
                        <div>{$type}</div>
                        <div>({$measurements}) - {$class} {$plazas}</div>
                        <div class="grande">{$modelName}</div>
                        <hr style="margin:2px 0;">
                        <div class="legal">{$legalText}</div>
                        <div class="ambiental">Etiqueta elaborada 100% con material reciclado post-consumo</div>
                        <div class="ambiental negrita">COMPROMETIDOS CON EL MEDIO AMBIENTE</div>
                        <div class="ambiental negrita" style="margin-top:2px;">NO DESPRENDER LA ETIQUETA</div>
                    </td>
                </tr></table>
            </div>

        </div>
        HTML;

        return $html;
    }

    public function getFilenameForBatch(LabelBatch $batch): string
    {
        return 'etiquetas-' . $batch->internal_batch_code . '.pdf';
    }

    public function getFilenameForLabel(Label $label): string
    {
        return 'etiqueta-' . $label->serial . '.pdf';
    }
}
