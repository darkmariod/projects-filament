<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Garantía - {{ $label->serial }}</title>
    <style>
        @page { margin: 12mm 12mm 8mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 9pt; line-height: 1.35; }
        .page { width: 100%; }

        .header { background: #8B0000; color: #fff; padding: 12px 20px; margin: -12mm -12mm 0; text-align: center; }
        .header h1 { font-size: 22pt; letter-spacing: 4px; }
        .header p { font-size: 9pt; opacity: 0.9; margin-top: 2px; }
        .header .company-info { font-size: 7.5pt; opacity: 0.7; margin-top: 4px; }

        .cert-title { text-align: center; font-size: 14pt; color: #8B0000; margin: 14px 0 10px; font-weight: bold; letter-spacing: 1px; }

        .serial-box { text-align: center; margin-bottom: 10px; }
        .serial-box .label { font-size: 7.5pt; color: #666; }
        .serial-box .code { font-family: 'Courier New', monospace; font-size: 13pt; color: #8B0000; font-weight: bold; letter-spacing: 1px; }

        .validity-box { background: #d4edda; border: 2px solid #c3e6cb; border-radius: 6px; padding: 8px; text-align: center; margin-bottom: 12px; }
        .validity-box .title { font-size: 9pt; color: #155724; }
        .validity-box .date { font-size: 16pt; font-weight: bold; color: #155724; margin-top: 2px; }

        .columns { display: flex; gap: 10px; margin-bottom: 12px; }
        .col { flex: 1; }
        .section { margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px; }
        .section h3 { font-size: 7.5pt; color: #8B0000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .row { margin-bottom: 2px; }
        .row .label { font-size: 7pt; color: #888; }
        .row .value { font-size: 8.5pt; font-weight: bold; color: #333; }

        .legal { background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; padding: 8px; margin-bottom: 10px; }
        .legal h3 { font-size: 7.5pt; color: #8B0000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .legal p { font-size: 7pt; color: #666; line-height: 1.4; text-align: justify; }

        .footer { text-align: center; font-size: 7pt; color: #999; border-top: 1px solid #eee; padding-top: 6px; margin-top: 6px; }
        .footer .line { margin-bottom: 1px; }
        .footer .generated { font-size: 6.5pt; color: #bbb; margin-top: 2px; }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <h1>PARAÍSO</h1>
        <p>DONDE EMPIEZAN TUS SUEÑOS</p>
        <div class="company-info">
            Productos Paraíso del Ecuador &bull; www.paraiso.com.ec
        </div>
    </div>

    <div class="cert-title">CERTIFICADO DIGITAL DE GARANTÍA</div>

    <div class="serial-box">
        <div class="label">CÓDIGO DE PRODUCTO</div>
        <div class="code">{{ $label->serial }}</div>
    </div>

    <div class="validity-box">
        <div class="title">Garantía vigente hasta</div>
        <div class="date">{{ $label->warranty->warranty_end_date->format('d/m/Y') }}</div>
    </div>

    <div class="columns">

        <div class="col">

            <div class="section">
                <h3>Datos del cliente</h3>
                <div class="row">
                    <div class="label">Nombre completo</div>
                    <div class="value">{{ $label->warranty->customer->full_name }}</div>
                </div>
                <div class="row">
                    <div class="label">Documento</div>
                    <div class="value">{{ strtoupper($label->warranty->customer->document_type) }}: {{ $label->warranty->customer->document_number }}</div>
                </div>
                <div class="row">
                    <div class="label">Correo electrónico</div>
                    <div class="value">{{ $label->warranty->customer->email }}</div>
                </div>
                <div class="row">
                    <div class="label">Celular</div>
                    <div class="value">{{ $label->warranty->customer->phone }}</div>
                </div>
                <div class="row">
                    <div class="label">Dirección</div>
                    <div class="value">{{ $label->warranty->customer->address }}, {{ $label->warranty->customer->city }} - {{ $label->warranty->customer->province }}</div>
                </div>
            </div>

            <div class="section">
                <h3>Datos de la compra</h3>
                <div class="row">
                    <div class="label">Local de compra</div>
                    <div class="value">{{ $label->warranty->store_name }}</div>
                </div>
                <div class="row">
                    <div class="label">Número de factura</div>
                    <div class="value">{{ $label->warranty->invoice_number }}</div>
                </div>
                <div class="row">
                    <div class="label">Fecha de compra</div>
                    <div class="value">{{ $label->warranty->purchase_date->format('d/m/Y') }}</div>
                </div>
                <div class="row">
                    <div class="label">Inicio de garantía</div>
                    <div class="value">{{ $label->warranty->warranty_start_date->format('d/m/Y') }}</div>
                </div>
            </div>

        </div>

        <div class="col">

            <div class="section">
                <h3>Datos del producto</h3>
                <div class="row">
                    <div class="label">Producto</div>
                    <div class="value">{{ $label->product->name }}</div>
                </div>
                <div class="row">
                    <div class="label">Modelo</div>
                    <div class="value">{{ $label->product->productModel->name }}</div>
                </div>
                <div class="row">
                    <div class="label">Medidas</div>
                    <div class="value">{{ $label->product->measurements_text }}</div>
                </div>
                <div class="row">
                    <div class="label">Tipo</div>
                    <div class="value">{{ $label->product->productModel->type }}</div>
                </div>
                <div class="row">
                    <div class="label">Período de garantía</div>
                    <div class="value">{{ $label->product->productModel->warranty_years }} años</div>
                </div>
            </div>

            <div class="section">
                <h3>Lote de fabricación</h3>
                <div class="row">
                    <div class="label">Lote</div>
                    <div class="value">{{ $label->labelBatch->customer_batch_number }}</div>
                </div>
                <div class="row">
                    <div class="label">Fecha de fabricación</div>
                    <div class="value">{{ $label->labelBatch->customer_batch_date->format('d/m/Y') }}</div>
                </div>
            </div>

        </div>

    </div>

    <div class="legal">
        <h3>Términos y condiciones</h3>
        <p>
            Esta garantía cubre defectos de fabricación por un período de {{ $label->product->productModel->warranty_years }} años a partir de la fecha de compra. 
            La garantía no cubre daños por mal uso, accidentes, modificaciones no autorizadas, desgaste normal, o uso inadecuado del producto. 
            Para hacer efectiva la garantía, el cliente debe presentar este certificado digital junto con la factura de compra original. 
            Productos Paraíso del Ecuador se reserva el derecho de reparar o reemplazar el producto según su criterio. 
            Esta garantía es válida únicamente en Ecuador.
        </p>
    </div>

    <div class="footer">
        <div class="line">Productos Paraíso del Ecuador</div>
        <div class="line">www.paraiso.com.ec</div>
        <div class="generated">Documento generado digitalmente el {{ now()->format('d/m/Y \a \l\a\s H:i') }}</div>
    </div>

</div>
</body>
</html>
