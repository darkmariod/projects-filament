<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garantía Registrada - Productos Paraíso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; min-height: 100vh; }
        .header { background: #8B0000; color: #fff; padding: 20px; text-align: center; }
        .header h1 { font-size: 28px; letter-spacing: 2px; }
        .header p { font-size: 12px; margin-top: 4px; opacity: 0.9; }
        .content { padding: 20px; }
        .success-icon { width: 64px; height: 64px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 32px; color: #155724; }
        .success-title { text-align: center; font-size: 20px; color: #155724; margin-bottom: 4px; }
        .success-subtitle { text-align: center; font-size: 13px; color: #666; margin-bottom: 20px; }
        .serial-box { background: #f0f0f0; border-radius: 6px; padding: 12px; text-align: center; margin-bottom: 16px; }
        .serial-box span { font-family: monospace; font-size: 14px; color: #8B0000; font-weight: bold; }
        .validity-box { background: #d4edda; border-radius: 8px; padding: 16px; text-align: center; margin-bottom: 16px; border: 1px solid #c3e6cb; }
        .validity-box h3 { color: #155724; font-size: 14px; }
        .validity-box .date { font-size: 18px; font-weight: bold; color: #155724; margin-top: 4px; }
        .section { background: #f9f9f9; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid #8B0000; }
        .section h3 { font-size: 12px; color: #8B0000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .label-text { font-size: 13px; color: #666; }
        .value-text { font-size: 13px; font-weight: bold; color: #333; text-align: right; }
        .btn { display: block; width: 100%; padding: 16px; background: #8B0000; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; text-align: center; text-decoration: none; cursor: pointer; margin-top: 8px; }
        .btn:hover { background: #6B0000; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-outline { background: transparent; color: #8B0000; border: 2px solid #8B0000; }
        .btn-outline:hover { background: #8B0000; color: #fff; }
        .actions { display: flex; flex-direction: column; gap: 8px; margin-top: 20px; }
        .footer { padding: 20px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; }
    </style>
    @include('partials.pwa')
</head>
<body>
<div class="container">

    <div class="header">
        <h1>PARAÍSO</h1>
        <p>DONDE EMPIEZAN TUS SUEÑOS</p>
    </div>

    <div class="content">

        <div class="success-icon">✓</div>
        <div class="success-title">Garantía registrada correctamente</div>
        <div class="success-subtitle">{{ $label->warranty->customer->first_name }} {{ $label->warranty->customer->last_name }}, tu garantía ha sido registrada con éxito.</div>

        <div class="serial-box">
            <div style="font-size:11px; color:#666; margin-bottom:4px;">CÓDIGO DE PRODUCTO</div>
            <span>{{ $label->serial }}</span>
        </div>

        <div class="validity-box">
            <h3>✓ Garantía vigente hasta</h3>
            <div class="date">{{ $label->warranty->warranty_end_date->format('d/m/Y') }}</div>
        </div>

        <div class="section">
            <h3>Datos del cliente</h3>
            <div class="row">
                <span class="label-text">Nombre</span>
                <span class="value-text">{{ $label->warranty->customer->full_name }}</span>
            </div>
            <div class="row">
                <span class="label-text">Documento</span>
                <span class="value-text">{{ strtoupper($label->warranty->customer->document_type) }}: {{ $label->warranty->customer->document_number }}</span>
            </div>
            <div class="row">
                <span class="label-text">Email</span>
                <span class="value-text">{{ $label->warranty->customer->email }}</span>
            </div>
            <div class="row">
                <span class="label-text">Celular</span>
                <span class="value-text">{{ $label->warranty->customer->phone }}</span>
            </div>
        </div>

        <div class="section">
            <h3>Información del producto</h3>
            <div class="row">
                <span class="label-text">Producto</span>
                <span class="value-text">{{ $label->product->name }}</span>
            </div>
            <div class="row">
                <span class="label-text">Modelo</span>
                <span class="value-text">{{ $label->product->productModel->name }}</span>
            </div>
            <div class="row">
                <span class="label-text">Medidas</span>
                <span class="value-text">{{ $label->product->measurements_text }}</span>
            </div>
        </div>

        <div class="section">
            <h3>Datos de la compra</h3>
            <div class="row">
                <span class="label-text">Local</span>
                <span class="value-text">{{ $label->warranty->store_name }}</span>
            </div>
            <div class="row">
                <span class="label-text">Factura</span>
                <span class="value-text">{{ $label->warranty->invoice_number }}</span>
            </div>
            <div class="row">
                <span class="label-text">Fecha de compra</span>
                <span class="value-text">{{ $label->warranty->purchase_date->format('d/m/Y') }}</span>
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('public.warranty.certificate', $label->serial) . '?download=1' }}" class="btn btn-success">
                Descargar certificado PDF
            </a>
            <a href="{{ route('public.product', $label->serial) }}" class="btn btn-outline">
                Ver producto
            </a>
        </div>

    </div>

    <div class="footer">
        <p>Productos Paraíso del Ecuador</p>
        <p>www.paraiso.com.ec</p>
    </div>

</div>
</body>
</html>
