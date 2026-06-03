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
            'product.productModel.category',
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
                'product.productModel.category',
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

    protected function buildLabelHtml(Label $label): string
    {
        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        $serial      = $label->serial;
        $qrUrl       = $label->qr_url;
        $productCode = $product->product_code;
        $modelName   = $model->name ?? '';
        $measurements = $product->measurements_text ?? '';
        $type        = $model->type ?? '';
        $class       = $model->class ?? '';
        $batchNumber = $batch->customer_batch_number ?? '';
        $batchDate   = $batch->customer_batch_date?->format('d/m/Y') ?? '';
        $operator    = $batch->operator ?? '';

        $cover       = $composition->cover_material ?? '';
        $springs     = $composition->springs ?? '';
        $foam        = $composition->foam_description ?? '';
        $conservation = $composition->conservation_instructions ?? '';
        $manufacturer = $composition->manufacturer ?? '';
        $ruc         = $composition->manufacturer_ruc ?? '';
        $address     = $composition->manufacturer_address ?? '';
        $inen        = $composition->inen_standard ?? 'NTE INEN 2035';
        $website     = $composition->website ?? '';
        $legalText   = $composition->legal_text ?? '';

        $qrBase64 = base64_encode(
            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(120)
                ->generate($qrUrl)
        );

        $html = '
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 9px; }
            .etiqueta { width: 100%; page-break-after: always; }
            .seccion { padding: 6px 8px; border-bottom: 1px solid #000; }
            .seccion-titulo { font-weight: bold; font-size: 10px; text-align: center; margin-bottom: 4px; }
            .fila { display: flex; justify-content: space-between; margin-bottom: 2px; }
            .col { width: 48%; }
            .col-full { width: 100%; }
            .negrita { font-weight: bold; }
            .grande { font-size: 14px; font-weight: bold; }
            .muy-grande { font-size: 18px; font-weight: bold; text-align: center; }
            .firma { border-bottom: 1px solid #000; width: 120px; margin-top: 16px; display: inline-block; }
            .qr-img { width: 100px; height: 100px; }
            .seccion-principal { display: flex; padding: 6px 8px; }
            .seccion-principal-izq { width: 110px; }
            .seccion-principal-der { flex: 1; padding-left: 8px; }
            .texto-vertical { writing-mode: vertical-rl; text-orientation: mixed; font-size: 8px; font-weight: bold; border-left: 1px solid #000; padding-left: 2px; margin-left: 4px; }
            .row-flex { display: flex; }
            .ambiental { font-size: 8px; margin-top: 6px; }
        </style>
        <div class="etiqueta">

            <!-- SECCION 1: TRAZABILIDAD -->
            <div class="seccion">
                <div class="fila">
                    <div class="col">
                        <div>N°: <span class="negrita">' . $serial . '</span></div>
                        <div>' . $productCode . '</div>
                        <div>Fecha: ' . $batchDate . '</div>
                        <div>Lote: ' . $batchNumber . '</div>
                    </div>
                    <div class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>' . $type . '</div>
                        <div class="negrita">' . $modelName . '</div>
                        <div>(' . $measurements . ')</div>
                        <div style="margin-top:8px;">Operador <span class="firma"></span></div>
                        <div style="margin-top:4px;">Ensamble <span class="firma"></span></div>
                        <div style="margin-top:4px;">Cerrador <span class="firma"></span></div>
                        <div style="margin-top:4px; font-size:8px;">Trazabilidad</div>
                    </div>
                </div>
            </div>

            <!-- SECCION 1B: TRAZABILIDAD REPETIDA -->
            <div class="seccion">
                <div class="fila">
                    <div class="col">
                        <div>N°: <span class="negrita">' . $serial . '</span></div>
                        <div>' . $productCode . '</div>
                        <div>Fecha: ' . $batchDate . '</div>
                        <div>Lote: ' . $batchNumber . '</div>
                    </div>
                    <div class="col">
                        <div class="negrita">CONTROL DE CALIDAD</div>
                        <div>' . $type . '</div>
                        <div class="negrita">' . $modelName . '</div>
                        <div>(' . $measurements . ')</div>
                        <div style="margin-top:8px;">Operador <span class="firma"></span></div>
                        <div style="margin-top:4px;">Ensamble <span class="firma"></span></div>
                        <div style="margin-top:4px;">Cerrador <span class="firma"></span></div>
                        <div style="margin-top:4px; font-size:8px;">Trazabilidad</div>
                    </div>
                </div>
            </div>

            <!-- SECCION 2: COMPOSICION TECNICA -->
            <div class="seccion">
                <div class="seccion-titulo">Informacion de Composicion</div>
                <div class="fila">
                    <div class="col">
                        <div>Tipo IV: ' . $type . '</div>
                        <div>Clase ' . $class . ': ' . $measurements . '</div>
                        <div style="margin-top:4px; font-size:8px; font-weight:bold;">CONDICIONES PARA SU CONSERVACION</div>
                        <div style="font-size:8px;">' . $conservation . '</div>
                        <div style="margin-top:8px;">Fecha: ' . $batchDate . '</div>
                        <div>Lote: ' . $batchNumber . '</div>
                        <div style="margin-top:8px; font-size:20px; font-weight:bold;">' . $operator . '</div>
                        <div style="font-size:8px;">Operador</div>
                    </div>
                    <div class="col">
                        <div>Forro: ' . $cover . '</div>
                        <div>Resortes: ' . $springs . '</div>
                        <div>' . $foam . '</div>
                        <div style="margin-top:6px; font-weight:bold;">HECHO EN ECUADOR</div>
                        <div style="margin-top:4px; font-size:8px;">FABRICADO POR:</div>
                        <div style="font-size:8px;">' . $manufacturer . '</div>
                        <div style="font-size:8px;">RUC: ' . $ruc . '</div>
                        <div style="font-size:8px;">' . $address . '</div>
                        <div style="margin-top:4px;">' . $inen . '</div>
                        <div>' . $website . '</div>
                    </div>
                </div>
            </div>

            <!-- SECCION 3: PRINCIPAL CON QR -->
            <div class="seccion-principal">
                <div class="seccion-principal-izq">
                    <img src="data:image/svg+xml;base64,' . $qrBase64 . '" class="qr-img">
                </div>
                <div class="seccion-principal-der">
                    <div class="muy-grande">PARAISO</div>
                    <div style="text-align:center; font-size:8px;">DONDE EMPIEZAN TUS SUENOS</div>
                    <div style="text-align:center; font-weight:bold; margin-top:4px;">CONTROL DE CALIDAD</div>
                    <div style="text-align:center;">N°: <span class="negrita">' . $serial . '</span></div>
                    <div style="text-align:center;">' . $productCode . '</div>
                    <div style="text-align:center;">' . $type . '</div>
                    <div style="text-align:center;" class="grande">' . $modelName . '</div>
                    <div style="text-align:center;">(' . $measurements . ')</div>
                    <div class="ambiental">' . $legalText . '</div>
                </div>
                <div class="texto-vertical">NO DESPRENDER LA ETIQUETA</div>
            </div>

        </div>';

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
