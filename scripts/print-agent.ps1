<#
.SYNOPSIS
    Agente de impresion Zebra — Sistema de Garantias
.DESCRIPTION
    Consulta el VPS cada 10 segundos y si hay etiquetas pendientes las imprime
    en la Zebra ZT411 conectada por USB a esta PC.
.NOTES
    Version: 2.0
    Como usar:
      1. Click derecho > "Ejecutar con PowerShell"
      2. O en terminal: PowerShell -ExecutionPolicy Bypass -File "%~f0"
      3. DEJAR LA VENTANA ABIERTA mientras imprimis
#>

# ============================================================
# CONFIGURACION
# ============================================================
$ServerUrl    = "http://localhost:8000"   # Local (Laragon / artisan serve)
$VpsUrl       = "http://108.174.152.179:8081"  # VPS producción
$AgentKey     = "zebra-agent-key-2026"
$PrinterName  = "Zebra ZT411"
$PollInterval = 10

# ============================================================
# INICIO — NO CAMBIAR DE ACA PARA ABAJO
# ============================================================
$Headers   = @{ "X-Agent-Key" = $AgentKey; "Accept" = "application/json" }
$TempDir   = "$env:TEMP\zebra-print-agent"
$LogFile   = "$TempDir\agent.log"

if (-not (Test-Path $TempDir)) { New-Item -ItemType Directory -Path $TempDir -Force | Out-Null }

# Colores para la consola
$Host.UI.RawUI.ForegroundColor = "White"

function Write-Info  { Write-Host "[INFO] $args" -ForegroundColor Cyan }
function Write-Ok    { Write-Host "[OK]   $args" -ForegroundColor Green }
function Write-Warn  { Write-Host "[!]    $args" -ForegroundColor Yellow }
function Write-Error { Write-Host "[ERROR] $args" -ForegroundColor Red }
function Write-Step  { Write-Host "`n>>> $args" -ForegroundColor Magenta }

function Add-Log {
    param([string]$M, [string]$L = "INFO")
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$L] $M"
    Add-Content -Path $LogFile -Value $line
}

# ── PRINTER CHECK ─────────────────────────────────────────────
function Test-PrinterShare {
    $shares = net share 2>&1 | Select-String -Pattern $PrinterName
    if ($shares) {
        Write-Ok "Impresora '$PrinterName' encontrada en recursos compartidos"
        return $true
    }
    Write-Warn "IMPORTANTE: No se encuentra la impresora '$PrinterName'"
    Write-Warn "Asegurate de que este compartida en:"
    Write-Warn "  Panel de Control > Dispositivos e impresoras"
    Write-Warn "  Click derecho en Zebra ZT411 > Propiedades > Compartir"
    Write-Warn "  Marcar 'Compartir esta impresora' con nombre: $PrinterName"
    Write-Warn "  (sin acentos, exactamente como esta arriba)"
    return $false
}

# ── SEND ZPL TO PRINTER ──────────────────────────────────────
function Send-Zpl {
    param([string]$Zpl, [int]$ItemId)

    $tempFile = "$TempDir\label-$ItemId.zpl"

    try {
        [System.IO.File]::WriteAllText($tempFile, $Zpl, [System.Text.Encoding]::GetEncoding(28591))
    } catch {
        Add-Log "Error escribiendo archivo ZPL #$ItemId : $_" "ERROR"
        return $false
    }

    Write-Info "Enviando etiqueta #$ItemId a \\localhost\$PrinterName ..."

    $result = cmd /c "copy /b `"$tempFile`" `"\\localhost\$PrinterName`" 2>&1"
    $ok = $LASTEXITCODE -eq 0 -and $result -notmatch "(error|no found|cannot find|no se encuentra|syntax)"

    if ($ok) {
        Write-Ok "Etiqueta #$ItemId IMPRESA correctamente"
        Add-Log "Etiqueta #$ItemId impresa OK" "OK"
        return $true
    }

    Add-Log "Error imprimiendo #$ItemId : $result" "FAIL"
    Write-Error "Fallo etiqueta #$ItemId"
    Write-Info "  Motivo: $result"
    Write-Warn "  Si el error persiste, proba manualmente en CMD:"
    Write-Warn "  copy /b `"$tempFile`" `"\\localhost\$PrinterName`""

    return $false
}

# ── API CALL ──────────────────────────────────────────────────
$Script:ActiveUrl = $ServerUrl   # Se asigna al conectar

function Call-Api {
    param([string]$Method, [string]$Path)
    try {
        return Invoke-RestMethod -Uri "$Script:ActiveUrl/api/agent/$Path" -Headers $Headers -Method $Method -ErrorAction Stop
    } catch {
        Add-Log "Error API $Method $Path : $_" "ERROR"
        return $null
    }
}

# ── STARTUP CHECK ─────────────────────────────────────────────
Clear-Host
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║   AGENTE DE IMPRESION ZEBRA ZT411        ║" -ForegroundColor Cyan
Write-Host "  ║   Sistema de Garantias — Paraiso          ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Add-Log "=== INICIO DEL AGENTE ==="

# 1) Verificar conexion — prueba localhost primero, VPS como fallback
Write-Step "PASO 1: Verificando conexion al servidor ..."
$connected = $false
$urlsToTry = @(
    @{Url = $ServerUrl; Label = "localhost (Laragon)"},
    @{Url = $VpsUrl; Label = "VPS producción"}
)
foreach ($target in $urlsToTry) {
    Write-Info "Probando $($target.Label): $($target.Url)..."
    for ($i = 1; $i -le 2; $i++) {
        try {
            $r = Invoke-RestMethod -Uri "$($target.Url)/api/agent/status" -Headers $Headers -Method GET -ErrorAction Stop
            if ($r.success) {
                $Script:ActiveUrl = $target.Url
                Write-Ok "Conectado a $($target.Label) ($($target.Url))"
                Add-Log "Conectado a $($target.Label): $($target.Url)"
                $connected = $true
                break
            }
        } catch {
            Write-Warn "  $($target.Label): intento $i/2 falló"
            Start-Sleep -Seconds 1
        }
    }
    if ($connected) { break }
}

if (-not $connected) {
    Write-Error "NO se pudo conectar a ningún servidor"
    Write-Warn "Verifica:"
    Write-Warn "  1. Que la app esté corriendo (php artisan serve)"
    Write-Warn "  2. O que el VPS esté accesible (http://108.174.152.179:8081/admin)"
    Write-Warn "  3. Que el AgentKey coincida con el del .env"
    Write-Warn ""
    Write-Warn "  El script se va a cerrar en 15 segundos..."
    Start-Sleep -Seconds 15
    exit 1
}

# 2) Verificar impresora
Write-Step "PASO 2: Verificando impresora compartida ..."
$printerOk = Test-PrinterShare

if (-not $printerOk) {
    Write-Warn ""
    Write-Warn "La impresora no se detecta pero el script va a intentar igual."
    Write-Warn "Si falla, segui las instrucciones de arriba para compartirla."
}

# 3) Iniciar loop
Write-Step "PASO 3: AGENTE ACTIVO — esperando colas de impresion"
Write-Info "Servidor:  $ActiveUrl"
Write-Info "Impresora: $PrinterName"
Write-Info "Intervalo: cada $PollInterval segundos"
Write-Info "Log:       $LogFile"
if ($printerOk) {
    Write-Ok "TODO LISTO — el agente ya esta corriendo"
} else {
    Write-Warn "AGENTE ACTIVO pero falta configurar la impresora"
}
Write-Host ""
Write-Host "  Crea un lote en el sistema y hace click en" -ForegroundColor Yellow
Write-Host "  'Imprimir en Zebra'. La etiqueta aparece aca." -ForegroundColor Yellow
Write-Host "  NO CIERRES ESTA VENTANA." -ForegroundColor Yellow
Write-Host ""

Add-Log "Agente iniciado — esperando colas"

# ── MAIN LOOP ─────────────────────────────────────────────────
while ($true) {
    try {
        $result = Call-Api -Method GET -Path "pending"

        if ($result -and $result.success -and $result.queues -and $result.queues.Count -gt 0) {
            foreach ($q in $result.queues) {
                $name = if ($q.printer_name) { $q.printer_name } else { "(sin nombre)" }
                Write-Step "Cola #$($q.queue_id) — $($q.total_items) etiqueta(s) para $name"
                Add-Log "Procesando cola #$($q.queue_id) con $($q.total_items) items"

                foreach ($item in $q.items) {
                    $ok = Send-Zpl -Zpl $item.zpl_content -ItemId $item.item_id

                    if ($ok) {
                        Call-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/complete"
                        Write-Ok "Item #$($item.item_id) marcado como impreso en el servidor"
                    } else {
                        Call-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/failed"
                        Write-Error "Item #$($item.item_id) marcado como fallido en el servidor"
                    }
                }

                $final = Call-Api -Method POST -Path "$($q.queue_id)/complete"
                if ($final) {
                    $emoji = if ($final.status -eq "completed") { "✔" } else { "⚠" }
                    Write-Step "$emoji Cola #$($q.queue_id) finalizada — Estado: $($final.status)"
                    Add-Log "Cola #$($q.queue_id) finalizada: $($final.status)" "OK"
                }
            }
        }
    } catch {
        Add-Log "Error en loop: $_" "CRITICAL"
        Write-Error "Error inesperado: $_"
    }

    Start-Sleep -Seconds $PollInterval
}
