# Plan — Reunión con el cliente, 3 de septiembre (7 grabaciones)

## Origen

Transcripción de audio (whisper) de 7 grabaciones de pantalla, reunión en vivo con el
cliente (Diego) revisando el sistema en producción, ~14.5 minutos totales.

Sistema: garantías/etiquetas Zebra (Paraíso). Laravel 12 + Filament v5, Docker en VPS.
Repo: `/Users/mariopazmino/Documents/Codex/sistema-garantias`

---

## TAREA 1 — 🔴 SEGURIDAD DEL SERIAL (prioridad máxima — insistencia repetida de Diego)

### Lo que pidió el cliente (rec 4, 5 y 6 — el tema al que volvió tres veces)

> "debemos garantizarnos que nadie viendo esto, viendo el serial y viendo el QR, pueda
> descifrar cuál va a ser el secuencial a serie del siguiente colchón... comienza a
> mandarme señoriales que efectivamente van a existir"

> "el número validador... para que no sea secuencial, sino que sea único, aleatorio...
> que sea hiperaleatorio"

> "esto más o menos debe ser la lógica de las tarjetas de crédito... no todas tienen
> secuencia, no tienen una variación entre cada tarjeta que se va creando"

El riesgo que describe es concreto: alguien que ve **una sola etiqueta impresa** puede
calcular el siguiente serial válido y reclamar la garantía de un colchón que todavía no
se fabricó ni se vendió.

### Verificado en el código — el riesgo es real

`app/Services/SerialGeneratorService.php`:
```
serial = {YYMM}-{PRODUCTCODE}-V-{SECUENCIA incremental de 8 dígitos}-{DV}
```
- La secuencia es **consecutiva** (`$lastSequence++`).
- El dígito verificador (`calculateDV`) se calcula con un algoritmo tipo Luhn sobre
  `yymm + productCode + line + sequence` — **todo dato público**, sin ninguna clave
  secreta. Cualquiera puede reproducir la fórmula y calcular el DV correcto para un
  serial inventado.
- El QR (`buildQrUrl`) codifica el **serial crudo**: `/p/{serial}`.
- `PublicController::product()` hace `Label::where('serial', $serial)->firstOrFail()`
  — si alguien adivina un serial válido, el sistema responde 200 con toda la info del
  producto y lo deja avanzar al formulario de garantía. No hay nada que distinga un
  serial "adivinado pero nunca impreso" de uno legítimo.

Con dos etiquetas de muestra (como probó Diego en rec 6) ya se ve el patrón completo.

### Diseño propuesto

Separar dos cosas que hoy son la misma: el **serial interno** (trazabilidad de
producción — lote, secuencia, fecha) y el **código público** (lo que va en el QR y lo
que puede reclamar una garantía).

1. Mantener `serial` como está (secuencial, sirve para reportes internos, Excel, bitácora).
2. Agregar un campo nuevo `public_token` en `labels`: string aleatorio criptográfico
   (ej. 20-24 caracteres, generado con `random_bytes`/`Str::random` de fuente segura,
   no con `mt_rand` ni con datos derivables), único, indexado.
3. El QR y la URL pública (`/p/{token}`, `/garantia/{token}/registrar`) usan el
   `public_token`, no el `serial`.
4. `PublicController` busca por `public_token` en vez de `serial`.
5. El código de barras (Code128) puede seguir mostrando el `serial` legible para
   producción (no es el vector de ataque — el ataque es el QR/URL pública), o también
   migrar al token si se prefiere uniformidad total.
6. Analogía con tarjetas de crédito que mencionó Diego: aplica bien — el número interno
   (secuencia) sigue existiendo para el banco, pero el que circula no permite inferir
   el siguiente.

### Puntos a confirmar con Diego antes de implementar

- ¿El código de barras (que ve el operario en planta) puede seguir mostrando el serial
  secuencial, o también debe ocultarse? (el de barras no es visible al cliente final
  del mismo modo que el QR, pero conviene preguntarlo explícitamente).
- Longitud/formato del token aceptable para el tamaño del QR en la etiqueta actual.

### Criterio de aceptación
- [ ] `labels.public_token` nuevo, único, generado con fuente criptográficamente segura.
- [ ] Ver 2 tokens consecutivos no permite predecir ni el tercero ni ningún otro.
- [ ] `/p/{token}` y `/garantia/{token}/registrar` usan el token, no el serial.
- [ ] Un serial o token inventado que no exista en la base sigue devolviendo 404 (ya
      es así vía `firstOrFail()`), pero ahora no hay forma de *construir* uno válido.
- [ ] Test: generar 1000 tokens, verificar 0 colisiones y que no siguen ningún orden
      con relación a `sequence_number`.
- [ ] La etiqueta (ZPL y PDF) sigue imprimiendo bien — QR con el token, resto igual.
- [ ] No se rompe la impresión ya entregada (regresión sobre lo actual).

---

## TAREA 2 — Etiquetas generadas pero no producidas (fecha real / anular y regenerar)

### Lo que describió el cliente (rec 1 y 2)

Escenario real: lote planificado de 100, solo se fabricaron 70 ese día porque entró una
producción urgente que interrumpió el trabajo; las 30 restantes se retoman recién el
lunes. Esas 30 etiquetas ya se generaron con la fecha de hoy.

Dos preguntas del cliente:
1. ¿Qué hacer con esas 30 para que no queden con la fecha equivocada cuando se impriman
   el lunes?
2. Si al final no se necesitan (cambia la cantidad real), ¿cómo darlas de baja sin que
   cuenten como "fabricadas y activadas"?

Decisión que tomó el cliente en la propia reunión (rec 2):
> "sería mejor ese número eliminarlos y crear y darlos de baja y al día siguiente
> volver desde cero" — prefiere anular las no producidas y crear un lote nuevo el
> día que realmente se retome, con fecha y códigos nuevos, antes que pausar y reusar
> las mismas.

### Verificado en el código

- **La función de anular YA EXISTE** y funciona bien: `LabelsRelationManager.php`
  tiene `anular` (individual y en bloque) sobre etiquetas en estado `available` o
  `printed`, bloquea si ya tiene garantía registrada, y deja rastro en `LabelLog`
  (Bitácora). Está dentro de **Crear etiquetas → abrir el lote → pestaña de
  etiquetas**, no dentro de la Bitácora.
- **La confusión del cliente y de Mario en la reunión fue de ubicación**: buscaron esta
  función *dentro* de la Bitácora (`LabelLogResource`, que hoy es de solo lectura,
  `->actions([])`) cuando ya existe en el lugar correcto. Arquitectónicamente la
  Bitácora debe seguir siendo de solo lectura (es el historial/auditoría); no conviene
  agregarle CRUD.
- **Lo que sí falta**: cuando se anulan etiquetas no producidas de un lote, el lote
  sigue "diciendo" que su `quantity` planificada era 100, sin distinguir cuántas se
  anularon antes de imprimirse. Eso afecta reportes y al Excel de exportación.

### Cambios a realizar

1. **No tocar la Bitácora** (correcto que sea solo lectura). En vez de eso, reforzar
   que el flujo real —abrir el lote en "Crear etiquetas" → pestaña de etiquetas→
   anular las que no se produjeron— quede claro. Esto ya funciona; se documenta el
   flujo para no volver a confundirlo en la próxima demo.
2. Agregar al listado/exportación de `LabelBatchResource` una columna o indicador de
   **cantidad real anulada antes de imprimir** vs **cantidad efectivamente impresa**,
   para que un lote de 100 con 30 anuladas no aparezca como "100 fabricadas".
3. Verificar que las etiquetas anuladas por este motivo (no producidas) no se cuenten
   en los widgets del dashboard como producción completada.

### Criterio de aceptación
- [ ] Un lote con etiquetas anuladas antes de imprimir muestra claramente cuántas se
      produjeron de verdad vs cuántas se planificaron.
- [ ] El dashboard/exportación no reporta como fabricadas las etiquetas anuladas.
- [ ] Crear un lote nuevo al día siguiente para el remanente funciona con el flujo
      normal (ya funciona; solo se verifica que herede el nuevo esquema de seguridad
      de la Tarea 1).

---

## TAREA 3 — Reconfigurar el agente de impresión (logística, no código)

### Lo que dijo el cliente (rec 2)

El ingeniero Daniel había dejado instalado un script en la computadora de la persona
encargada de producción, de forma que al prender la máquina, todo funciona sin clics
extra. Mario perdió el contacto con esa persona y necesita reinstalarlo — estima 1 hora
de trabajo coordinado. Además mencionan coordinar con **Robert** para hacer la prueba
en vivo con la impresora real antes de salir a producción.

### Qué hacer
Esto no es una tarea de desarrollo del sistema — es coordinación y soporte in-situ.
Queda fuera del alcance de este plan de código, pero se deja registrado como pendiente
crítico para el "vamos a salir en vivo": sin esto reinstalado, la impresión automática
no funciona en la máquina de planta aunque el sistema esté listo.

**Acción sugerida**: coordinar con Robert la fecha de la prueba en vivo, y tener a mano
la guía/documentación del script de impresión (ver `zebra-agent.ps1` en el repo) para
agilizar la reinstalación.

---

## TAREA 4 — Logo dinámico por marca/categoría (ya existe — solo repaso)

### Lo que preguntó el cliente (rec 7)

> "cargar el logo de la policía nacional y quitar paraíso, ¿cómo le hago para hacer eso?"

Mario, algo oxidado ("no he estado tocando el sistema mucho"), tardó en ubicar dónde se
sube. Es la función de logo por producto/categoría que ya se implementó y probó en
sesiones anteriores (fallback: imagen del producto → logo de categoría → Paraíso).

### Qué hacer
No requiere código nuevo. Se sugiere:
- Repasar el flujo antes de la próxima demo: **Productos → editar producto → campo
  "Imagen de la etiqueta (logo)"**.
- Si se quiere reforzar la claridad para que no vuelva a costar encontrarlo, se puede
  agregar un texto de ayuda (`helperText`) más explícito en ese campo, del estilo
  "Este logo reemplaza al de Paraíso en la etiqueta impresa de este producto."

---

## Orden de ejecución sugerido

1. **Tarea 1 (seguridad del serial)** — la más urgente; el cliente insistió en esto en
   tres grabaciones distintas y es un riesgo de fraude real y ya demostrado.
2. **Tarea 2 (fecha/anulación de lotes parciales)** — mayormente ya funciona; el
   trabajo real es de reportes y de aclarar el flujo, no de construir desde cero.
3. **Tarea 4 (helperText del logo)** — cambio cosmético de 5 minutos, se puede hacer
   junto con cualquiera de las anteriores.
4. **Tarea 3 (agente de impresión)** — coordinación con Robert, en paralelo, no bloquea
   el desarrollo.

## Restricciones

- No romper la impresión ni las etiquetas ya entregadas y aprobadas por el cliente.
- El cambio de la Tarea 1 es el más delicado: toca la URL pública que ya puede estar
  circulando en etiquetas impresas. Definir si conviene aceptar *ambas* rutas
  (`/p/{serial}` y `/p/{token}`) por un tiempo de transición, o si como el sistema
  recién sale a producción no hay etiquetas reales en la calle todavía y se puede
  cortar limpio. **Esto se confirma con el cliente antes de implementar.**
- TDD: correr `php artisan test` antes y después (línea base: 221 pasan).
- Despliegue Docker: el código va horneado en la imagen `garantias-app`; recordar
  commit + push + redeploy para que sobreviva un rebuild (ver memoria
  `garantias/vps-docker-deploy`).
