# Plan de Desarrollo — Reunión 3 de septiembre

Plan de ejecución para las 4 tareas de `plan-reunion-03-sep.md`. Fases ordenadas,
con TDD (RED → GREEN → REFACTOR) en cada subtarea.

## Verificación previa (ya resuelta, no bloquea el arranque)

Consultado el VPS en vivo: **0 etiquetas impresas físicamente, 0 garantías reales**
(solo quedaba mi dato de prueba "Cliente Prueba", ya descartado). 67 etiquetas existen
pero todas en estado `available` (generadas, nunca enviadas a la Zebra).

→ **Corte limpio**: no hace falta mantener la ruta pública vieja en paralelo. Se migran
las 67 etiquetas existentes a la nueva URL antes de cerrar la fase 1.

---

## FASE 1 — Token público no adivinable (P0)

### Diseño (validado contra el código real)

`LabelPdfService` y `ZebraZplRenderer` **leen `$label->qr_url` ya guardado**, no lo
recalculan del `serial`. Esto acota mucho el cambio: no hay que tocar ni el renderer
ZPL ni el PDF — solo hay que asegurar que `qr_url` se construya con el token nuevo
desde el origen.

**Huella real de cambio**: migración + `SerialGeneratorService` + `routes/web.php` +
`PublicController` + backfill de las 67 etiquetas existentes + tests.

### 1.1 — Migración

`database/migrations/2026_09_04_xxxxxx_add_public_token_to_labels_table.php`
```php
$table->string('public_token', 32)->nullable()->unique()->after('serial');
```
Nullable primero (hay 67 filas existentes); se vuelve `NOT NULL` después del backfill
(1.6) en una segunda migración, o se deja nullable con validación a nivel de app si se
prefiere evitar una segunda migración — decidir al implementar según cuánto se quiera
blindar a nivel de base de datos.

### 1.2 — Generador de token seguro (TDD)

`SerialGeneratorService::generatePublicToken(): string`
- Fuente: `random_bytes()` (criptográficamente segura), NO `mt_rand`/`rand`/`uniqid`.
- Codificación: Base32 sin caracteres ambiguos (sin `0/O`, `1/I/L`) para que sea legible
  si algún día hace falta escribirlo a mano — pero el uso principal es solo dentro del QR.
- Longitud: 20 caracteres (≈100 bits de entropía) — imposible de fuerza bruta.
- Reintenta si colisiona con uno existente (igual patrón que ya usa el sequence loop).

**Tests primero (RED)**:
- Genera 5000 tokens → 0 colisiones.
- Dos tokens generados en la misma llamada/lote no comparten ningún prefijo/sufijo
  detectable (verificación estadística simple: no hay relación aritmética entre ellos).
- El token no contiene ni el `sequence_number` ni el `product_code` ni la fecha en
  ninguna forma reconocible (test de regresión directa contra el bug reportado).
- Longitud y alfabeto correctos.

### 1.3 — Persistir el token al generar etiquetas

`generateForBatch()`, `generateLabelsForBatch()`, `generateForProduct()`:
- Cada etiqueta generada recibe su `public_token`.
- `buildQrUrl()` pasa a construirse con el token: `buildPublicUrl(string $token): string`
  devuelve `{APP_URL}/p/{token}`. Se mantiene `buildQrUrl` como alias temporal si algo
  más lo usa, o se renombra y se actualizan las 2 llamadas existentes.

**Tests**: un lote de 50 etiquetas → 50 tokens únicos, cada `qr_url` contiene el token
de su propia fila (no el de otra), el `serial` interno sigue intacto y secuencial
(no se toca — sigue sirviendo para reportes internos).

### 1.4 — Rutas públicas

`routes/web.php` — 5 rutas cambian el parámetro de `{serial}` a `{token}`:
```
/qr-img/{token}
/p/{token}
/garantia/{token}/registrar   (GET y POST)
/garantia/{token}/certificado
```

### 1.5 — PublicController

`product()`, `warrantyForm()`, `warrantyStore()`, `warrantyCertificate()`, `qrImage()`:
cambiar `Label::where('serial', $serial)` → `Label::where('public_token', $token)`.

**Tests** (extender `PublicProductPageTest`, `WarrantyRegistrationTest`,
`WarrantyCertificateTest` ya existentes):
- Un `serial` real usado en la URL pública (en vez del token) → 404, no 200.
- Un token inventado con el mismo formato/longitud pero no existente → 404.
- El flujo completo (escanear → ver producto → registrar garantía → certificado)
  sigue funcionando igual con el token.

### 1.6 — Backfill de las 67 etiquetas existentes

Extender el comando existente `labels:regenerate-qr-urls` (o crear
`labels:backfill-public-tokens`) siguiendo el mismo patrón por `chunk(100)`:
para cada etiqueta sin `public_token`, generarlo y reconstruir su `qr_url`.
Correrlo en el VPS antes de cerrar la fase. Después, migración que vuelve la columna
`NOT NULL` (opcional, ver 1.1).

### 1.7 — Verificación de seguridad explícita (lo que pidió Diego, en un test)

Un test dedicado que reproduce el escenario exacto de la reunión:
```
"dados 2 tokens de etiquetas reales consecutivas (mismo lote, sequence_number N y N+1),
no debe existir ninguna transformación determinista de uno al otro"
```
Esto es el test que se le puede mostrar a Diego como evidencia de que quedó resuelto.

### 1.8 — Verificación end-to-end en VPS

Repetir el mismo patrón de pruebas ya usado en sesiones anteriores: generar un lote,
imprimir (render ZPL + PDF), escanear el QR resultante con el navegador, registrar una
garantía de prueba, confirmar 200/404 donde corresponde. Con **datos de prueba nuevos**,
no reutilizar los de la reunión.

---

## FASE 2 — Fabricación real vs planificada (P1)

### 2.1 — Accessor en `LabelBatch`

`producedCount()`: cuenta etiquetas del lote con estado `printed` o `registered`.
`cancelledBeforePrintCount()`: cuenta las `anulled` que nunca llegaron a `printed`.

### 2.2 — Mostrar en la tabla y el PDF/Excel

`LabelBatchResource` (tabla): columna nueva "Producidas" junto a "Cantidad" (planificada).
`LabelBatchesExport`: agregar esa misma columna al Excel.

### 2.3 — Dashboard

Revisar `DashboardStatsOverview` / `BatchesChart`: confirmar que solo cuentan
`printed`/`registered`, no `available` ni `anulled`, para "Lotes generados" y
similares. Ajustar si hace falta.

### 2.4 — Tests

Lote de 100 con 30 anuladas antes de imprimir y 70 marcadas como impresas →
`producedCount()` = 70, el dashboard no reporta 100.

---

## FASE 3 — Texto de ayuda del logo (quick win, 5 min)

`ProductResource.php`, campo `image`:
```php
->helperText('Este logo reemplaza al de Paraíso en la etiqueta impresa de este producto.')
```
Sin tests nuevos — es solo texto de UI. Verificación visual en el VPS.

---

## FASE 4 — Agente de impresión (no es código)

No entra en este ciclo de desarrollo. Se deja como checklist aparte:
- [ ] Contactar a la persona con acceso a la máquina de producción.
- [ ] Reinstalar/verificar `zebra-agent.ps1`.
- [ ] Coordinar con Robert la prueba en vivo antes de imprimir en planta.

---

## Orden de ejecución

1. **Fase 1** completa (1.1 → 1.8) — es la que tiene impacto de seguridad real.
2. **Fase 3** (5 minutos) — se intercala en cualquier momento libre.
3. **Fase 2** — reporte, no bloquea nada urgente.
4. **Fase 4** en paralelo, por tu lado, con Robert.

## Restricciones

- TDD estricto en Fase 1: cada subtarea con test que falla primero.
- No romper la impresión ni el registro de garantías ya validado en sesiones previas.
- Correr `php artisan test` completo antes y después de cada fase (línea base: 221).
- Commit + push obligatorio al cerrar cada fase (el código va horneado en la imagen
  Docker del VPS — sin push, un rebuild revierte los cambios).
- Desplegar y verificar en el VPS al cerrar la Fase 1 antes de pasar a la Fase 2.
