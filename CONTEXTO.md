# Contexto del proyecto — Sistema de Garantías, Productos Paraíso

> Pegá este archivo al inicio de una sesión nueva para retomar el trabajo sin
> volver a explicar nada. Última actualización: 10 de septiembre de 2026.

---

## Qué es el sistema

Laravel 12 + Filament v5 + MySQL 8, en Docker sobre un VPS. Genera etiquetas
para colchones Paraíso, las manda a imprimir a una Zebra ZT411 en la planta, y
gestiona el registro de garantías que hace el cliente final escaneando el QR.

Volumen real: **más de mil etiquetas diarias**.

### Acceso

| Qué | Dónde |
|---|---|
| Panel de administración | `http://108.174.152.179/admin/login` — **puerto 80** |
| API del agente de impresión | `http://108.174.152.179:8081/api/agent/*` — **puerto 8081** |
| Usuario | `admin@paraiso.com` / `password123` |
| Clave del agente | `zebra-agent-key-2026` (cabecera `X-Agent-Key`) |
| Servidor | `ssh -p 22022 root@108.174.152.179` |
| Carpeta de despliegue | `/opt/sistema-garantias` (no es un clon de git) |
| Repositorio | `github.com:darkmariod/projects-filament` rama `master` |

**Los puertos no son intercambiables.** El panel solo responde por el 80 y la
API del agente solo por el 8081. Entrar al panel por el 8081 hace que el ingreso
falle en silencio: `APP_URL` no lleva puerto, así que Filament genera el destino
del formulario hacia el 80 y el navegador bloquea la petición por ser otro
origen.

---

## Lo que pidió el cliente en la reunión del 3 de septiembre

Salió de siete grabaciones de pantalla con Diego revisando el sistema en vivo.

### Tarea 1 — Que el serial no se pueda adivinar `RESUELTO`

Diego volvió sobre esto en tres grabaciones distintas:

> «Debemos garantizarnos que nadie viendo el serial y viendo el QR pueda
> descifrar cuál va a ser el secuencial del siguiente colchón… que no sea
> secuencial, sino único, aleatorio, que sea hiperaleatorio.»
>
> «Esto más o menos debe ser la lógica de las tarjetas de crédito.»

El riesgo era concreto y estaba demostrado: el dígito verificador del serial se
calculaba con datos públicos y sin clave secreta, así que **viendo una sola
etiqueta impresa se podía construir el siguiente serial válido** y reclamar la
garantía de un colchón que todavía no se fabricaba.

**Cómo quedó.** Se separaron dos cosas que antes eran la misma:

- El **serial** sigue siendo correlativo (`2609-PRUEBA-V-00000001-4`). Producción
  lo necesita para trazabilidad, y va impreso en la etiqueta y en el código de
  barras.
- El **código público** es lo único que viaja en el QR y en las URLs: 20
  caracteres generados con `random_bytes` sobre un alfabeto de 32 sin caracteres
  que se confundan al dictarlos (sin `0`, `O`, `1`, `I`, `L`).

Son `32^20 ≈ 2^100` combinaciones. Con diez años de producción cargada —tres
millones y medio de etiquetas— y probando mil por segundo, acertar una tomaría
del orden de **10^13 años**. El universo tiene 1,4 × 10^10.

Además las consultas públicas tienen tope de 60 por minuto y por IP, para que
nadie pueda barrer el sistema ni saturarlo, y el envío del formulario de
garantía tiene tope de 3 por minuto.

Verificado contra el servidor:

```
/p/{código del QR}      → 200
/p/{serial impreso}     → 404
```

Archivos: `SerialGeneratorService::generatePublicToken()`, `PublicController`,
`routes/web.php`, migración `2026_09_03_000001_add_public_token_to_labels_table`.
Pruebas: `tests/Feature/PublicTokenSecurityTest.php`.

### Tarea 2 — Etiquetas generadas que no se produjeron `A MEDIAS`

Escenario que describió el cliente: se planifica un lote de 100, se fabrican 70
porque entró una producción urgente, y las 30 restantes se retoman el lunes con
la fecha equivocada.

Decidió en la propia reunión: anular las no producidas y crear un lote nuevo el
día que se retome.

**La función de anular ya existía** antes de la reunión. Está en *Crear
etiquetas → abrir el lote → pestaña de etiquetas*, y en la demo se buscó dentro
de la Bitácora, que es solo de consulta. Fue confusión de ubicación.

**Lo que falta:** un lote de 100 con 30 anuladas todavía figura como «100
fabricadas» en el tablero y en el Excel. Hay que distinguir planificadas de
producidas de verdad.

### Tarea 3 — Que la impresión arranque sola con la computadora `ENTREGADO`

El script que había dejado un ingeniero anterior se perdió. Se rehízo completo
en `scripts/agente-zebra-python/`:

```
python setup.py              instala
python setup.py estado       muestra si funciona
python setup.py desinstalar  saca el arranque automático
```

Va en **`C:\agente-zebra`**, no en el escritorio: la tarea corre con la cuenta de
sistema de Windows, que puede perder acceso a las carpetas del perfil de usuario
y dejar de funcionar tras reiniciar.

Tres seguros lo mantienen vivo: arranca con la computadora, un vigilante lo
revisa cada minuto, y un candado por socket impide copias duplicadas imprimiendo
etiquetas repetidas. Si al encender no hay internet todavía, espera en lugar de
cerrarse.

Guía para la planta: `scripts/agente-zebra-python/GUIA-INSTALACION.pdf`.

**Probado en planta el 10 de septiembre**: armaron un lote de cinco etiquetas y
el circuito se cerró entero — panel, cola, agente, impresora y confirmación de
vuelta.

### Tarea 4 — Cambiar el logo de la etiqueta por marca `RESUELTO`

> «Cargar el logo de la policía nacional y quitar Paraíso, ¿cómo le hago?»

No era que no se encontrara dónde subirlo: **el logo se subía y no se guardaba**.
Al modelo de categorías le faltaba declarar ese campo, así que Laravel descartaba
el valor en silencio. La columna existía en la base desde julio.

Orden en que se elige el logo de la etiqueta:

1. Imagen del producto
2. Logo de la categoría
3. `resources/zpl/paraiso-logo.gfa`
4. Texto «PARAISO»

**Una foto no sirve para una etiqueta térmica.** El ZPL no tiene grises: una foto
de colchón queda 97% tinta y sale como una mancha negra. Solo funcionan imágenes
de alto contraste, negro sobre blanco. Por eso los productos van sin imagen y la
etiqueta usa el logo de Paraíso.

---

## Defectos encontrados que nadie había pedido

Ninguno daba error en pantalla. Todos hacían mal las cosas en silencio.

| Defecto | Qué provocaba |
|---|---|
| El servidor corría código fuera de todo commit | Las etiquetas salían con una fila de firmas vacías eliminada el 2 de septiembre |
| Cabecera `^GFA` con el campo equivocado | Se enviaba la altura donde van los bytes por fila: una imagen de 288 puntos se dibujaba como 960 y quedaba fuera del papel |
| Hexadecimal sin relleno | `dechex(0)` devuelve `"0"` y no `"00"`: cada byte menor a 16 desplazaba el resto de la imagen |
| Colas en `processing` invisibles | Un lote interrumpido no volvía a ofrecerse nunca. Reproducido: al cortarse la red en la etiqueta 212 de 1000, quedaron 788 sin imprimir |
| Contador que no distinguía avisos repetidos | Una cola de 5 etiquetas llegó a reportar 16 impresas |
| Se pisaba la fecha de compra | La garantía se contaba desde el día del registro. Comprando en mayo y registrando en septiembre, al cliente le sacaban esos meses de una garantía de ocho años |
| El panel escribía en inglés | El idioma estaba en `en`; las traducciones ya venían en Filament, solo faltaba pedirlas |

---

## Cómo funciona la impresión

```
Panel → Lotes de Etiquetas → "Imprimir en Zebra"
  → PrintQueueService arma la cola y congela el ZPL de cada etiqueta
    → el agente consulta GET /api/agent/pending cada 10 segundos
      → imprime por win32print
        → POST /api/agent/{cola}/item/{item}/complete   (una por etiqueta)
        → POST /api/agent/{cola}/complete
```

**El ZPL se congela al armar la cola.** Reimprimir un lote viejo saca el formato
que tenía ese día, no el actual. Para probar un cambio hay que crear un lote
nuevo.

**El cuello de botella es el reporte, no la impresión.** El agente confirma una
etiqueta por petición: mil etiquetas son unos veinte minutos solo de
confirmaciones. Medido contra el servidor:

| Paso | Tiempo |
|---|---|
| Generar 1000 seriales | 0,92 s |
| Armar la cola y el ZPL | 2,78 s |
| Peso del envío | 11,7 MB |
| Confirmar las 1000 | ~20 min |

El endpoint `pending` **no tiene paginación**: devuelve todos los ítems de todas
las colas pendientes juntos. Y solo atiende colas de tipo `usb`; una cola creada
como `network` no la ve nunca.

---

## Lo que falta

1. **Reporte de planificadas contra producidas** — lo que queda de la Tarea 2.
2. **Paginar el endpoint `pending`** — hoy manda 11,7 MB de una vez.
3. **Reportar en bloque** — una petición por etiqueta es lo que hace que mil
   tarden veinte minutos.
4. **Preparar el despliegue en el servidor del cliente.**

---

## Reglas de trabajo

**Todo cambio sale del repositorio y baja por despliegue.** El servidor llegó a
tener nueve archivos que no existían en ningún commit, incluida la seguridad del
serial completa: estaba a un despliegue de perderse. Sincronizar no es
reemplazar, es fusionar — al traer un archivo del servidor se perdió un arreglo
que solo tenía el repositorio, y lo detectó una prueba.

**Antes de tocar la base, respaldo.** Los respaldos van en
`/root/backups-garantias/`.

**Las pruebas se corren antes y después.** Línea base actual: **236 pasan**. Una
prueba que no falla cuando se revierte el arreglo no sirve — verificarlo siempre.

### Despliegue

```bash
# 1. enviar los archivos cambiados
scp -P 22022 <archivos> root@108.174.152.179:/tmp/

# 2. instalarlos y reconstruir
ssh -p 22022 root@108.174.152.179 'cp /tmp/<archivo> /opt/sistema-garantias/<ruta>/ && cd /opt/sistema-garantias && docker compose up -d --build app'
```

El código va horneado en la imagen: solo `storage` es volumen, así que **hay que
reconstruir**, no basta con copiar. Si se tocan imágenes, limpiar además
`storage/app/zpl-cache/`.
