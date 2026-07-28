# Design: Mejoras Etiquetas v6

## Architecture Decisions

### 1. Datos dinámicos en stickers
**Decisión**: Actualizar `extractData()` para incluir todos los campos del LabelBatch necesarios para los stickers.

**Razón**: Los datos ya existen en el modelo, solo falta mapearlos al array que usa el renderer.

**Cambios**:
- `extractData()`: agregar `operator`, `customer_batch_number`, `customer_batch_date`, `internal_batch_code`, `generated_by_name`, `sequence_number`
- `buildQualitySticker()`: usar los nuevos campos del array
- `buildComposition()`: verificar que ya usa los campos correctos

### 2. Trazabilidad en sticker 2
**Decisión**: El sticker 2 muestra serial, secuencia, lote interno, fecha de generación y usuario generador.

**Razón**: Estos datos ya están en el modelo Label y LabelBatch, solo hay que mostrarlos.

**Layout del sticker 2**:
- Columna izquierda: serial, secuencia
- Columna centro: código interno, fecha generación
- Columna derecha: usuario generador, firma

### 3. Logo por categoría
**Decisión**: Agregar campo `logo` (string, nullable) a la tabla `categories` via migración.

**Razón**: 
- El sistema ya tiene `ImageToZplConverter` que puede reutilizarse
- El campo `Product.image` ya existe y funciona
- Mantener consistencia con el patrón existente

**Fallback chain**:
1. `$product->image` → ImageToZplConverter
2. `$product->productModel->category->logo` → ImageToZplConverter
3. `resources/zpl/paraiso-logo.gfa` → zplGraphic()
4. Texto "PARAÍSO" como último recurso

### 4. Etiqueta de prueba mejorada
**Decisión**: Crear método `renderTestLabel()` en ZebraZplRenderer que acepta un producto opcional.

**Razón**: 
- Separar lógica de etiqueta normal vs prueba
- Retrocompatible: sin producto = etiqueta básica actual
- Reutilizar ImageToZplConverter para el logo

**Estructura de la etiqueta de prueba**:
- Header: "ETIQUETA DE PRUEBA" centrado
- Logo: categoría del producto (si existe)
- Datos: nombre, código, medidas del producto
- Conexión: IP/puerto o USB
- Fecha/hora actual
- Borde visible para verificar alineación

## Technical Plan

### Files to create/modify
1. `database/migrations/xxxx_add_logo_to_categories_table.php` — nueva migración
2. `app/Models/Category.php` — agregar logo a fillable
3. `app/Filament/Resources/CategoryResource.php` — agregar FileUpload
4. `app/Services/ZebraZplRenderer.php` — actualizar extractData(), buildQualitySticker(), buildComposition(), nuevo renderTestLabel()
5. `app/Services/ImageToZplConverter.php` — sin cambios (ya funciona)
6. `app/Filament/Resources/ZebraPrintSettingResource.php` — mejorar acción de test
7. Tests en tests/Unit/Services/ZebraZplServiceTest.php

### Execution Order
1. Migración + Modelo Category
2. CategoryResource (FileUpload)
3. ZebraZplRenderer — extractData() actualizado
4. ZebraZplRenderer — buildQualitySticker() actualizado
5. ZebraZplRenderer — fallback chain de logo
6. ZebraZplRenderer — renderTestLabel()
7. ZebraPrintSettingResource — acción de test mejorada
8. Tests para cada cambio