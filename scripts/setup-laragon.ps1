<#
.SYNOPSIS
  Setup automático — Sistema Garantías en Laragon (Windows)
.DESCRIPTION
  Clona/configura el proyecto completo en Laragon:
  - Copia .env con defaults para SQLite
  - composer install, npm install, npm run build
  - Genera APP_KEY, migra con seeders
  - Crea atajos para correr el agente y el proyecto

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

# Crear archivo SQLite vacío
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    Write-Host "  ✓ database.sqlite creado" -ForegroundColor Green
} else {
    Write-Host "  ✓ database.sqlite ya existe" -ForegroundColor Green
}

php artisan migrate --force --seed 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Migraciones + seeders ejecutados" -ForegroundColor Green
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

# ── 7) Configurar agente local ────────────────────────────────
Write-Host ""
Write-Host "[7/7] Configurando agente de impresión local..." -ForegroundColor Yellow

$agentPath = "$ProjectRoot\scripts\print-agent.ps1"
if (Test-Path $agentPath) {
    $agentContent = Get-Content $agentPath -Raw
    # Si apunta al VPS, reemplazar por localhost
    if ($agentContent -match "108\.174\.152\.179") {
        $agentContent = $agentContent -replace "http://108\.174\.152\.179(:?\d*)", 'http://localhost:8000'
        Set-Content -Path $agentPath -Value $agentContent
        Write-Host "  ✓ print-agent.ps1 apunta a localhost:8000" -ForegroundColor Green
    }

    # Crear atajo en el escritorio
    $desktop = [Environment]::GetFolderPath("Desktop")
    $shortcut = "$desktop\ZEBRA - Sistema Garantías.lnk"

    $wsh = New-Object -ComObject WScript.Shell
    $link = $wsh.CreateShortcut($shortcut)
    $link.TargetPath = "powershell.exe"
    $link.Arguments = "-ExecutionPolicy Bypass -NoProfile -File `"$agentPath`""
    $link.WorkingDirectory = "$ProjectRoot\scripts"
    $link.Description = "Agente de impresión Zebra — Sistema Garantías"
    $link.Save()

    Write-Host "  ✓ Atajo creado en el escritorio: ZEBRA - Sistema Garantías" -ForegroundColor Green
}

# ── Resumen ───────────────────────────────────────────────────
$Elapsed = (Get-Date) - $StartTime
Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  ✅  SETUP COMPLETADO                     ║" -ForegroundColor Green
Write-Host "║  Tiempo: $($Elapsed.Minutes)m $($Elapsed.Seconds)s       ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  ─── CÓMO USAR ───" -ForegroundColor Yellow
Write-Host ""
Write-Host "  1. INICIAR APP:" -ForegroundColor Yellow
Write-Host "     php artisan serve" -ForegroundColor White
Write-Host "     → http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "  2. LOGIN:" -ForegroundColor Yellow
Write-Host "     Admin: admin@paraiso.com / password" -ForegroundColor White
Write-Host ""
Write-Host "  3. IMPRIMIR (en PC con Zebra conectada):" -ForegroundColor Yellow
Write-Host "     Hacé doble click en el atajo del escritorio:" -ForegroundColor White
Write-Host "     'ZEBRA - Sistema Garantías'" -ForegroundColor Cyan
Write-Host "     DEJÁ LA VENTANA ABIERTA mientras imprimís" -ForegroundColor White
Write-Host ""
Write-Host "  4. CREAR PRODUCTO + IMPRIMIR:" -ForegroundColor Yellow
Write-Host "     http://localhost:8000/admin" -ForegroundColor Cyan
Write-Host "     Productos > Crear > Lotes > Imprimir en Zebra" -ForegroundColor White
Write-Host ""
Write-Host "  5. TEST RÁPIDO DE ZEBRA (si estás en la PC con la impresora):" -ForegroundColor Yellow
Write-Host "     PowerShell -ExecutionPolicy Bypass .\scripts\test-zebra.ps1" -ForegroundColor White
Write-Host ""

Write-Host "  ⚠  IMPORTANTE:" -ForegroundColor Red
Write-Host "  Dejá la terminal de 'php artisan serve' ABIERTA." -ForegroundColor Red
Write-Host "  Dejá la terminal del agente ABIERTA mientras imprimís." -ForegroundColor Red
Write-Host ""
