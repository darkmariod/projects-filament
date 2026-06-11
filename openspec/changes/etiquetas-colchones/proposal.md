# Proposal: Cambios etiquetas colchones (post-reunión)

## Intent

Implementar los 5 cambios acordados en la reunión sobre el sistema de etiquetas para colchones. El cambio principal es migrar de seriales secuenciales a seriales aleatorios con dígito verificador Luhn, manteniendo el formato existente. Los cambios restantes son mejoras de UI y funcionalidad en Filament y la vista pública.

## Scope

### In Scope
- A — Serial aleatorio: reemplazar contador secuencial por 8 dígitos aleatorios + Luhn DV en `SerialGeneratorService`
- B — Highlight "número único" en vista pública `/p/{serial}`
- C — Navegación por pestañas de estado en `LabelBatchResource` list
- D — Búsqueda por nombre/lote en tabla de `LabelBatchResource`
- E — Acción de reimpresión individual en `LabelResource`

### Out of Scope
- Cambios de schema o migraciones (el formato de serial no cambia)
- UI overhaul de vistas públicas
- Modificaciones al sistema de impresión Zebra (solo ajuste menor si aplica)
- Migración de labels existentes (solo labels nuevos usan serial aleatorio)

## Capabilities

### New Capabilities
- `serial-aleatorio`: Generación de seriales con 8 dígitos aleatorios + Luhn DV manteniendo formato `YYMM-PRODUCTCODE-V-SSSSSSSS-DV`
- `public-label-page`: Vista pública `/p/{serial}` con indicador "número único" destacado
- `label-batch-navigation`: Pestañas de filtrado por estado en listado de lotes
- `label-batch-search`: Búsqueda por nombre/cliente/lote en tabla de lotes
- `label-reprint`: Reimpresión individual de etiqueta desde `LabelResource`

### Modified Capabilities
None

## Approach

**Opción A** — Serial aleatorio. En `SerialGeneratorService::generateForBatch()`, reemplazar `$lastSequence++` por 8 dígitos aleatorios vía `random_int()` con validación de unicidad (loop si hay colisión). El DV Luhn se calcula igual que hoy. Sin cambios en la DB: `sequence_number` ahora guarda 0 o un hash — se puede renombrar semánticamente después si hace falta.

Cambios B-E son modificaciones localizadas en archivos Filament y Blade. B: agregar `<div class="badge">NÚMERO ÚNICO</div>` en `product.blade.php`. C: `$table->tabs()` con statuses. D: agregar `->searchable()` a columnas faltantes. E: nueva `Action` en `LabelResource` que crea un `PrintQueueItem` individual.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/SerialGeneratorService.php` | Modified | `generateForBatch()` usa `random_int()` en vez de secuencia; `getLastSequence()` se simplifica/elimina |
| `resources/views/public/product.blade.php` | Modified | Agregar badge "NÚMERO ÚNICO" junto al serial |
| `app/Filament/Resources/LabelBatchResource.php` | Modified | Agregar `$table->tabs()` + columnas searchable adicionales |
| `app/Filament/Resources/LabelResource.php` | Modified | Nueva acción "Reimprimir" con modal de impresora |
| `app/Services/ZebraZplService.php` | Modified (posible) | Ajuste menor si la reimpresión necesita ruta individual |
| `app/Services/PrintQueueService.php` | Modified (posible) | Método `createQueueForLabel()` para reimpresión individual |
| `tests/Unit/Services/SerialGeneratorServiceTest.php` | Modified | Tests actualizados: serial aleatorio, no secuencial |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Colisión de seriales aleatorios | Low | Loop de re-intento con límite; 8 dígitos = 100M combinaciones por prefijo |
| Tests existentes fallan por cambio a aleatorio | High | Actualizar tests que verifican secuencia (`it_generates_sequential_serials`, `it_skips_existing_serials`) |
| DV calculator no funciona con aleatorio | Low | `calculateDV()` opera sobre string, no depende de secuencia |

## Rollback Plan

Revertir `SerialGeneratorService.php` a la versión anterior via git. Los labels ya generados con serial aleatorio no se ven afectados (siguen siendo válidos). Los cambios B-E son aditivos y se pueden revertir individualmente.

## Dependencies

- Ninguna externa. `random_int()` es core PHP 8.2.

## Success Criteria

- [ ] Todos los tests pasan: `php artisan test`
- [ ] Seriales nuevos usan 8 dígitos aleatorios con DV válido
- [ ] Vista pública muestra "NÚMERO ÚNICO" destacado
- [ ] LabelBatchResource tiene tabs de estado funcionales
- [ ] LabelBatchResource permite búsqueda por nombre/producto/lote
- [ ] LabelResource tiene acción de reimpresión individual
