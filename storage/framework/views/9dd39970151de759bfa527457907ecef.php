<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Paraíso - <?php echo e($label->serial); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; min-height: 100vh; }
        .header { background: #8B0000; color: #fff; padding: 20px; text-align: center; }
        .header h1 { font-size: 28px; letter-spacing: 2px; }
        .header p { font-size: 12px; margin-top: 4px; opacity: 0.9; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 8px; }
        .badge-green { background: #d4edda; color: #155724; }
        .badge-gray { background: #e2e3e5; color: #383d41; }
        .badge-red { background: #f8d7da; color: #721c24; }
        .content { padding: 20px; }
        .section { background: #f9f9f9; border-radius: 8px; padding: 16px; margin-bottom: 16px; border-left: 4px solid #8B0000; }
        .section h3 { font-size: 12px; color: #8B0000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .label-text { font-size: 13px; color: #666; }
        .value-text { font-size: 13px; font-weight: bold; color: #333; text-align: right; }
        .serial-box { background: #f0f0f0; border-radius: 6px; padding: 12px; text-align: center; margin-bottom: 16px; }
        .serial-box span { font-family: monospace; font-size: 14px; color: #8B0000; font-weight: bold; }
        .btn { display: block; width: 100%; padding: 16px; background: #8B0000; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; text-align: center; text-decoration: none; cursor: pointer; margin-top: 8px; }
        .btn:hover { background: #6B0000; }
        .btn-gray { background: #6c757d; }
        .btn-gray:hover { background: #5a6268; }
        .registered-box { background: #d4edda; border-radius: 8px; padding: 16px; text-align: center; margin-bottom: 16px; border: 1px solid #c3e6cb; }
        .registered-box h3 { color: #155724; font-size: 16px; }
        .registered-box p { color: #155724; font-size: 13px; margin-top: 4px; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .footer { padding: 20px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>PARAÍSO</h1>
        <p>DONDE EMPIEZAN TUS SUEÑOS</p>
    </div>

    <div class="content">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
            <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="serial-box">
            <div style="font-size:11px; color:#666; margin-bottom:4px;">CÓDIGO DE PRODUCTO</div>
            <span><?php echo e($label->serial); ?></span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label->product->image): ?>
            <div style="text-align:center; margin-bottom:16px;">
                <img src="<?php echo e(asset('storage/' . $label->product->image)); ?>"
                     alt="<?php echo e($label->product->name); ?>"
                     style="max-width:100%; border-radius:8px; max-height:300px; object-fit:contain;">
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div style="text-align:center; margin-bottom:16px;">
            <div style="background:#fff; border:1px solid #eee; border-radius:8px; padding:12px; display:inline-block;">
                <img src="<?php echo e(route('public.qr.image', $label->serial)); ?>"
                     alt="QR <?php echo e($label->serial); ?>"
                     style="width:180px; height:180px; image-rendering:pixelated;">
            </div>
            <div style="font-size:11px; color:#999; margin-top:6px;">Escaneá el QR para registrar la garantía</div>
        </div>

        <div class="section">
            <h3>Información del producto</h3>
            <div class="row">
                <span class="label-text">Producto</span>
                <span class="value-text"><?php echo e($label->product->name); ?></span>
            </div>
            <div class="row">
                <span class="label-text">Modelo</span>
                <span class="value-text"><?php echo e($label->product->productModel->name); ?></span>
            </div>
            <div class="row">
                <span class="label-text">Medidas</span>
                <span class="value-text"><?php echo e($label->product->measurements_text); ?></span>
            </div>
            <div class="row">
                <span class="label-text">Tipo</span>
                <span class="value-text"><?php echo e($label->product->productModel->type); ?></span>
            </div>
            <div class="row">
                <span class="label-text">Garantía</span>
                <span class="value-text"><?php echo e($label->product->productModel->warranty_years); ?> años</span>
            </div>
        </div>

        <div class="section">
            <h3>Información del lote</h3>
            <div class="row">
                <span class="label-text">Lote</span>
                <span class="value-text"><?php echo e($label->labelBatch->customer_batch_number); ?></span>
            </div>
            <div class="row">
                <span class="label-text">Fecha fabricación</span>
                <span class="value-text"><?php echo e($label->labelBatch->customer_batch_date->format('d/m/Y')); ?></span>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label->status === 'available' || $label->status === 'printed'): ?>
            <div style="text-align:center; margin-bottom:12px;">
                <span class="badge badge-green">✓ Disponible para registrar garantía</span>
            </div>
            <a href="<?php echo e(route('public.warranty.form', $label->serial)); ?>" class="btn">
                Registrar garantía
            </a>

        <?php elseif($label->status === 'registered'): ?>
            <div class="registered-box">
                <h3>✓ Garantía registrada</h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label->warranty): ?>
                    <p>Registrada el <?php echo e($label->warranty->created_at->format('d/m/Y')); ?></p>
                    <p>Válida hasta <?php echo e($label->warranty->warranty_end_date->format('d/m/Y')); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <a href="<?php echo e(route('public.warranty.certificate', $label->serial)); ?>" class="btn">
                Descargar certificado
            </a>

        <?php elseif($label->status === 'anulled'): ?>
            <div style="text-align:center; margin-bottom:12px;">
                <span class="badge badge-red">✕ Etiqueta anulada</span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>

    <div class="footer">
        <p>Productos Paraíso del Ecuador</p>
        <p>www.paraiso.com.ec</p>
    </div>

</div>
</body>
</html>
<?php /**PATH /Users/mariopazmino/Documents/Codex/sistema-garantias/resources/views/public/product.blade.php ENDPATH**/ ?>