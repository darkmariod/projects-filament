# Sistema de Garantías — Paraíso

Sistema de gestión de productos, garantías y etiquetas con impresión Zebra, construido con **Laravel 12** + **Filament 5**.

## Funcionalidades

- **Productos** — Catálogo con modelos, composiciones técnicas, categorías y datos de fabricante
- **Garantías** — Gestión de garantías por producto
- **Etiquetas ZPL** — Generación e impresión de etiquetas en impresoras Zebra (Zebra ZT411, 203 DPI), diseño portrait 95×200 mm (760×1600 dots): dos stickers de control de calidad, composición técnica con símbolos de cuidado, logotipo Paraíso, código QR de garantía y código de barras
- **Impresión por lotes** — Cola de impresión con control de secuencia, reintentos y logs
- **Clientes** — Registro y seguimiento de clientes
- **Roles y permisos** — Control de acceso con Spatie Permission

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 12 |
| Admin | Filament 5 |
| BD | SQLite (desarrollo) / MySQL (producción) |
| QR | simplesoftwareio/simple-qrcode |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
| Impresión | ZPL over TCP/IP |

## Setup rápido (Laragon — Windows)

```powershell
git clone git@github.com:darkmariod/projects-filament.git
cd projects-filament
PowerShell -ExecutionPolicy Bypass .\scripts\setup-laragon.ps1
php artisan serve
```

El script crea la base SQLite, corre migraciones + seeders (datos demo), compila assets y crea un acceso directo al agente Zebra.

### Accesos demo

- **Admin:** `http://localhost:8000/admin`
- **Usuario:** `admin@paraiso.com` / `password123`

### Agente de impresión

Ejecutá `scripts/supervisor-agente.pyw` (recomendado, corre oculto) o `scripts/iniciar-agente.bat`. Ver la sección **📦 Entrega del proyecto** más abajo para la instalación completa.

## Validación y testing

```bash
# Instalar dependencias y preparar entorno
composer setup

# Ejecutar tests (Unit + Feature con SQLite en memoria)
composer test

# Code style (Laravel Pint)
vendor/bin/pint --test

# También podés correr tests directamente con Artisan
php artisan test
```

## Scripts disponibles

| Script | Uso |
|--------|-----|
| `composer setup` | Bootstrap completo: install, .env, key, migrate, npm, build |
| `composer dev` | Entorno dev: servidor + queue + logs + Vite en paralelo |
| `composer test` | Suite de tests PHPUnit |
| `./deploy.sh` | Despliegue a VPS con Docker + MySQL + health check |
| `scripts/agente.py` | Agente de impresión Zebra para Windows (USB) |
| `docker-compose up -d` | Entorno de producción con Docker |

## Despliegue (VPS)

Ver `deploy.sh` y `docker-compose.yml` para despliegue con Docker y MySQL.

---

## 📦 Entrega del proyecto — Instalación del agente de impresión

El sistema web corre en el servidor. Para que las etiquetas salgan por la **impresora Zebra ZT411 conectada por USB**, cada computadora que imprima necesita el **agente de impresión** (un programa liviano en Python que conecta la Zebra con el sistema).

### 1. Descargar lo necesario

| Qué | Enlace directo | Nota |
|-----|----------------|------|
| **Agente de impresión (ZIP)** | [⬇️ Descargar agente-python.zip](https://github.com/darkmariod/projects-filament/raw/main/scripts/agente-python.zip) | Contiene el agente, el supervisor y las guías |
| **Python (Windows)** | [⬇️ python.org/downloads](https://www.python.org/downloads/) | Marcar **"Add Python to PATH"** al instalar |

> El resto (librerías `requests` y `pywin32`) se instala con un comando incluido en la guía — no hay que descargar nada más.

### 2. Instalar Python

1. Abrir el instalador descargado de python.org
2. **IMPORTANTE:** marcar la casilla **"Add Python to PATH"** antes de instalar
3. Clic en *Install Now*

### 3. Instalar las librerías del agente

Abrir `cmd` (tecla Windows + R → escribir `cmd` → Enter) y pegar:

```cmd
pip install requests pywin32
```

### 4. Configurar y dejar el agente siempre activo

1. Extraer el ZIP descargado en la carpeta `C:\Agente`
2. Doble clic en **`supervisor-agente.pyw`** (corre oculto, se reinicia solo si falla)
3. Para que arranque al prender la PC: Windows + R → escribir `shell:startup` → Enter → crear ahí un acceso directo a `C:\Agente\supervisor-agente.pyw`

La guía completa paso a paso está dentro del ZIP en **`GUIA-AGENTE-SIEMPRE-ACTIVO.txt`**.

### 5. Probar la impresión

1. Entrar al sistema web → **Lotes de Etiquetas** → generar un lote
2. La etiqueta sale sola por la Zebra
3. El agente avisa en pantalla / en `agente.log` si la impresora está **sin papel, en pausa, con la tapa abierta o desconectada**

### Archivos del agente (`scripts/`)

| Archivo | Para qué sirve |
|---------|----------------|
| `agente.py` | Agente principal: conecta el sistema con la Zebra por USB |
| `supervisor-agente.pyw` | Mantiene el agente siempre encendido (oculto, con reinicio automático) |
| `iniciar-agente.bat` | Alternativa con ventana visible |
| `INSTALAR-PYTHON.txt` | Guía de instalación de Python y librerías |
| `GUIA-AGENTE-SIEMPRE-ACTIVO.txt` | Guía para dejar el agente permanente |
| `agente-python.zip` | Paquete listo para entregar (todos los archivos de arriba) |

> ℹ️ La configuración de la impresora (**Configuración Zebra** en el panel) ya viene lista: USB, `ZDesigner ZT411-203dpi ZPL`, 95×200 mm. **No hace falta tocarla** salvo que cambien de impresora. Los datos de las etiquetas se editan desde **Productos** y **Composiciones**.
