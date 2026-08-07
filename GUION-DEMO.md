# Guion de la demo — Sistema de Garantías y Etiquetas

**Para:** reunión con el ingeniero que va a desplegar
**Sistema en vivo:** http://108.174.152.179/admin

---

## Antes de empezar (5 minutos antes)

1. Abre el navegador y entra a `http://108.174.152.179/admin` — deja la sesión iniciada.
2. Ten a mano las dos imágenes de etiqueta (`DEMO-1-logo-DEFAULT.png` y `DEMO-2-logo-NUEVO.png`).
3. Si el sitio no carga, avisa: *"dame un segundo, reinicio el servicio"* — no improvises explicaciones.

---

## PARTE 1 — La demo funcional (10 min)

### Arranque — una frase para abrir

> "El sistema hace tres cosas: registra los productos, genera e imprime las etiquetas con
> código único y QR, y guarda la garantía cuando el cliente final la registra. Se lo muestro
> en ese orden."

### Paso 1 — El Escritorio

**Qué haces:** entras a `/admin`, se ve la pantalla de inicio.

**Qué dices:**
> "Esta es la pantalla de resumen. Muestra cuántas etiquetas se han generado, las garantías
> activas, los lotes del mes y los productos registrados. Es la foto general del negocio."

### Paso 2 — Productos

**Qué haces:** clic en **Productos → Productos**, luego en **Crear**.

**Qué dices:**
> "Aquí se registra el producto una sola vez. Fíjense que en un mismo formulario se ingresa
> la categoría o empresa, el modelo y el producto. Antes eran tres pantallas distintas; lo
> unificamos para no repetir datos."

**Punto fuerte a destacar** — señala el campo de imagen:
> "Este campo es el logo que sale impreso en la etiqueta. Cada empresa puede tener el suyo.
> Si no se sube ninguno, el sistema pone automáticamente el logo de Paraíso."

### Paso 3 — Crear etiquetas (lo más importante)

**Qué haces:** clic en **Etiquetas → Crear etiquetas**. Muestras la lista de lotes.

**Qué dices:**
> "Un lote es un grupo de etiquetas que se fabrican juntas. Vean el código: **0826-001**.
> El 08 es el mes, el 26 el año, y el 001 es el número de lote. Cambia solo cada mes,
> nadie tiene que llevar la cuenta a mano."

**Qué haces:** abres un lote (clic en la fila).

**Qué dices:**
> "Adentro está el detalle de cada etiqueta: su código QR y su número de serie único.
> Desde aquí se manda a imprimir a la Zebra o se descarga en PDF."

### Paso 4 — La etiqueta impresa (el momento clave)

**Qué haces:** muestras las dos imágenes en pantalla, una al lado de la otra.

**Qué dices:**
> "Esta es la etiqueta real, a tamaño y resolución de impresión. La de la izquierda sale con
> el logo de Paraíso, que es el que viene por defecto. La de la derecha es exactamente el
> mismo sistema, pero con otra empresa cargada: sale su propio logo. Sin tocar nada de código."

**Si preguntan cómo se hace:**
> "Se sube el logo en la ficha de la categoría o del producto. El sistema lo convierte solo
> al formato que entiende la impresora térmica."

### Paso 5 — Bitácora y Garantías (rápido)

**Qué dices:**
> "La Bitácora guarda automáticamente todo lo que pasa con cada etiqueta: cuándo se generó,
> cuándo se imprimió, quién lo hizo. Y en Garantías aparecen los registros de los clientes
> finales cuando escanean el QR de su colchón."

---

## PARTE 2 — Preguntas del ingeniero (ten esto listo)

### "¿En qué está hecho?"

> "Laravel 12 con Filament 5 para el panel de administración, y MySQL 8 de base de datos.
> PHP 8.4. Corre en Docker."

### "¿Cómo está desplegado hoy?"

> "En un VPS Ubuntu, dentro de contenedores Docker, con Traefik como puerta de entrada
> gestionado por Dokploy. Hay tres contenedores: la aplicación, el trabajador de cola de
> impresión y MySQL."

### "¿Dónde está el código?"

> "En GitHub, rama master. Todo lo que está en producción está en el repositorio."

**Punto crítico — dilo tú antes de que pregunte:**
> "Algo importante para el despliegue: el código va **horneado dentro de la imagen Docker**,
> no montado como volumen. Lo único que es volumen es la carpeta de storage. Entonces, para
> aplicar cambios hay que **reconstruir la imagen desde el repositorio** — no basta con copiar
> archivos al servidor."

### "¿Cómo imprime?"

> "La impresora es una Zebra ZT411 a 203 puntos por pulgada, etiqueta de 95 por 200 milímetros.
> El sistema genera el código ZPL, que es el lenguaje nativo de Zebra. Hoy está conectada por
> USB, y un agente en Windows recoge los trabajos de la cola. También soporta conexión por red
> directa, cambiando la configuración."

### "¿Aguanta volumen?"

> "Lo probamos con 2000 etiquetas en un solo lote: se generan en **1 segundo**, y el archivo
> completo para imprimir se arma en **5 segundos**, usando 78 MB de memoria de los 256 MB
> disponibles. Sin duplicados. La impresión se manda en bloques de 500 para no saturar."

### "¿Tiene pruebas?"

> "217 pruebas automatizadas, todas pasando. Cubren la generación de etiquetas, el formato de
> lote, los logos, las garantías y los permisos."

### "¿Qué necesito para levantarlo en otro lado?"

> "Docker y Docker Compose. El repositorio trae el Dockerfile y el docker-compose. Hay que
> configurar el archivo de variables de entorno con los datos de la base y la zona horaria,
> que debe quedar en America/Guayaquil."

---

## PARTE 3 — Lo que está pendiente (dilo tú, no esperes que lo descubran)

> "Hay un dato que falta cargar: el **RUC del fabricante**. El campo existe y la etiqueta está
> preparada para imprimirlo, pero está vacío en la base. Necesito que me pasen el número real
> para cargarlo — no lo puse inventado porque es un dato tributario que va impreso en una
> etiqueta legal de garantía."

Esto juega a tu favor: demuestra criterio, no descuido.

---

## Si algo sale mal

| Situación | Qué decir |
|---|---|
| El sitio no carga | *"Un momento, reinicio el servicio"* — no des explicaciones técnicas improvisadas |
| Preguntan algo que no sabes | *"Déjame confirmarlo y te lo respondo hoy mismo"* — mejor que inventar |
| Sale un dato raro en pantalla | *"Eso es data de prueba que cargué para la demo"* (es cierto) |
| Preguntan por una función que no existe | *"Hoy no está, se puede agregar. ¿Es prioritario para ustedes?"* |

---

## Cierre

> "El sistema está funcionando, el código está en el repositorio y las pruebas pasan.
> Lo que necesito de su lado es el RUC para cerrar el último detalle de la etiqueta,
> y coordinar con ustedes el despliegue en el servidor definitivo."

---

## Recordatorios rápidos

- El lote **0826-001** = agosto 2026, lote 1. Rota solo cada mes.
- Cada etiqueta tiene **QR y serie únicos**, nunca se repiten.
- El logo por defecto es Paraíso; cada empresa puede tener el suyo.
- Categorías, Modelos y Composiciones **no están en el menú a propósito**: se llenan desde
  Productos. Si preguntan, esa es la respuesta.
