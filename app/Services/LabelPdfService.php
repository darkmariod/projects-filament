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

        $html = <<<HTML
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 8px; line-height: 1.15; }
            .etiqueta { width: 100%; page-break-after: always; }

            /* ── Secciones ── */
            .seccion { padding: 4px 5px; }
            .sep { border-top: 1px solid #000; margin: 2px 0; }
            .sep-grueso { border-top: 3px solid #000; margin: 3px 0; }

            /* ── Layout ── */
            .fila { display: flex; justify-content: space-between; }
            .col { width: 48%; }
            .col-full { width: 100%; text-align: center; }
            .fila-footer { display: flex; justify-content: space-between; margin-top: 4px; }

            /* ── Tipografía ── */
            .negrita { font-weight: bold; }
            .grande { font-size: 14px; font-weight: bold; }
            .muy-grande { font-size: 24px; font-weight: bold; }
            .titulo-sec { font-weight: bold; font-size: 10px; text-align: center; margin-bottom: 3px; }
            .legal { font-size: 6px; margin-top: 2px; }

            /* ── Firmas ── */
            .linea-firma { border-bottom: 1px solid #000; width: 90px; margin-top: 2px; display: inline-block; }
            .firma-row { display: flex; gap: 6px; margin-top: 4px; }
            .firma-row .linea { border-bottom: 1px solid #000; width: 65px; }

            /* ── QR / Barcode ── */
            .qr-img { width: 100px; height: 100px; }

            /* ── Sección 3 ── */
            .seccion-principal { display: flex; padding: 4px 5px; }
            .sp-izq { width: 115px; text-align: center; }
            .sp-der { flex: 1; padding-left: 5px; text-align: center; }
            .sp-vert { writing-mode: vertical-rl; text-orientation: mixed; font-size: 7px; font-weight: bold;
                        border-left: 1px solid #000; padding-left: 2px; margin-left: 3px; }

            .barcode-num { font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 2px; margin-top: 4px; }
            .ambiental { font-size: 6.5px; margin-top: 2px; }
        </style>
        <div class="etiqueta">

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 1A — TRAZABILIDAD
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <div class="negrita" style="margin-bottom:3px;">Lote: {$batchNumber} ({$measurements}) {$class} {$plazas}</div>
                <div class="fila">
                    <div class="col">
                        <div>N°: <span class="negrita">{$serial}</span></div>
                        <div>{$productCode}</div>
                        <div>Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                    </div>
                    <div class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>{$type}</div>
                        <div class="negrita">{$modelName}</div>
                        <div>({$measurements}) {$class} {$plazas}</div>
                        <div style="margin-top:3px;">Operador Ensamble: <span class="linea-firma"></span></div>
                    </div>
                </div>
            </div>

            <div class="sep"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 1B — TRAZABILIDAD REPETIDA (con firmas)
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <div class="fila">
                    <div class="col">
                        <div>N°: <span class="negrita">{$serial}</span></div>
                        <div>{$productCode}</div>
                        <div>Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                    </div>
                    <div class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>{$type}</div>
                        <div class="negrita">{$modelName}</div>
                        <div>({$measurements}) {$class} {$plazas}</div>
                        <div style="margin-top:3px;">Cerrador: <span class="linea-firma"></span></div>
                        <div style="margin-top:2px;">Trazabilidad: <span class="linea-firma"></span></div>
                    </div>
                </div>
                <div class="firma-row">
                    <span class="linea"></span>
                    <span class="linea"></span>
                    <span class="linea"></span>
                </div>
            </div>

            <div class="sep-grueso"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 2 — COMPOSICION TECNICA
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion">
                <div class="titulo-sec">Informacion de Composicion</div>
                <div class="fila">
                    <div class="col">
                        <div>{$type}</div>
                        <div>{$class}: {$measurements} {$plazas}</div>
                        <div style="margin-top:2px; font-weight:bold;">CONDICIONES CONSERVACION</div>
                        <div>{$conservation}</div>
                        <div style="margin-top:3px;">Fecha: {$batchDate}</div>
                        <div>Lote: {$batchNumber}</div>
                        <div style="margin-top:4px;">
                            <span style="display:inline-block; border-bottom:1px solid #000; width:100px;"></span>
                        </div>
                        <div>Operador: ________________</div>
                    </div>
                    <div class="col">
                        <div>Forro: {$cover}</div>
                        {$resortesHtml}
                        <div>Espuma Poliuretano:</div>
                        <div>{$foam}</div>
                        <div style="margin-top:3px; font-weight:bold;">HECHO EN ECUADOR</div>
                        <div style="margin-top:2px;">FABRICADO POR:</div>
                        <div>{$manufacturer}</div>
                        {$rucHtml}
                        <div>{$warrantyText}</div>
                        <div>{$address}</div>
                    </div>
                </div>
                <div class="fila-footer">
                    <span>Operador {$operator}</span>
                    <span>{$inen}</span>
                    <span>{$website}</span>
                </div>
            </div>

            <div class="sep-grueso"></div>

            <!-- ══════════════════════════════════════════════════════════════
                 SECTION 3 — PRINCIPAL CON QR
                 ══════════════════════════════════════════════════════════════ -->
            <div class="seccion-principal">
                <div class="sp-izq">
                    <img src="data:image/svg+xml;base64,{$qrBase64}" class="qr-img">
                    <div class="barcode-num">{$barcodeNum}</div>
                </div>
                <div class="sp-der">
                    <div class="muy-grande">PARAISO</div>
                    <div style="font-size:7px;">DONDE EMPIEZAN TUS SUEÑOS</div>
                    <hr style="margin:2px 0;">
                    <div style="font-weight:bold; margin-top:2px;">CONTROL DE CALIDAD</div>
                    <div style="margin-top:2px;">N°: <span class="negrita">{$serial}</span></div>
                    <div>{$productCode}</div>
                    <div>{$type}</div>
                    <div>({$measurements}) - {$class} {$plazas}</div>
                    <div class="grande">{$modelName}</div>
                    <hr style="margin:2px 0;">
                    <div class="legal">{$legalText}</div>
                    <div class="ambiental">Etiqueta elaborada 100% con material reciclado post-consumo</div>
                    <div class="ambiental negrita">COMPROMETIDOS CON EL MEDIO AMBIENTE</div>
                </div>
                <div class="sp-vert">NO DESPRENDER LA ETIQUETA</div>
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
