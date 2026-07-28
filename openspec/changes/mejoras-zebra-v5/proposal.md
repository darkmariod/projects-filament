# Proposal: Mejoras al sistema de Garantías/Zebra — 5 items para entrega

## Intent

Cinco mejoras urgentes para entregar el viernes: corregir código de barras duplicado (bug), optimizar etiqueta Zebra, integrar imagen de producto en ZPL, consolidar módulos de configuración en Producto, e importar catálogo completo desde Excel. El sistema debe quedar listo para entrega y cobro.

## Scope

### In Scope

1. **Fix código de barras duplicado** — Bug fix en `SerialGeneratorService` para evitar seriales repetidos en generación por lote
2. **Reducir serial repetido en etiqueta** — Optimizar layout ZPL para mostrar serial solo una vez (evitar redundancia visual)
3. **Imagen del producto en ZPL** — Reemplazar logo Paraíso fijo con imagen real del producto, fallback a logo si no hay imagen
4. **Consolidar módulos en Producto** — Merge de `ProductModelResource`, `CategoryResource`, `TechnicalCompositionResource` dentro de `ProductResource` como campos/relaciones internas
5. **Importación de catálogo completo por Excel** — Import masivo de productos, modelos, categorías y composiciones técnicas vía Maatwebsite Excel

### Out of Scope

- Modificaciones al script Python de impresión USB
- Subida a GitHub (solo si hay pedido explícito)
- Cambios en esquema de base de datos
- Nuevos endpoints API externos

## Capabilities

### New Capabilities

- `product-image-zpl`: Renderizar imagen del producto dentro de la etiqueta ZPL, con conversión de imagen a bitmap monocromático y fallback al logo Paraíso
- `product-module-consolidation`: Consolidar módulos de configuración (Modelo, Categoría, Composición Técnica) como secciones internas del recurso Producto
- `catalog-excel-import`: Importar catálogo completo (productos, modelos, categorías, composiciones) desde archivo Excel con validación y reporte de errores

### Modified Capabilities

- `serial-aleatorio`: Fix de bug — detectar y prevenir seriales duplicados que pasan la validación de colisión existente

## Approach

Ejecutar en orden de menor a mayor riesgo:

1. **Item 1** (bajo riesgo) — Fix bug en `SerialGeneratorService`: agregar validación adicional post-generación para detectar duplicados que escapen la lógica actual de retry
2. **Item 2** (bajo riesgo) — Modificar `ZebraZplRenderer` para consolidar campos de serial en layout ZPL, verificando que el Python script USB no dependa de la repetición
3. **Item 4** (medio riesgo) — Refactorizar `ProductResource` para absorber campos de `ProductModelResource`, `CategoryResource`, y `TechnicalCompositionResource` como relaciones embebidas con gestión inline
4. **Item 5** (medio riesgo) — Crear `CatalogImport` usando Maatwebsite Excel (ya instalado), con validación por fila y rollback en errores críticos
5. **Item 3** (alto riesgo, al final) — Crear `ImageToZplConverter` para convertir imagen PNG/JPG a bitmap monocromático ZPL, integrar en `ZebraZplRenderer` con fallback a logo Paraíso. Verificar GD/Imagick en contenedor Docker

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/SerialGeneratorService.php` | Modified | Fix duplicados — agregar segunda capa de validación post-generación |
| `app/Services/ZebraZplRenderer.php` | Modified | Reducir serial repetido + integrar imagen de producto |
| `app/Services/ImageToZplConverter.php` | New | Conversión de imagen a bitmap monocromático ZPL |
| `app/Filament/Resources/ProductResource.php` | Modified | Absorber módulos de Modelo, Categoría y Composición Técnica |
| `app/Filament/Resources/ProductModelResource.php` | Removed | Consolidado en ProductResource |
| `app/Filament/Resources/CategoryResource.php` | Removed | Consolidado en ProductResource |
| `app/Filament/Resources/TechnicalCompositionResource.php` | Removed | Consolidado en ProductResource |
| `app/Imports/CatalogImport.php` | New | Importación Excel con Maatwebsite |
| `app/Filament/Resources/LabelResource/Pages/CreateLabel.php` | Modified | Ajustes menores para serial único en label |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| GD/Imagick no disponible en contenedor Docker | Medium | Verificar con `php -m` antes de item 3; fallback a logo fijo si no hay soporte |
| Refactor consolidación rompe funcionalidad existente de Modelo/Categoría | Medium | Tests de regresión antes y después; mantener resources originales como deprecated hasta validación |
| Import Excel con datos inconsistentes corrompe catálogo | Low | Validación por fila con reporte de errores; dry-run antes de commit |
| Cambio en layout ZPL rompe script Python USB | Low | El script Python lee campos por posición; verificar que la eliminación de serial repetido no afecte parsing |

## Rollback Plan

- **Items 1, 2**: Revertir commits individuales — cambios aislados en Services
- **Item 3**: Si GD no funciona, desactivar feature flag `PRODUCT_IMAGE_ZPL=false` y mantener logo fijo
- **Item 4**: Restaurar resources eliminados desde git; las migraciones son reversibles (no hay cambios de schema)
- **Item 5**: Revertir commit del import — no afecta datos existentes hasta que se ejecute

## Dependencies

- Item 3 requiere GD o Imagick en el contenedor Docker (verificar con `php -m | grep -i gd`)
- Item 5 (import) recomendado hacerse después de Item 4 (consolidación) para no importar a estructura que cambiará
- Maatwebsite Excel ya instalado en el proyecto

## Success Criteria

- [ ] Serial único sin duplicados — verificado con batch de 1000+ etiquetas
- [ ] Etiqueta Zebra muestra serial una sola vez, layout limpio
- [ ] Imagen del producto impresa en etiqueta (o logo fallback si no hay imagen)
- [ ] Módulos de Modelo, Categoría y Composición gestionados desde Producto
- [ ] Import Excel procesa catálogo de 500+ productos sin errores
- [ ] Todos los tests existentes pasan (`php artisan test`)
- [ ] Impresión Zebra USB funciona igual que antes (sin cambios en script Python)
