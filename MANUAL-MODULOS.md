# Manual del sistema — Explicación de cada módulo

**Sistema de Garantías y Etiquetas — Productos Paraíso**

Este documento explica, en palabras sencillas, para qué sirve cada sección del sistema.
Está pensado para cualquier persona de la empresa, sin necesidad de conocimientos técnicos.

---

## ¿Qué hace el sistema, en resumen?

El sistema se encarga de tres cosas:

1. **Guardar la información de los productos** que fabrica la empresa (colchones y demás).
2. **Generar e imprimir las etiquetas** que van pegadas a cada producto, con su código único y su código QR.
3. **Registrar la garantía** cuando el cliente final escanea el QR de su producto.

Cada etiqueta que sale de la impresora tiene un número único. Ese número permite saber después
qué producto es, cuándo se fabricó, quién lo hizo y si el cliente ya registró su garantía.

---

## Escritorio

Es la pantalla de bienvenida, la primera que aparece al entrar.

Muestra un resumen rápido del estado del negocio: cuántas etiquetas se han generado, cuántas
garantías están activas, cuántos lotes se produjeron en el mes y cuántos productos hay
registrados. También incluye gráficos para ver la evolución mes a mes.

Sirve para tener una foto general sin entrar a revisar cada sección.

---

## Productos

Aquí se registra todo lo que la empresa fabrica.

Cada producto se ingresa **una sola vez** con todos sus datos: nombre, código, medidas,
materiales (resortes, espuma), instrucciones de cuidado y datos del fabricante. También se
indica a qué **categoría o empresa** pertenece y qué **modelo** es.

Una parte importante: aquí se sube la **imagen del logo** que saldrá impresa en la etiqueta.
Esto permite que cada marca o empresa tenga su propio logo en sus etiquetas. Si no se sube
ninguno, el sistema usa automáticamente el logo de Paraíso.

Al guardar un producto nuevo, el sistema crea solo la categoría y el modelo si no existían,
y prepara de una vez el primer lote de etiquetas. No hay que ir a otras pantallas.

---

## Etiquetas

Es la sección del día a día en producción. Tiene dos partes:

### Crear etiquetas

Aquí se generan las etiquetas para producir. Se elige el producto, se indica cuántas etiquetas
se necesitan y el sistema arma un **lote**.

Un lote es simplemente un grupo de etiquetas fabricadas juntas. Cada lote recibe un número
que indica el mes y el año —por ejemplo **0826-001** significa "agosto de 2026, lote número 1"—
y ese número se reinicia solo cada mes.

Dentro de cada lote se puede ver la lista de todas sus etiquetas, cada una con su **código QR**
y su número de serie único. Desde aquí también se manda a imprimir a la impresora Zebra, se
descarga el archivo en PDF, o se anula una etiqueta si salió dañada en producción.

### Bitácora de etiquetas

Es el historial. Registra automáticamente todo lo que pasa con cada etiqueta: cuándo se generó,
cuándo se imprimió, si se anuló y quién lo hizo.

Sirve como respaldo para saber qué ocurrió y cuándo, sin depender de la memoria de nadie.
Se puede exportar a Excel.

---

## Garantías

Aquí se ven las garantías que los clientes finales han registrado.

Cuando una persona compra un colchón, escanea el código QR de la etiqueta, ingresa sus datos y
queda registrada su garantía. Esa información aparece en esta sección: qué producto es, quién lo
compró, en qué almacén, con qué factura y hasta qué fecha está cubierto.

Permite consultar rápidamente si un reclamo está dentro del periodo de garantía.

---

## Clientes

Es el listado de las personas que registraron una garantía.

Guarda sus datos de contacto (nombre, documento, teléfono, correo, ciudad). Si una misma persona
registra varios productos, el sistema la reconoce y no la duplica.

Sirve para tener la base de clientes ordenada y poder contactarlos si hace falta.

---

## Configuración Zebra

Es donde se configura la impresora de etiquetas.

Se indica qué impresora se usa y cómo está conectada (por red o por cable USB), además del
tamaño exacto de la etiqueta. También hay un botón de **prueba de impresión** que envía una
etiqueta de ejemplo para verificar que todo funciona antes de imprimir un lote completo.

Normalmente se configura una vez y no se vuelve a tocar.

---

## Administración

Esta sección es para el responsable del sistema.

### Usuarios

Se crean las cuentas de las personas que van a usar el sistema y se les asigna una contraseña.

### Roles y Permisos

Define qué puede hacer cada persona. Por ejemplo, un operador de producción puede generar e
imprimir etiquetas, pero no necesita poder eliminar productos ni crear usuarios.

Sirve para que cada quien vea solo lo que le corresponde y evitar cambios accidentales.

---

## Cómo se conecta todo: el flujo de trabajo

El orden natural de uso es este:

1. **Se registra el producto** una sola vez, con su logo y todos sus datos.
2. **Se crea un lote de etiquetas** indicando cuántas se necesitan.
3. **Se imprimen** en la impresora Zebra. Cada etiqueta sale con su número único y su QR.
4. Las etiquetas **se pegan a los productos** en la fábrica.
5. **El cliente final escanea el QR** al comprar y registra su garantía.
6. La garantía queda guardada y **se puede consultar** cuando haga falta.

Todo lo que ocurre en el camino queda registrado en la Bitácora.

---

## Una nota sobre las secciones que no aparecen en el menú

Algunas secciones existen pero están ocultas a propósito: **Categorías**, **Modelos** y
**Composiciones técnicas**.

No se eliminaron: simplemente ya no hacía falta entrar a cada una por separado, porque toda esa
información se llena directamente desde la pantalla de **Productos**. Así se evita repetir datos
y se reduce el riesgo de equivocarse.
