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

- Una PC con Windows 10 o Windows 11
- La impresora Zebra conectada por USB a esa PC
- Que la PC tenga internet (para hablar con el sistema)

---

## Paso 1 — Encontrar la impresora en Windows

Primero tenemos que ver el nombre exacto que Windows le puso a la Zebra.

1. Conectá la Zebra por USB a la PC y prendela
2. Abrí el **Menú Inicio** y escribí **"Ver impresoras"**
3. Hacé clic en el resultado que dice **"Ver impresoras y escáneres"**
4. Buscá la Zebra en la lista
5. **Anotate el nombre exacto** tal cual aparece. Por lo general es algo como:
   - `Zebra ZT411`
   - `Zebra ZT230`
   - `ZPL Printer`

   Ese nombre lo vamos a necesitar después.

![La pantalla de impresoras de Windows muestra la lista con el nombre de cada una]

*También podés hacer esto:* apretá las teclas **Windows + R**, escribí `control printers` y apretá Enter.

---

## Paso 2 — Descargar el programita

1. En la misma PC, abrí el navegador (Chrome, Edge, el que tengas)
2. Hacé clic en la barra de direcciones (arriba de todo) y escribí:

   ```
   http://108.174.152.179:8081/zebra-agent.ps1
   ```

3. Apretá Enter
4. Se va a descargar un archivo llamado `zebra-agent.ps1`
5. **No lo cierres todavía**, después te digo dónde guardarlo

> **Si el navegador te muestra el contenido del archivo en vez de descargarlo:**
> Apretá **Ctrl + S**, elegí **Escritorio** como lugar y **Guardar**.

---

## Paso 3 — Abrir PowerShell como Administrador

Este es el paso más importante. PowerShell es como una ventanita negra donde escribimos órdenes.

1. Apretá la tecla **Windows** (la del logo) y escribí **PowerShell**
2. Te va a aparecer **Windows PowerShell** en la lista
3. **NO hagas clic todavía.** En lugar de eso, fijate que a la derecha dice **"Ejecutar como administrador"** — hacé clic ahí
4. Te va a preguntar "¿Querés permitir que esta app haga cambios?" — decí **Sí**
5. Se abre una ventana negra. **Esa es la consola.**

> Quizás te asuste un poco ver una pantalla negra, pero es normal. Todo lo que escribas ahí le da órdenes a la PC.

![Una ventana negra con texto blanco: Windows PowerShell]

---

## Paso 4 — Permitir que PowerShell ejecute scripts

Windows a veces bloquea estos programitas por seguridad. Vamos a darle permiso solo una vez.

En la ventana negra de PowerShell, **escribí exactamente esto** (podés copiar y pegar con click derecho):

```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

Apretá **Enter**. Te va a preguntar algo como:

```
¿Desea cambiar la directiva de ejecución?
[S] Sí  [N] No  [T] Todos
```

Escribí la letra **S** (de Sí) y apretá **Enter**.

---

## Paso 5 — Ir al Escritorio

Ahora vamos a decirle a PowerShell que trabaje en el Escritorio, donde descargamos el archivo.

Escribí este comando y apretá Enter:

```powershell
cd $env:USERPROFILE\Desktop
```

No tendrías que ver ningún error. Si ves una línea nueva con algo como `PS C:\Users\...\Desktop`, está bien.

---

## Paso 6 — Verificar el nombre de la impresora

Vamos a confirmar que Windows ve la Zebra. Escribí:

```powershell
Get-Printer | Format-Table Name
```

Te va a mostrar una lista de impresoras instaladas. Fijate que aparezca la Zebra. El nombre tiene que **coincidir exactamente** con el que anotaste en el Paso 1.

---

## Paso 7 — Configurar el nombre en el programita

Ahora vamos a ajustar el nombre de la impresora en el archivo que descargamos.

En la misma ventana negra de PowerShell, escribí:

```powershell
notepad zebra-agent.ps1
```

Se abre el Bloc de Notas con el contenido del archivo.

1. Buscá la línea 38 (o buscá el texto `Zebra ZT411` apretando **Ctrl + B**)
2. Vas a ver algo como:

   ```powershell
   $Script:PrinterName   = "Zebra ZT411"
   ```

3. Cambiá `"Zebra ZT411"` por el nombre exacto de tu impresora
   - Por ejemplo: `$Script:PrinterName = "Zebra ZT230"`
   - **Respetá las comillas**,
 - escribí el nombre exacto que viste en el Paso 1
4. Apretá **Ctrl + G** para guardar
5. Cerrate el Bloc de Notas
6. Volvé a la ventana de PowerShell

---

## Paso 8 — ¡Probarlo!

Vamos a hacer una prueba para ver si todo funciona. En PowerShell escribí:

```powershell
.\zebra-agent.ps1 -once
```

Si todo está bien, vas a ver algo como:

```
[2026-06-23 14:30:00] Zebra Print Agent iniciado
[2026-06-23 14:30:00] Impresora encontrada: Zebra ZT411
[2026-06-23 14:30:00] Consultando colas pendientes...
[2026-06-23 14:30:00] No hay colas pendientes.
[2026-06-23 14:30:00] Modo -once: finalizado.
```

Eso significa que **el programita funciona** y está esperando que alguien mande etiquetas a imprimir.

> Si ves un error que dice algo como "Impresora no encontrada", revisá que:
> - El nombre en el Paso 7 sea **exactamente igual** al que te mostró `Get-Printer` en el Paso 6
> - La impresora esté prendida y conectada por USB

---

## Paso 9 — Instalar para que funcione siempre

Ahora vamos a hacer que este programita se active solo, aunque apagues y prendas la PC.

En la misma ventana de PowerShell, escribí:

```powershell
.\zebra-agent.ps1 -install
```

Te va a aparecer un mensaje como:

```
✓ Tarea 'ZebraPrintAgent' instalada correctamente.
  Se ejecuta al iniciar Windows y cada 1 minuto.
```

**Listo.** Ya está instalado. Desde ahora, la PC:

- Apenas se prende, arranca el agente solo
- Cada 1 minuto pregunta si hay etiquetas para imprimir
- Cuando hay, las imprime automáticamente

**No hace falta hacer nada más.** Podés cerrar la ventana de PowerShell y olvidarte.

---

## Paso 10 — Verificar que funcione bien

En cualquier momento podés ver el estado del agente. Abrí PowerShell y escribí:

```powershell
.\zebra-agent.ps1 -status
```

Te va a mostrar:

- Si el agente está instalado ✅
- Si la impresora está conectada ✅
- Si el sistema está respondiendo ✅

También podés ver el historial de actividad. Es un archivo de texto que está en:

```
C:\Users\TU_USUARIO\AppData\Local\Temp\zebra-agent.log
```

Si querés ver las últimas líneas, abrí PowerShell y escribí:

```powershell
Get-Content "$env:TEMP\zebra-agent.log" -Tail 10
```

---

## Solución de problemas

### 🙈 "No se pudo conectar con el servidor"

La PC no llega al sistema. Revisá:
- Que la PC tenga internet
- Que el sistema esté funcionando (preguntale al que te pasó esta guía)

### 🖨️ "Impresora no encontrada"

- Verificá que la Zebra esté prendida y conectada por USB
- Andá a Menú Inicio → "Impresoras" → fijate que aparezca
- Ejecutá `Get-Printer | Format-Table Name` en PowerShell para ver el nombre exacto

### ⚠️ "No hay colas pendientes"

Eso no es un error, significa que no hay nada para imprimir. El agente está esperando.
Andá al sistema web, creá un lote de etiquetas e imprimilo. El agente lo agarra solo.

### ❌ Error de permisos al ejecutar

Asegurate de haber ejecutado PowerShell como **Administrador** (Paso 3).

---

## ¿Cada cuánto imprime?

El agente revisa cada **1 minuto** si hay etiquetas nuevas. Apenas encuentra, las imprime una por una con medio segundo de diferencia. No hace falta hacer nada, todo es automático.

Si imprimiste desde el sistema y la Zebra no reacciona, esperá un minuto. Si pasa más de un minuto, revisá los pasos de arriba.

---

## Desinstalar

Si algún día querés sacarlo:

```powershell
.\zebra-agent.ps1 -uninstall
```

Y listo, se borra la tarea programada. El programita deja de funcionar.

---

## ¿Necesitás ayuda?

Si algo no funciona, contactate con nosotros. En lo posible mandá una captura de pantalla de la ventana de PowerShell cuando intentaste los pasos, así vemos dónde está el problema.

¡Suerte y gracias!
