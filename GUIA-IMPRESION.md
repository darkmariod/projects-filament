# Guía de instalación — Impresión de etiquetas

Hola. Si estás leyendo esto es porque necesitamos que la impresora Zebra funcione
en la PC de la fábrica. No te preocupes, es más fácil de lo que parece.
Te voy a guiar paso a paso, como si estuviéramos al lado.

---

## ¿Qué vamos a hacer?

Vamos a instalar un programita en la PC de la fábrica que se encarga de:

1. Preguntarle al sistema cada tantos segundos si hay etiquetas para imprimir
2. Si hay, las manda a la impresora Zebra
3. Le avisa al sistema que ya se imprimieron

**No necesitás saber de computación.** Solo seguir estos pasos en orden.

---

## Requisitos

- Una PC con **Windows 10 o Windows 11**
- La impresora **Zebra conectada por USB** a esa PC
- Que la PC tenga **internet** (para hablar con el sistema)

---

## Paso 1 — Descargar el programita

1. En la PC de la fábrica, abrí el navegador (Chrome, Edge, el que tengas)
2. Hacé clic en la barra de direcciones (arriba de todo) y escribí:

   ```
   http://108.174.152.179:8081/scripts/agente.zip
   ```

3. Apretá Enter
4. Se va a descargar un archivo llamado `agente.zip`
5. **No lo abras todavía**

---

## Paso 2 — Extraer en el Escritorio

1. Andá a la carpeta de **Descargas**
2. Buscá el archivo `agente.zip`
3. Hacé **clic derecho** sobre él
4. Elegí **"Extraer todo..."**
5. En la ventana que aparece, **cambiá la carpeta de destino** al **Escritorio**
6. Apretá **Extraer**

Te tiene que quedar una carpeta llamada `agente` en el Escritorio
con estos archivos adentro:

```
1-VER-IMPRESORAS.cmd
2-TEST-IMPRESION.cmd
3-INICIAR-AGENTE.cmd
4-INSTALAR-INICIO-AUTOMATICO.cmd
5-VER-ESTADO.cmd
zebra-raw-agent.ps1
```

---

## Paso 3 — Ver el nombre de la impresora

Dentro de la carpeta `agente`, hacé **doble clic** en:

```
1-VER-IMPRESORAS.cmd
```

Se va a abrir una ventana negra con una lista de impresoras instaladas.
**Anotate el nombre exacto** de la Zebra. Por lo general es algo como:

- `Zebra ZT411`
- `Zebra ZT230`
- `ZPL Printer`

Cuando terminés, apretá cualquier tecla para cerrar la ventana.

> 💡 Si no aparece ninguna Zebra en la lista, asegurate de que esté
> prendida y conectada por USB a la PC.

---

## Paso 4 — Configurar el nombre en los archivos

Ahora vamos a poner el nombre exacto de tu impresora en los archivos .cmd.

1. Hacé **clic derecho** sobre `2-TEST-IMPRESION.cmd`
2. Elegí **"Editar"** (se abre el Bloc de Notas)
3. Buscá la línea que dice algo como:

   ```
   set PRINTER_NAME=ZDesigner ZT411-203dpi ZPL
   ```

4. Cambiá `ZDesigner ZT411-203dpi ZPL` por el **nombre exacto** que anotaste
   en el Paso 3. Por ejemplo:

   ```
   set PRINTER_NAME=Zebra ZT230
   ```

5. Guardá con **Ctrl + G** (o Archivo > Guardar) y cerralo
6. **Repetí lo mismo** para `3-INICIAR-AGENTE.cmd`, `4-INSTALAR-INICIO-AUTOMATICO.cmd`
   y `5-VER-ESTADO.cmd`

---

## Paso 5 — Probar la impresora

Asegurate que la Zebra esté prendida, con papel, y hacé **doble clic** en:

```
2-TEST-IMPRESION.cmd
```

Se abre una ventana negra que intenta imprimir una etiqueta de prueba.

- ✅ **Si la Zebra imprime** — ¡perfecto! Pasá al Paso 6.
- ❌ **Si no imprime**:
  - Verificá que el nombre de la impresora sea **exactamente igual** al del Paso 3
  - Verificá que la Zebra esté prendida y con papel
  - Andá a Menú Inicio > "Impresoras" y asegurate que la Zebra esté visible
  - Volvé a intentar

---

## Paso 6 — Probar que llegue al sistema

Hacé **doble clic** en:

```
3-INICIAR-AGENTE.cmd
```

Se abre una ventana negra que muestra:

```
>>> PASO 1: Verificando impresora ...
  [OK] Impresora encontrada: Zebra ZT411
>>> PASO 2: Conectando al servidor ...
  [OK] Conectado al servidor
>>> PASO 3: AGENTE ACTIVO
```

Si ves eso, **todo funciona**. **NO cierres esta ventana.**

Ahora andá al sistema web, creá un lote de etiquetas y mandalo a imprimir
en la Zebra. En unos segundos la ventana va a mostrar:

```
>>> Cola #123 — 5 etiqueta(s) para Zebra ZT411
  [OK]  Item #1 impreso y reportado
  [OK]  Item #2 impreso y reportado
```

La etiqueta sale de la Zebra automáticamente.

> Para detener el agente, apretá **CTRL + C** en la ventana negra.

---

## Paso 7 — Hacer que funcione siempre (automático)

Cuando estés seguro de que funciona, cerrá la ventana del Paso 6
(apretá CTRL + C si hace falta).

Ahora andá a la carpeta `agente`, hacé **clic derecho** sobre:

```
4-INSTALAR-INICIO-AUTOMATICO.cmd
```

Y elegí **"Ejecutar como administrador"**.

Esto hace que el agente se active **cada vez que se prende la PC** y
cada 1 minuto. No hace falta hacer nada más, funciona solo.

---

## Paso 8 — Verificar que esté todo bien

En cualquier momento podés hacer **doble clic** en:

```
5-VER-ESTADO.cmd
```

Te va a mostrar un resumen de todo:

- ✅ Si el agente automático está instalado
- ✅ Si la impresora está conectada
- ✅ Si el sistema está respondiendo

Si ves tres de estos, **está todo listo**.

---

## Solución de problemas

### 🖨️ "Impresora no encontrada"

- Verificá que la Zebra esté prendida y conectada por USB
- Andá a Menú Inicio → "Impresoras" → fijate que aparezca
- Ejecutá `1-VER-IMPRESORAS.cmd` para ver el nombre exacto
- Asegurate de haber cambiado el nombre en los archivos .cmd (Paso 4)

### 🌐 "No se pudo conectar al servidor"

- La PC no llega al sistema. Revisá:
- Que la PC tenga internet
- Que el sistema esté funcionando

### ⏳ "No hay colas pendientes"

Eso no es un error. Significa que no hay nada para imprimir.
Andá al sistema web, creá un lote de etiquetas e imprimilo.
El agente lo agarra solo.

### ❌ "Acceso denegado" al ejecutar 4-INSTALAR-INICIO-AUTOMATICO.cmd

Hacé **clic derecho** sobre el archivo y elegí **"Ejecutar como administrador"**.

---

## ¿Cada cuánto imprime?

El agente revisa cada **10 segundos** si hay etiquetas nuevas.
Apenas encuentra, las imprime una por una. No hace falta hacer nada.

Cuando la PC se prende, el agente arranca solo (si instalaste el Paso 7).

---

## Desinstalar

Si algún día querés sacarlo:

1. Apretá **Windows + R**, escribí `taskschd.msc`, Enter
2. Buscá `ZebraPrintAgent` en la lista
3. Clic derecho > **Eliminar**
4. Borrá la carpeta `agente` del Escritorio

Y listo, el programita deja de funcionar.

---

## ¿Necesitás ayuda?

Si algo no funciona, contactate con nosotros. En lo posible mandá
una **captura de pantalla** de lo que ves al ejecutar los pasos,
así vemos dónde está el problema.

¡Suerte y gracias!
