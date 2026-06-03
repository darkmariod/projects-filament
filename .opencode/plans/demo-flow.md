# Plan: Demo Completa para Cliente

## Objetivo

Que el cliente pueda ver el sistema funcionando EN VIVO en 2 minutos:

1. Ver QR → escanear con celular → registrar garantía → guardar en sistema
2. Ver los archivos ZPL/PDF generados
3. Ver la data guardada en el panel admin

---

## Archivos a modificar (4 archivos)

### 1. `routes/console.php` — 1 línea

**Qué**: Agregar carga de comandos personalizados.

**Por qué**: Laravel 11 no auto-descubre `app/Console/Commands/`. Sin esto, cualquier comando Artisan que cree no funciona.

**Cambio**:

```php
// routes/console.php — al final, después del comando inspire

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ★ AGREGAR ESTO:
Artisan::command('demo:full-flow', function () {
    $this->call(\App\Console\Commands\DemoFullFlow::class);
})->purpose('Flujo demo completo para clientes');
```

---

### 2. `routes/web.php` — 1 ruta

**Qué**: Agregar ruta `/demo`.

**Por qué**: Página única con QR + tabla + export, para que el cliente no tenga que copiar URLs.

**Cambio**:

```php
// routes/web.php — al final

Route::get('/demo', function () {
    $batches = \App\Models\LabelBatch::with('labels.product.productModel')
        ->latest()->take(5)->get();
    $labels  = \App\Models\Label::with('product.productModel')
        ->latest()->take(20)->get();
    return view('demo.index', compact('batches', 'labels'));
})->name('demo.index');
```

---

### 3. `resources/views/demo/index.blade.php` — archivo nuevo

**Qué**: Página completa con:
- QR grande (centrado, 250px) apuntando a `http://{IP}:8000/p/{primer_serial}`
- Tabla con lotes generados
- Tabla con etiquetas (serial, producto, estado, links)
- Botón "Exportar Excel garantías" redirige a `/admin/warranties?export=1`
- Diseño responsive, branding rojo #8B0000, textos en español

---

### 4. `app/Console/Commands/DemoFullFlow.php` — ya está creado

**Qué**: Comando `php artisan demo:full-flow --fresh`.

**Lo que hace**:
- Crea: categoría → modelo → producto → composición técnica → setting zebra
- Genera 3 etiquetas con seriales
- Registra 1 garantía de ejemplo
- Guarda ZPL y PDF en `storage/app/demo/`
- Muestra URLs para probar
- `--fresh` limpia datos demo anteriores

**Ya existe. No necesita cambios.**

---

## Orden de implementación

```
1. routes/console.php       ← 1 línea (registrar comando)
2. routes/web.php           ← 1 ruta
3. resources/views/demo/    ← 1 vista nueva
```

---

## Cómo probar después de implementar

```bash
# 1. Regenerar autoload
composer dump-autoload

# 2. Correr demo
php artisan demo:full-flow --fresh

# 3. Servidor
php artisan serve --host=0.0.0.0

# 4. Abrir en navegador
http://localhost:8000/demo

# 5. En iPhone (misma red)
http://192.168.x.x:8000/demo
```

---

## Lo que ve el cliente en /demo

```
╔═══════════════════════════════════════════╗
║         SISTEMA DE GARANTÍAS             ║
║         PRODUCTOS PARAÍSO                ║
╚═══════════════════════════════════════════╝

[QR GRANDE — escanea con el celular]

  📱 Escaneá el QR para ver el producto
  o usá los links de abajo

────────────────────────────────────────────
  🔗 Ver producto (serial 1)
  🔗 Registrar garantía (serial 1)
  🔗 Ver certificado (serial 1)
  🔗 Exportar Excel garantías
────────────────────────────────────────────

📦  Etiquetas generadas (3)
  Serial    | Producto      | Estado     | Acción
  2605-...  | Colchón Demo  | registrado | 🔗👁️
  2605-...  | Colchón Demo  | disponible | 🔗
  2605-...  | Colchón Demo  | disponible | 🔗

📄  Archivos generados
  📎 output.zpl       → storage/app/demo/output.zpl
  📎 etiquetas-*.pdf  → storage/app/demo/etiquetas-*.pdf
  📎 certificado-*.pdf → storage/app/demo/certificado-*.pdf
```

---

## Total: 3 archivos tocados (2 existentes + 1 nuevo)
