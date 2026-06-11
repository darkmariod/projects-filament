<#
.SYNOPSIS
    Agente de impresión para Zebra ZT411 en Windows.
    Consulta el VPS cada N segundos, descarga ZPL y envía a la impresora USB.

.DESCRIPTION
    1. Hace GET /api/agent/pending al VPS (con X-Agent-Key)
    2. Si hay colas pendientes, procesa cada etiqueta:
       - Escribe el ZPL a un archivo temporal
       - Envía a la impresora con copy /b
       - Marca como completa o fallida en el VPS
    3. Finaliza la cola cuando todas las etiquetas se procesaron

.NOTES
    Ejecutar como: PowerShell -ExecutionPolicy Bypass -File print-agent.ps1
    Dejar la ventana abierta mientras se imprime.
#>

# ============================================
# CONFIGURACIÓN — Cambiá estos valores
# ============================================
$VpsUrl       = "https://sistema-garantias.com"   # La URL de tu VPS
$AgentKey     = "change-me-in-production"          # Mismo valor que PRINT_AGENT_KEY en el .env del VPS
$PrinterName  = "Zebra ZT411"                      # Nombre exacto del recurso compartido USB
$PollInterval = 10                                  # Segundos entre cada consulta

# ============================================
# NO TOQUES DE ACÁ PARA ABAJO
# ============================================
$Headers = @{
    "X-Agent-Key" = $AgentKey
    "Accept"      = "application/json"
}

$TempDir = "$env:TEMP\zebra-print-agent"
$LogFile = "$TempDir\agent.log"

if (-not (Test-Path $TempDir)) {
    New-Item -ItemType Directory -Path $TempDir -Force | Out-Null
}

# ── Funciones ────────────────────────────────────────────────

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$Level] $Message"
    Add-Content -Path $LogFile -Value $line
    Write-Host $line
}

function Send-Zpl {
    param([string]$Zpl, [int]$ItemId)

    $tempFile = "$TempDir\label-$ItemId.zpl"

    # ZPL requiere codificación ISO-8859-1 para caracteres especiales
    try {
        [System.IO.File]::WriteAllText($tempFile, $Zpl, [System.Text.Encoding]::GetEncoding(28591))
    } catch {
        Write-Log "Error escribiendo archivo ZPL #$ItemId : $_" "ERROR"
        return $false
    }

    Write-Log "Enviando etiqueta #$ItemId a \\localhost\$PrinterName"

    # copy /b es el método estándar para enviar raw ZPL a impresoras compartidas en Windows
    $result = cmd /c "copy /b `"$tempFile`" `"\\localhost\$PrinterName`" 2>&1"
    $ok = $LASTEXITCODE -eq 0 -and $result -notmatch "(error|no found|cannot find|no se encuentra)"

    if ($ok) {
        Write-Log "✔ Etiqueta #$ItemId impresa correctamente" "OK"
    } else {
        Write-Log "✘ Error imprimiendo #$ItemId : $result" "FAIL"
    }

    return $ok
}

function Call-Api {
    param([string]$Method, [string]$Path)

    try {
        return Invoke-RestMethod -Uri "$VpsUrl/api/agent/$Path" -Headers $Headers -Method $Method -ErrorAction Stop
    } catch {
        Write-Log "Error en API $Method $Path : $_" "ERROR"
        return $null
    }
}

function Test-VpsConnection {
    $result = Call-Api -Method GET -Path "status"
    if ($result -and $result.success) {
        Write-Log "✔ Conectado al VPS: $($result.server)" "OK"
        Write-Log "  Colas pendientes: $($result.pending) | En proceso: $($result.processing)"
        return $true
    }
    return $false
}

# ── Loop principal ────────────────────────────────────────────

Write-Log "═══════════════════════════════════════════"
Write-Log "  AGENTE DE IMPRESIÓN ZEBRA"
Write-Log "═══════════════════════════════════════════"
Write-Log "VPS:       $VpsUrl"
Write-Log "Impresora: $PrinterName"
Write-Log "Temp dir:  $TempDir"
Write-Log "═══════════════════════════════════════════"

$connected = Test-VpsConnection
if (-not $connected) {
    Write-Log "⚠ No se pudo conectar al VPS. Verificá URL y AgentKey." "WARN"
}

while ($true) {
    try {
        $result = Call-Api -Method GET -Path "pending"

        if ($result -and $result.success -and $result.queues -and $result.queues.Count -gt 0) {
            foreach ($q in $result.queues) {
                $qName = if ($q.printer_name) { $q.printer_name } else { "(sin nombre)" }
                Write-Log "════ Procesando cola #$($q.queue_id) — $($q.total_items) etiquetas para $qName"

                foreach ($item in $q.items) {
                    $ok = Send-Zpl -Zpl $item.zpl_content -ItemId $item.item_id

                    if ($ok) {
                        Call-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/complete"
                    } else {
                        Call-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/failed"
                    }
                }

                $final = Call-Api -Method POST -Path "$($q.queue_id)/complete"
                if ($final) {
                    Write-Log "Cola #$($q.queue_id) finalizada → $($final.status)" "OK"
                }
            }
        }
    } catch {
        Write-Log "Error en loop principal: $_" "CRITICAL"
    }

    Start-Sleep -Seconds $PollInterval
}
