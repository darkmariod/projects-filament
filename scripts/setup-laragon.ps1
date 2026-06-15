<#
.SYNOPSIS
  Setup automático — Sistema Garantías en Laragon (Windows)
.DESCRIPTION
  Clona/configura el proyecto completo en Laragon:
  - Copia .env con defaults para SQLite + agente
  - composer install, npm install, npm run build
  - Genera APP_KEY, migra con seeders (incluye datos demo)
  - Crea atajo en el escritorio para el agente Zebra

  USO:
    1. Abrí Laragon > Terminal
    2. Pegá:  git clone git@github.com:darkmariod/projects-filament.git
    3. Pegá:  cd projects-filament
    4. Ejecutá:  PowerShell -ExecutionPolicy Bypass .\scripts\setup-laragon.ps1
#>

$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$StartTime = Get-Date

Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  SETUP SISTEMA GARANTÍAS — LARAGON       ║" -ForegroundColor Cyan
Write-Host "║  Windows + SQLite (sin MySQL)            ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Set-Location $ProjectRoot

# ── 1) .env ──────────────────────────────────────────────────
Write-Host "[1/7] Creando .env ..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "  ✓ .env copiado desde .env.example" -ForegroundColor Green
} else {
    Write-Host "  ✓ .env ya existe" -ForegroundColor Green
}

# ── 2) Composer ───────────────────────────────────────────────
Write-Host ""
Write-Host "[2/7] Instalando dependencias PHP (composer) ..." -ForegroundColor Yellow
Write-Host "  Esto puede tomar 1-2 minutos..." -ForegroundColor Gray
$composerResult = & "composer" "install" "--no-interaction" "--prefer-dist" "2>&1"
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ composer install OK" -ForegroundColor Green
} else {
    Write-Host "  ✗ composer install falló" -ForegroundColor Red
    Write-Host "  $composerResult" -ForegroundColor Red
    Write-Host "  Asegurate de tener PHP y Composer instalados en Laragon" -ForegroundColor Yellow
}

# ── 3) APP_KEY ────────────────────────────────────────────────
Write-Host ""
Write-Host "[3/7] Generando APP_KEY ..." -ForegroundColor Yellow
php artisan key:generate --force --quiet
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ APP_KEY generada" -ForegroundColor Green
} else {
    Write-Host "  ✗ Error generando APP_KEY" -ForegroundColor Red
}

# ── 4) SQLite database + migrate ──────────────────────────────
Write-Host ""
Write-Host "[4/7] Creando base de datos SQLite y migrando..." -ForegroundColor Yellow

if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    Write-Host "  ✓ database.sqlite creado" -ForegroundColor Green
} else {
    Write-Host "  ✓ database.sqlite ya existe" -ForegroundColor Green
}

php artisan migrate --force --seed 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Migraciones + seeders ejecutados" -ForegroundColor Green
    Write-Host "  ✓ Productos demo (línea Señorial) cargados" -ForegroundColor Green
} else {
    Write-Host "  ✗ Error en migraciones" -ForegroundColor Red
}

# ── 5) Storage link ───────────────────────────────────────────
Write-Host ""
Write-Host "[5/7] Storage link..." -ForegroundColor Yellow
php artisan storage:link --force --quiet 2>$null
Write-Host "  ✓ storage link creado" -ForegroundColor Green

# ── 6) npm build ──────────────────────────────────────────────
Write-Host ""
Write-Host "[6/7] Compilando assets frontend (npm)..." -ForegroundColor Yellow
Write-Host "  Esto puede tomar 1-2 minutos..." -ForegroundColor Gray

$npmResult = & "npm" "install" "2>&1"
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ npm install OK" -ForegroundColor Green
    npm run build 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ npm run build OK" -ForegroundColor Green
    } else {
        Write-Host "  ✗ npm run build falló (podés correrlo manual)" -ForegroundColor Yellow
    }
} else {
    Write-Host "  ⚠ npm install falló (asegurate de tener Node.js en Laragon)" -ForegroundColor Yellow
}

# ── 7) Atajo en el escritorio ─────────────────────────────────
Write-Host ""
Write-Host "[7/7] Creando acceso directo al agente Zebra..." -ForegroundColor Yellow

$agentPath = "$ProjectRoot\scripts\print-agent.ps1"
if (Test-Path $agentPath) {
    $desktop = [Environment]::GetFolderPath("Desktop")
    $shortcut = "$desktop\ZEBRA - Sistema Garantías.lnk"

    $wsh = New-Object -ComObject WScript.Shell
    $link = $wsh.CreateShortcut($shortcut)
    $link.TargetPath = "powershell.exe"
    $link.Arguments = "-ExecutionPolicy Bypass -NoProfile -File `"$agentPath`""
    $link.WorkingDirectory = "$ProjectRoot\scripts"
    $link.Description = "Agente de impresión Zebra — Sistema Garantías"
    $link.Save()

    Write-Host "  ✓ Atajo creado: Escritorio > ZEBRA - Sistema Garantías" -ForegroundColor Green
}

# ── Resumen ───────────────────────────────────────────────────
$Elapsed = (Get-Date) - $StartTime
Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  ✅  SETUP COMPLETADO                     ║" -ForegroundColor Green
Write-Host "║  Tiempo: $($Elapsed.Minutes)m $($Elapsed.Seconds)s        ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  ─── PASOS PARA EL DEMO ───" -ForegroundColor Yellow
Write-Host ""
Write-Host "  ⚡ TERMINAL 1 — APP:" -ForegroundColor Cyan
Write-Host "     php artisan serve" -ForegroundColor White
Write-Host "     http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "  ⚡ TERMINAL 2 — AGENTE:" -ForegroundColor Cyan
Write-Host "     Doble click en 'ZEBRA - Sistema Garantías' del escritorio" -ForegroundColor White
Write-Host "     (se conecta automático a localhost:8000)" -ForegroundColor Gray
Write-Host ""
Write-Host "  ⚡ NAVEGADOR:" -ForegroundColor Cyan
Write-Host "     http://localhost:8000/admin" -ForegroundColor White
Write-Host "     admin@paraiso.com / password123" -ForegroundColor White
Write-Host ""
Write-Host "  ⚡ IMPRIMIR (primero creá datos si no existen):" -ForegroundColor Cyan
Write-Host "     Productos → ya hay datos demo (Señorial)" -ForegroundColor Gray
Write-Host "     Lotes → Crear lote → Elegir producto → Generar etiquetas" -ForegroundColor White
Write-Host "     Lote → 'Imprimir en Zebra'" -ForegroundColor White
Write-Host "     → Etiqueta sale en 5-10 segundos" -ForegroundColor Green
Write-Host ""
Write-Host "  ⚠  NO CIERRES ninguna terminal hasta terminar." -ForegroundColor Red
Write-Host ""

Write-Host "  ── LINKS RÁPIDOS ──" -ForegroundColor Yellow
Write-Host "  Admin:  http://localhost:8000/admin" -ForegroundColor Cyan
Write-Host "  Página: http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
