# Sistema de Garantías — Paraíso

Sistema de gestión de productos, garantías y etiquetas con impresión Zebra, construido con **Laravel 12** + **Filament 5**.

## Funcionalidades

- **Productos** — Catálogo con modelos, composiciones técnicas, categorías y datos de fabricante
- **Garantías** — Gestión de garantías por producto
- **Etiquetas ZPL** — Generación e impresión de etiquetas en impresoras Zebra con diseño landscape (1594×760), tres zonas: logotipo + datos del producto + código QR
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

Ejecutá `scripts/ejecutar-agente.bat` o el acceso directo *ZEBRA - Sistema Garantías* del escritorio.

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
| `zebra-agent.ps1` | Agente de impresión Zebra para Windows |
| `docker-compose up -d` | Entorno de producción con Docker |

## Despliegue (VPS)

Ver `deploy.sh` y `docker-compose.yml` para despliegue con Docker y MySQL.
