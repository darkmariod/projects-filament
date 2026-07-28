# Tasks: Mejoras Etiquetas v6

## Phase 1: Datos dinámicos en stickers

### 1.1 Actualizar extractData()
- [x] Agregar campos del LabelBatch: `generated_by_name`, `sequence_number`, `internal_batch_code`
- [x] Verificar que `operator`, `customer_batch_number`, `customer_batch_date` ya están mapeados

### 1.2 Actualizar buildQualitySticker()
- [x] Sticker 1: mostrar operador real, lote y fecha
- [x] Sticker 2: mostrar operador real, código interno

### 1.3 Actualizar buildComposition()
- [x] Verificar que muestra operador, lote y fecha correctos

### 1.4 Tests de datos dinámicos
- [x] Test: sticker 1 contiene datos del batch
- [x] Test: sticker 2 contiene datos del batch
- [x] Test: composición contiene operador
- [x] Test: datos vacíos no rompen el ZPL

## Phase 2: Trazabilidad en sticker 2

### 2.1 Actualizar buildQualitySticker() para sticker 2
- [x] Agregar serial del label
- [x] Agregar secuencia del label
- [x] Agregar código interno del lote
- [x] Agregar fecha de generación
- [x] Agregar usuario que generó el lote

### 2.2 Tests de trazabilidad
- [x] Test: sticker 2 contiene serial y secuencia
- [x] Test: sticker 2 contiene usuario generador
- [x] Test: sticker 2 contiene código interno
- [x] Test: serial no aparece más de 2 veces en el ZPL

## Phase 3: Logo por categoría

### 3.1 Migración y modelo
- [x] Crear migración para categories.logo
- [x] Actualizar Category model (fillable, allowedFileUploadTypes)
- [x] Ejecutar migración

### 3.2 CategoryResource
- [x] Agregar FileUpload para logo en CategoryResource
- [x] Configurar disco public, directorio categories

### 3.3 Actualizar fallback chain en ZebraZplRenderer
- [x] Actualizar buildMainLabel() para usar lógica de prioridad
- [x] Prioridad: producto → categoría → Paraíso genérico → texto
- [x] Reutilizar ImageToZplConverter para logos de categoría

### 3.4 Tests de logo por categoría
- [x] Test: producto con imagen usa imagen del producto
- [x] Test: producto sin imagen, categoría con logo usa logo de categoría
- [x] Test: producto sin imagen, categoría sin logo usa logo Paraíso
- [x] Test: sin logo ni imagen muestra texto "PARAÍSO"

## Phase 4: Etiqueta de prueba mejorada

### 4.1 Crear renderTestLabel()
- [x] Método acepta producto opcional
- [x] Header: "ETIQUETA DE PRUEBA"
- [x] Logo: categoría del producto (si existe)
- [x] Datos: nombre, código, medidas
- [x] Conexión: IP/puerto o USB
- [x] Fecha/hora actual
- [x] Borde visible

### 4.2 Actualizar ZebraPrintSettingResource
- [ ] Modificar acción "Test de impresión"
- [ ] Agregar select opcional de producto
- [ ] Si hay producto: generar con logo de categoría
- [ ] Si no hay producto: generar etiqueta básica

### 4.3 Tests de etiqueta de prueba
- [x] Test: etiqueta con producto contiene datos del producto
- [x] Test: etiqueta sin producto es retrocompatible
- [x] Test: etiqueta tiene borde visible

## Phase 5: Verificación final

### 5.1 Ejecutar suite completa
- [x] php artisan test
- [x] Verificar que todos los tests pasan
- [x] Documentar pre-existing failures

### 5.2 Verificar manualmente
- [x] Revisar que el ZPL generado es válido
- [x] Verificar que la jerarquía de logos funciona
- [x] Confirmar retrocompatibilidad
