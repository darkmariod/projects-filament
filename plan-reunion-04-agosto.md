# Plan — Reunión Demo con el cliente (4 de agosto, 4:30 PM)

## Origen
Transcripción del audio de la grabación `Screen Recording 2026-08-04 at 4.30.50 PM.mov`
(Google Meet "Reunión Demo" — Diego Fabricio Pérez Esparza + Monkey Computer, ~99 seg).

Sistema: garantías/etiquetas Zebra (Paraíso). Laravel 12 + Filament v5, Docker en VPS.
Repo: `/Users/mariopazmino/Documents/Codex/sistema-garantias` · Admin: `http://108.174.152.179/admin`

---

## Lo que pidió el cliente (citas de la transcripción)

> "Lote siempre es el mes 08 y los últimos dos números del año, 08 y 26, ese sería el Lote siempre."
> "estamos hablando que ahorita es agosto 26, ese es el Lote y de ahí continúa el número único que usted generó."
> "Eso debería cambiarse automático cada mes."

> "Donde decía etiquetas, debería decir crear etiquetas y debería decir antes del que estaba con el símbolo de QR."
> "Debería estar abajo mejor."
> "Debería ser primero crear etiquetas y después el que es Bitácora de etiquetas y el otro que no recuerdo ahorita porque no le tomé foto."

> "yo le voy a mandar ahorita la guía antes de hacer el desarrollo... Le mando yo la guía de noche"
> "me entrego mañana a la misma hora con tal como está ahorita el sistema"

---

## TAREA 1 — Formato de lote MMYY (automático cada mes)

### Estado actual
`app/Models/LabelBatch.php` líneas 23–29:
```php
$prefix = 'LOTE-' . now()->format('Ym');   // Ym = 202608
$batch->customer_batch_number = $prefix . '-' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
// Resultado hoy: LOTE-202608-004
```
En la etiqueta se imprime como `Lote: LOTE-202608-004`.

### Lo que pide el cliente
El lote debe ser **mes + últimos 2 dígitos del año** = `0826` para agosto 2026, y a
continuación el número secuencial único. Debe rotar **solo** cada mes (septiembre → `0926`).

### Cambio a aplicar
En `LabelBatch::booted()` cambiar el formato de fecha de `Ym` (202608) a `my` (0826):
```php
$prefix = now()->format('my');            // 0826
// secuencial por mes, igual que hoy
$batch->customer_batch_number = $prefix . '-' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
// Resultado: 0826-001, 0826-002, …  → en septiembre: 0926-001
```

**Decisión pendiente de confirmar con el cliente** (afecta el string final):
- (a) `0826-001` — MMYY + guion + secuencial de 3 dígitos *(recomendado: legible, mantiene la estructura actual)*
- (b) `0826001` — todo junto sin separador
- (c) `LOTE-0826-001` — conservando el prefijo "LOTE-"

La etiqueta ya imprime la palabra "Lote:" como rótulo, por lo que repetir "LOTE-" dentro del
valor es redundante → por eso la recomendación es (a).

### Puntos de cuidado
- La consulta que busca el último secuencial (`where('customer_batch_number','like',$prefix.'-%')`)
  debe usar el **nuevo prefijo**; con `0826-%` sigue funcionando igual.
- **Datos históricos**: hay 8 lotes en el VPS con formato viejo (`LOTE-202608-004`). No se migran
  — los nuevos usan el formato nuevo. Confirmar que al cliente le sirve así (lo normal es sí,
  no se reescriben lotes ya impresos).
- `internal_batch_code` (ej. `CR-SE-080-082026`) es un código **interno distinto**, generado en
  `generateInternalCode()` línea 116 con formato `mY`. La transcripción no lo menciona →
  **no se toca** salvo que la guía diga lo contrario.

### Criterio de aceptación
- [ ] Un lote nuevo creado en agosto 2026 recibe `0826-001` (o el formato confirmado).
- [ ] El segundo lote del mismo mes es `0826-002` (secuencial correcto).
- [ ] Simulando septiembre, el prefijo cambia a `0926` y el secuencial reinicia en `001`.
- [ ] La etiqueta ZPL y el PDF imprimen `Lote: 0826-001`.
- [ ] Los 8 lotes históricos siguen intactos y visibles.
- [ ] Tests verdes (208 baseline). Los tests actuales usan valores explícitos (`CBN-001`),
      así que **no dependen** del formato auto-generado → no deberían romperse.

---

## TAREA 2 — Renombrar y reordenar el menú "Etiquetas" ⚠️ BLOQUEADA

### Estado actual
```
Etiquetas
  1. Lotes de Etiquetas      (heroicon-o-qr-code)   ← el "del símbolo QR"; aquí se CREAN
  2. Etiquetas               (heroicon-o-tag)
  3. Bitácora de etiquetas   (heroicon-o-clipboard-document-list)
```
Archivos: `LabelBatchResource.php:35,39` · `LabelResource.php:30,34` · `LabelLogResource.php:22,26`

### Lo que se entiende de la transcripción
- "Etiquetas" pasa a llamarse **"Crear etiquetas"**.
- Orden deseado: **Crear etiquetas** → **Bitácora de etiquetas** → *(el tercero)*.

### Por qué está bloqueada
El cliente se contradice sobre la posición ("antes del que estaba con el símbolo de QR" →
luego "debería estar abajo mejor") y **él mismo reconoce que no recuerda el tercer item**
("el otro que no recuerdo ahorita porque no le tomé foto").

Además hay ambigüedad de fondo: el que realmente **crea** etiquetas es *Lotes de Etiquetas*
(el del ícono QR), no *Etiquetas* (que es el listado). Renombrar "Etiquetas" → "Crear etiquetas"
podría confundir más, salvo que la intención sea fusionarlos.

**→ Esperar la guía que el cliente enviará esta noche antes de tocar la navegación.**
Es un cambio de 3 líneas; se aplica en minutos una vez llegue la guía.

### Criterio de aceptación (cuando llegue la guía)
- [ ] Labels y orden del grupo "Etiquetas" coinciden exactamente con la guía.
- [ ] Ningún recurso queda inaccesible.
- [ ] `ProductResourceConsolidationTest` sigue verde.

---

## Compromiso de entrega acordado en la reunión

> "me entrego mañana a la misma hora con tal como está ahorita el sistema"

**El cliente acordó que la entrega de mañana (5 de agosto, ~4:30 PM) es con el sistema
TAL COMO ESTÁ HOY.** Los cambios de este plan van **después** de esa entrega, cuando llegue
la guía.

Implicación práctica: **no hay que apurar estos cambios para mañana.** Lo entregable de
mañana ya está listo y pusheado (`origin/master = ea11d2c`).

---

## Orden de ejecución sugerido

1. **Ahora**: confirmar con el cliente el formato exacto del lote (opción a/b/c) — es lo único
   que bloquea la Tarea 1.
2. **Al recibir la guía (esta noche)**: aplicar Tarea 2 (menú) + cualquier punto extra de la guía.
3. Implementar Tarea 1 con TDD, correr `php artisan test`, desplegar y verificar en VPS.
4. Commit + push (obligatorio: el código está horneado en la imagen Docker; sin push, un
   redeploy de Dokploy revierte los cambios — ver memoria `garantias/vps-docker-deploy`).

## Pendiente arrastrado (no mencionado en esta reunión)

- **RUC del fabricante vacío en BD** → no se imprime en la etiqueta. Se requiere el número
  real de Productos Paraíso para cargarlo (no se puede inventar: es un ID tributario).
