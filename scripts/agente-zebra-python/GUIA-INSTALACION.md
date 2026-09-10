# Agente de Impresión Zebra

### Guía de instalación — Sistema de Garantías, Productos Paraíso

---

## 1. Qué hace este programa

Es un asistente que funciona en segundo plano en la computadora de producción.

Cada 10 segundos consulta al sistema si hay etiquetas pendientes. Cuando las hay, las
envía a la impresora Zebra y confirma al sistema que ya fueron impresas.

Una vez instalado, **nadie necesita abrirlo ni configurarlo cada día**. Se enciende solo
al prender la computadora y trabaja durante toda la jornada.

---

## 2. Requisitos previos

Antes de instalar, verifique que la computadora cumpla lo siguiente:

| Requisito | Detalle |
|---|---|
| Sistema operativo | Windows 10 u 11 |
| Python | Instalado, con la casilla **"Add Python to PATH"** marcada |
| Impresora | Zebra conectada, encendida y con etiquetas cargadas |
| Conexión | Acceso a internet |

> **Importante sobre Python:** si al instalarlo no se marca la casilla
> *"Add Python to PATH"*, el programa no podrá ejecutarse. Si tiene dudas de si quedó
> marcada, revise el punto 7 de esta guía.

---

## 3. Instalación

### Paso 1 — Descomprimir la carpeta en la ubicación indicada

Usted recibirá un archivo comprimido llamado **`agente-zebra-python.zip`**.

Descomprímalo directamente en la raíz del disco C, de modo que quede así:

```
C:\agente-zebra
```

Dentro de esa carpeta deben quedar los archivos `agente.py`, `setup.py`,
`config.json`, `requirements.txt` y esta guía.

> **Importante — no cambie esta ubicación.** El agente se ejecuta con la cuenta de
> sistema de Windows, que no siempre tiene acceso a las carpetas personales de un
> usuario (Escritorio, Documentos, Descargas). Si instala el agente en una de esas
> carpetas, puede funcionar durante la instalación pero dejar de funcionar al
> reiniciar la computadora. Instálelo en `C:\agente-zebra`.

### Paso 2 — Abrir PowerShell como administrador

Haga clic derecho en el botón de Inicio de Windows y seleccione
**"Windows PowerShell (Administrador)"** o **"Terminal (Administrador)"**.

Se abrirá una ventana azul o negra.

### Paso 3 — Ubicarse en la carpeta del agente

Escriba el siguiente comando tal como aparece y presione Enter:

```powershell
cd C:\agente-zebra
```

Si aparece un error diciendo que la ruta no existe, revise el Paso 1: la carpeta debe
estar exactamente en `C:\agente-zebra`.

### Paso 4 — Ejecutar el instalador

```powershell
python setup.py
```

El instalador realiza tres tareas y le va informando en pantalla:

1. **Instala las librerías necesarias** (`requests` y `pywin32`).
2. **Busca la impresora Zebra** entre las impresoras instaladas en Windows.
   - Si encuentra una sola, la selecciona automáticamente.
   - Si encuentra varias, muestra la lista y le pide que escriba el número
     correspondiente.
3. **Imprime una etiqueta de prueba** y le pregunta si salió correctamente.
   - Si la etiqueta salió, escriba **s** y presione Enter.
   - Si no salió, escriba **n** y revise que la impresora esté encendida, conectada
     y con etiquetas cargadas.

Cuando el proceso termina, aparece el mensaje **LISTO**.

---

## 4. Verificación

### Verificación inmediata

```powershell
python setup.py estado
```

Este comando muestra cuatro puntos. Los dos primeros deben indicar **OK**:

| Punto | Qué informa |
|---|---|
| [1] | Si el arranque automático quedó instalado |
| [2] | Si el agente está funcionando en este momento |
| [3] | Qué impresora tiene configurada |
| [4] | Las últimas 15 líneas del registro de actividad |

### Verificación definitiva (no omitir)

**Reinicie la computadora** y, una vez que vuelva a encender, ejecute nuevamente:

```powershell
python setup.py estado
```

Los puntos [1] y [2] deben mostrar **OK**, y en el punto [4] debe aparecer una línea
que diga **"Agente vivo"** con la hora reciente.

> Este paso es la única forma de comprobar que el agente realmente se enciende solo al
> prender la computadora. No lo omita.

---

## 5. Cómo funciona el encendido automático

El agente cuenta con tres mecanismos que garantizan su funcionamiento continuo:

**1. Encendido con la computadora**

Se registra una tarea en Windows que enciende el agente apenas arranca el equipo,
incluso antes de que alguien inicie sesión.

**2. Supervisor cada minuto**

Windows intenta encender el agente una vez por minuto durante todo el día. Si por
cualquier motivo el agente se detuvo, vuelve a funcionar en menos de un minuto, sin
que nadie intervenga.

**3. Protección contra copias duplicadas**

Como se intenta encender cada minuto, podrían quedar varias copias funcionando al
mismo tiempo e imprimir etiquetas repetidas. Para evitarlo, el agente reserva un lugar
en la computadora al iniciar. Si detecta que ya hay uno funcionando, la copia nueva se
cierra de inmediato.

Adicionalmente, si al encender la computadora todavía no hay conexión a internet, el
agente **no se cierra**: espera y reintenta hasta que la conexión esté disponible. El
mismo comportamiento aplica si el internet se interrumpe durante la jornada.

---

## 6. Uso diario

No requiere ninguna acción. El agente funciona solo.

Si necesita confirmar que está operando correctamente, ejecute:

```powershell
python setup.py estado
```

Si en el registro aparece la línea **"Agente vivo"** con hora reciente, todo está
funcionando. El agente deja esa señal cada 5 minutos.

---

## 7. Solución de problemas

### Si Windows no reconoce el comando `python`

Pruebe con:

```powershell
py setup.py
```

Si tampoco funciona, significa que Python no está instalado o no se marcó la casilla
*"Add Python to PATH"* durante su instalación. Descargue Python desde
[python.org](https://www.python.org/downloads/), instálelo nuevamente marcando esa
casilla, y repita la instalación del agente.

### Mensajes frecuentes en el registro

Ejecute `python setup.py estado` y revise el punto [4]. Los mensajes más comunes son:

| Mensaje | Significado y solución |
|---|---|
| `401 No autorizado` | La clave del archivo `config.json` no coincide con la del servidor. Corrija el valor de `agent_key`. |
| `Servidor no disponible, reintento` | No hay internet o el sistema está fuera de servicio. El agente sigue esperando por su cuenta; no requiere intervención. |
| `Error USB ...` | La impresora está apagada, desconectada, sin etiquetas, o cambió de nombre en Windows. Revise la impresora. |
| No existe registro | El agente nunca llegó a funcionar. Ejecute nuevamente `python setup.py`. |

El registro completo se guarda en el archivo `agente.log`, dentro de la misma carpeta.
Cuando supera los 5 MB se archiva como `agente.log.old` y se inicia uno nuevo, para
evitar que ocupe espacio innecesario en el disco.

---

## 8. Desinstalación

Si necesita retirar el agente de la computadora:

```powershell
python setup.py desinstalar
```

Esto elimina el encendido automático. Los archivos y la configuración permanecen en la
carpeta, por si necesita reinstalarlo más adelante.

---

## 9. Contenido de la carpeta

Ubicación: `C:\agente-zebra`

| Archivo | Descripción |
|---|---|
| `agente.py` | El agente. Es el que imprime. No debe modificarse. |
| `setup.py` | Instalador, verificación de estado y desinstalación. |
| `config.json` | Servidor, clave de acceso e impresora configurada. |
| `requirements.txt` | Librerías necesarias. |
| `GUIA-INSTALACION.pdf` | Esta guía. |
| `LEEME.txt` | Resumen rápido de los comandos. |
| `agente.log` | Registro de actividad. Se genera solo al funcionar el agente. |

---

## 10. Información técnica

Para el personal de soporte:

| Parámetro | Valor |
|---|---|
| Servidor | `http://108.174.152.179:8081` |
| Frecuencia de consulta | 10 segundos |
| Señal de actividad | Cada 5 minutos |
| Registro | `agente.log` (se archiva a los 5 MB) |
| Tareas en Windows | `ZebraPrintAgentPython`<br>`ZebraPrintAgentPython-Watchdog` |

> **Nota sobre los puertos:** el panel de administración del sistema se abre por el
> puerto 80 (`http://108.174.152.179`), mientras que la comunicación del agente utiliza
> el puerto 8081. No son intercambiables: cada uno responde únicamente por su puerto.

---

## 11. Resumen de comandos

| Acción | Comando |
|---|---|
| Instalar | `python setup.py` |
| Verificar estado | `python setup.py estado` |
| Desinstalar | `python setup.py desinstalar` |

---

*Monkey Computer — Sistema de Garantías, Productos Paraíso*
