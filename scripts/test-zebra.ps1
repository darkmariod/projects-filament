<#
.SYNOPSIS
  Test de impresora Zebra — NO toca el VPS, solo prueba conectividad local.

.DESCRIPTION
  Verifica que la Zebra esté instalada en Windows y envía una etiqueta
  de prueba. Seguro de ejecutar: no modifica colas ni contacta el servidor.

  Uso:
    .\scripts\test-zebra.ps1
    .\scripts\test-zebra.ps1 -PrinterName "Zebra ZT411"

  Si no sabés el nombre exacto:
    Get-Printer | Format-Table Name, DriverName, PortName
#>

param(
    [string]$PrinterName = "Zebra ZT411"
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║     TEST ZEBRA — Conexión Local          ║" -ForegroundColor Cyan
Write-Host "║     NO contacta el VPS                   ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# ── 1) Verificar que la impresora existe ────────────────────────────
Write-Host "[1/4] Verificando impresora en Windows..." -ForegroundColor Yellow

$printer = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue

if (-not $printer) {
    Write-Host "  ✗ Impresora '$PrinterName' NO encontrada" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Impresoras disponibles:" -ForegroundColor Yellow
    Write-Host "  ─────────────────────" -ForegroundColor Gray
    Get-Printer | Format-Table Name, DriverName, PortName
    Write-Host ""
    Write-Host "  Si la impresora está instalada pero con otro nombre:" -ForegroundColor Cyan
    Write-Host "  .\scripts\test-zebra.ps1 -PrinterName ""Zebra ZT411 (copia 1)""" -ForegroundColor Gray
    exit 1
}

Write-Host "  ✓ Encontrada: $($printer.Name)" -ForegroundColor Green
Write-Host "    Driver: $($printer.DriverName)"
Write-Host "    Puerto: $($printer.PortName)"
Write-Host "    Estado: $($printer.PrinterStatus) (3 = Ready)"

if ($printer.PrinterStatus -ne 3) {
    Write-Host "  ⚠  La impresora no está en estado Ready. Verificá que esté encendida y sin errores." -ForegroundColor Yellow
}

# ── 2) Verificar recurso compartido ─────────────────────────────────
Write-Host ""
Write-Host "[2/4] Verificando recurso compartido..." -ForegroundColor Yellow

$shareName = $PrinterName
$shares = net share 2>&1 | Out-String
if ($shares -match [regex]::Escape($shareName)) {
    Write-Host "  ✓ Recurso compartido '$shareName' disponible" -ForegroundColor Green
} else {
    Write-Host "  ⚠  No se detectó el recurso compartido '$shareName'" -ForegroundColor Yellow
    Write-Host "     Para compartir la impresora:" -ForegroundColor Cyan
    Write-Host "     1. Panel de Control > Dispositivos e Impresoras" -ForegroundColor Gray
    Write-Host "     2. Click derecho en '$PrinterName' > Propiedades de impresora" -ForegroundColor Gray
    Write-Host "     3. Pestaña 'Compartir' > Marcar 'Compartir esta impresora'" -ForegroundColor Gray
    Write-Host "     4. Nombre del recurso: $PrinterName" -ForegroundColor Gray
    Write-Host ""
    Write-Host "     El test va a continuar igual..." -ForegroundColor Yellow
}

# ── 3) Enviar etiqueta de prueba ────────────────────────────────────
Write-Host ""
Write-Host "[3/4] Enviando etiqueta de prueba..." -ForegroundColor Yellow

$serial = "TEST-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

$testZpl = @"
^XA
^FO50,50^A0N,50,50^FDSISTEMA GARANTIAS^FS
^FO50,120^A0N,30,30^FDPrueba de conexion Zebra^FS
^FO50,170^A0N,25,25^FDSi ves esto, la impresora funciona OK^FS
^FO50,230^BQN,2,6^FDMA,${serial}^FS
^FO50,350^A0N,20,20^FDSerial: ${serial}^FS
^FO50,400^A0N,18,18^FD$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')^FS
^XZ
"@

$tempFile = "$env:TEMP\zebra-test-$($serial).zpl"
[System.IO.File]::WriteAllText($tempFile, $testZpl, [System.Text.Encoding]::UTF8)

Write-Host "  ZPL generado: $tempFile" -ForegroundColor Gray

# Método 1: copy /b
Write-Host "  Método 1: copy /b ..." -ForegroundColor Gray
$result = cmd /c "copy /b `"$tempFile`" `"\\localhost\$PrinterName`" 2>&1"

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Etiqueta enviada por copy /b" -ForegroundColor Green
    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  ✓ TEST EXITOSO                          ║" -ForegroundColor Green
    Write-Host "║  La Zebra está funcionando               ║" -ForegroundColor Green
    Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
    exit 0
}

Write-Host "  ✗ copy /b falló: $result" -ForegroundColor Yellow

# Método 2: fopen directo
Write-Host "  Método 2: escritura directa UNC ..." -ForegroundColor Gray
try {
    $stream = [System.IO.File]::OpenWrite("\\localhost\$PrinterName")
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($testZpl)
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    Write-Host "  ✓ Etiqueta enviada por escritura directa" -ForegroundColor Green
    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  ✓ TEST EXITOSO                          ║" -ForegroundColor Green
    Write-Host "║  (copy /b falló pero fopen funcionó)     ║" -ForegroundColor Green
    Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
    exit 0
} catch {
    Write-Host "  ✗ Escritura directa falló: $_" -ForegroundColor Yellow
}

# Método 3: Out-Printer
Write-Host "  Método 3: Out-Printer ..." -ForegroundColor Gray
try {
    Get-Content $tempFile -Raw | Out-Printer -Name $PrinterName
    Write-Host "  ✓ Etiqueta enviada por Out-Printer" -ForegroundColor Green
    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
    Write-Host ""
    Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  ✓ TEST EXITOSO                          ║" -ForegroundColor Green
    Write-Host "║  (usando Out-Printer)                    ║" -ForegroundColor Green
    Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
    exit 0
} catch {
    Write-Host "  ✗ Out-Printer falló: $_" -ForegroundColor Red
}

Remove-Item $tempFile -Force -ErrorAction SilentlyContinue

# ── 4) Todo falló ──────────────────────────────────────────────────
Write-Host ""
Write-Host "[4/4] Diagnóstico..." -ForegroundColor Yellow
Write-Host "  TODOS los métodos fallaron." -ForegroundColor Red
Write-Host ""
Write-Host "  Posibles causas:" -ForegroundColor Cyan
Write-Host "  1. La impresora no está compartida" -ForegroundColor Gray
Write-Host "     → Panel de Control > Impresoras > Compartir > Marcar" -ForegroundColor Gray
Write-Host "  2. El nombre no coincide exactamente" -ForegroundColor Gray
Write-Host "     → Ejecutá: Get-Printer | Format-Table Name" -ForegroundColor Gray
Write-Host "  3. La impresora está en pausa o tiene error" -ForegroundColor Gray
Write-Host "     → Verificá el panel de impresión de Windows" -ForegroundColor Gray
Write-Host "  4. Driver incorrecto (no acepta ZPL)" -ForegroundColor Gray
Write-Host "     → La Zebra debe usar driver 'Zebra ZPL Printer'" -ForegroundColor Gray
Write-Host ""
Write-Host "  Solución rápida: compartí la impresora con este nombre exacto:" -ForegroundColor Cyan
Write-Host "  $PrinterName" -ForegroundColor White
Write-Host ""

exit 1
