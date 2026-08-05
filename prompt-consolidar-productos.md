# Prompt — Consolidar Categorías+Modelos y arreglar el formulario de Productos

## Contexto
Sistema de garantías/etiquetas Zebra (Paraíso). Laravel 12 + Filament v5 + MySQL 8, en Docker (contenedor `garantias-app`) en el VPS.
Repo: `/Users/mariopazmino/Documents/Codex/sistema-garantias`. Admin: `http://108.174.152.179/admin`.

Hay DOS problemas a corregir:
1. El formulario de Crear/Editar Producto tiene **huecos grandes** (secciones en tarjetas lado a lado con alturas distintas).
2. **Categorías** y **Modelos** son dos módulos separados en el menú; deben quedar en **uno solo**, registrándose juntos.

**NO tocar** la impresión de etiquetas ni los logos dinámicos (ya entregado y funcionando).

---

## TAREA 1 — Arreglar el formulario de Producto (quitar huecos)

**Archivo:** `app/Filament/Resources/ProductResource.php`, método `form()`.

### Causa del hueco
Las secciones (`Section::make(...)`) se renderizan como tarjetas en un grid de 2 columnas. Cuando dos secciones contiguas tienen alturas distintas (ej. "Medidas" corta a la izquierda, "Materiales y conservación" alta a la derecha), queda un vacío debajo de la más corta.

### Fix
1. Forzar que TODAS las secciones ocupen el **ancho completo** y se apilen en una sola columna (sin tarjetas lado a lado):
   - En el schema raíz del form usar `->columns(1)`, **o** poner `->columnSpanFull()` en cada `Section`.
2. Dentro de cada sección, usar grids densos para aprovechar el ancho horizontal:
   - **Datos del producto** → `->columns(3)`:
     - Fila 1: `Categoría/Empresa`, `Modelo de colchón`, `Código de producto`
     - Fila 2: `Nombre del producto`, `Nombre comercial`, `Familia de producto`
     - Fila 3: `Código de barras`, `Cantidad de etiquetas (default)`, `Activo`
     - `Imagen de la etiqueta (logo)` → `->columnSpanFull()`
   - **Medidas (Plaza)** → `->columns(4)` en una sola fila: `Ancho`, `Largo`, `Alto`, `Medidas en texto`.
   - **Materiales y conservación** → `->columns(2)`: `Resortes`, `Espuma`; `Instrucciones de conservación` → `->columnSpanFull()`.
   - **Datos del fabricante** → `->columns(3)`: `Fabricante`, `RUC`, `Dirección`, `País`, `Sitio web`. Dejar `->collapsible()->collapsed()` (casi no se edita).
3. Quitar cualquier `Section::make('Plaza')` suelta que quede a media columna: fusionar/ordenar según lo de arriba.

### Aceptación
- [ ] No hay huecos verticales grandes; las secciones fluyen apiladas a ancho completo.
- [ ] En pantalla ancha cada fila usa 3–4 campos; en móvil colapsa a 1 columna (responsive).
- [ ] Se conservan todos los campos y sus defaults (fabricante, materiales, cantidad de etiquetas).
- [ ] Orden Categoría → Modelo se mantiene.

---

## TAREA 2 — Categorías + Modelos en un solo módulo

**Objetivo:** gestionar los Modelos DENTRO de cada Categoría, en una sola pantalla. El menú deja de tener "Modelos" como entrada separada.

### Pasos
1. Crear `app/Filament/Resources/CategoryResource/RelationManagers/ProductModelsRelationManager.php`:
   - `protected static string $relationship = 'productModels';` (ya existe `Category::productModels()`).
   - `$recordTitleAttribute = 'name'`.
   - **Tabla:** columnas `code`, `name`, `type`, `class`, `warranty_years`, `active` (mismas que el listado actual de Modelos, menos la columna Categoría que ahora es implícita).
   - **Formulario** (CreateAction en header + EditAction inline): `name`, `code` (unique), `type`, `class`, `warranty_years` (default 1), `active` (default true). **No** pedir `category_id` — lo asigna la relación.
2. En `app/Filament/Resources/CategoryResource.php` agregar:
   ```php
   public static function getRelations(): array
   {
       return [RelationManagers\ProductModelsRelationManager::class];
   }
   ```
3. En `app/Filament/Resources/ProductModelResource.php` ocultar del menú:
   ```php
   protected static bool $shouldHideNavigation = true;
   ```
   (Se mantiene accesible por URL, pero desaparece del sidebar. Los modelos se gestionan desde la Categoría.)
4. Verificar que el `createOptionForm` inline de `ProductResource` (crear categoría/modelo al vuelo desde el producto) **siga funcionando** — no tocarlo.

### Aceptación
- [ ] Al editar una Categoría aparece la pestaña/tabla de sus Modelos y se puede crear/editar/eliminar ahí.
- [ ] Crear un Modelo desde la Categoría asigna el `category_id` correcto automáticamente.
- [ ] El menú "Productos" queda: **Categorías** y **Productos** (Modelos ya no aparece).
- [ ] Nada de la impresión/etiquetas se rompe.

---

## Archivos

| Archivo | Cambio |
|---------|--------|
| `app/Filament/Resources/ProductResource.php` | Layout: secciones a ancho completo + grids densos (Tarea 1) |
| `app/Filament/Resources/CategoryResource/RelationManagers/ProductModelsRelationManager.php` | NUEVO (Tarea 2) |
| `app/Filament/Resources/CategoryResource.php` | Registrar `getRelations()` (Tarea 2) |
| `app/Filament/Resources/ProductModelResource.php` | `$shouldHideNavigation = true` (Tarea 2) |
| `tests/Feature/` | Test: crear modelo vía RelationManager queda ligado a la categoría; form de producto no rompe |

## Restricciones y despliegue
- Textos en **español neutro**. TDD: `php artisan test` antes y después (baseline: 208 pasan).
- NO tocar `ZebraZplRenderer`, `LabelPdfService`, `ImageToZplConverter` (impresión/logos ya entregados).
- **Despliegue Docker (VPS):** el código está horneado en la imagen `garantias-app`. Para probar: `git push` → redeploy Dokploy (rebuild), o temporalmente `docker cp` al contenedor + `composer dump-autoload -o` + `php artisan view:clear` (no sobrevive redeploy). Ver memoria `garantias/vps-docker-deploy`.

## Orden
1. Tarea 1 (formulario) — impacto visual inmediato.
2. Tarea 2 (RelationManager Modelos en Categoría).
3. Tests + verificar en VPS.
