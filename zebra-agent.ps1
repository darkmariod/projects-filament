<#
.SYNOPSIS
  Agente de impresión para Zebra ZT411 — Sistema Garantías
.DESCRIPTION
  Consulta el VPS cada 30s (adaptativo: 5s si hay trabajo),
  descarga ZPL pendientes y los envía a la Zebra por USB (copy /b).
  Reporta resultados al servidor.

  Modo interactivo (loop continuo):
    .\zebra-agent.ps1

  Una sola iteración (ideal para atajo de escritorio):
    .\zebra-agent.ps1 -once

  Instalar como tarea programada (Task Scheduler):
    .\zebra-agent.ps1 -install

  Desinstalar tarea:
    .\zebra-agent.ps1 -uninstall

  Ver estado:
    .\zebra-agent.ps1 -status
#>

param(
    [switch]$install,
    [switch]$uninstall,
    [switch]$status,
    [switch]$once
)

# ═══════════════════════════════════════════════════════════════
# CONFIGURACIÓN — CAMBIAR ESTOS VALORES
# ═══════════════════════════════════════════════════════════════

$Script:ApiBaseUrl    = "http://108.174.152.179:8081/api/agent"
$Script:ApiKey        = "zebra-agent-key-2026"
$Script:PrinterName   = "Zebra ZT411"    # CAMBIAR al nombre exacto de su Zebra
$Script:PollInterval  = 30               # segundos entre consultas
$Script:TaskName      = "ZebraPrintAgent"
$Script:LogPath       = Join-Path $env:TEMP "zebra-agent.log"

# ═══════════════════════════════════════════════════════════════
# FUNCIONES
# ═══════════════════════════════════════════════════════════════

function Write-Log {
    param([string]$Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$timestamp] $Message"
    Write-Host $line
    Add-Content -Path $Script:LogPath -Value $line -Force
}

function Test-Printer {
    <#
    .SYNOPSIS
      Verifica que la impresora exista en Windows.
    #>
    $printer = Get-Printer -Name $Script:PrinterName -ErrorAction SilentlyContinue
    if (-not $printer) {
        Write-Log "ERROR: Impresora '$Script:PrinterName' no encontrada en Windows."
        Write-Log "Ejecutá: Get-Printer | Format-Table Name, DriverName, PortName"
        return $false
    }
    Write-Log "Impresora encontrada: $($printer.Name) | $($printer.DriverName)"
    if ($printer.PrinterStatus -ne 3) {
        Write-Log "⚠  Estado: $($printer.PrinterStatus) (3=Ready). Verificá la impresora."
    } else {
        Write-Log "✓ Impresora lista (status: Ready)."
    }
    return $true
}

function Invoke-Api {
    <#
    .SYNOPSIS
      Llama a un endpoint de la API del VPS.
    #>
    param(
        [string]$Method = "GET",
        [string]$Endpoint,
        [object]$Body = $null
    )

    $url = "$Script:ApiBaseUrl$Endpoint"
    $headers = @{
        "X-Agent-Key" = $Script:ApiKey
        "Accept"      = "application/json"
    }

    $params = @{
        Uri             = $url
        Method          = $Method
        Headers         = $headers
        ContentType     = "application/json"
        UseBasicParsing = $true
        TimeoutSec      = 30
    }

    if ($Body -and $Method -eq "POST") {
        $params.Body = ($Body | ConvertTo-Json)
    }

    try {
        $response = Invoke-RestMethod @params
        return $response
    }
    catch {
        Write-Log "ERROR de conexión con el servidor: $($_.Exception.Message)"
        return $null
    }
}

function Send-ToZebra {
    <#
    .SYNOPSIS
      Envía contenido ZPL a la impresora Zebra por USB.
      Usa copy /b a la ruta UNC del printer share.
    #>
    param([string]$ZplContent)

    try {
        $tempFile = [System.IO.Path]::GetTempFileName() + ".zpl"
        [System.IO.File]::WriteAllText($tempFile, $ZplContent, [System.Text.Encoding]::UTF8)

        $printerPath = "\\localhost\$Script:PrinterName"

        # Intentar 1: copy /b
        $result = cmd /c "copy /b `"$tempFile`" `"$printerPath`" > NUL 2>&1"
        if ($LASTEXITCODE -eq 0) {
            Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
            return $true
        }

        # Intentar 2: fopen directo (si copy falla por permisos)
        try {
            $stream = [System.IO.File]::OpenWrite($printerPath)
            $bytes = [System.Text.Encoding]::UTF8.GetBytes($ZplContent)
            $stream.Write($bytes, 0, $bytes.Length)
            $stream.Close()
            Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
            return $true
        }
        catch {
            Write-Log "  Falló fopen UNC: $($_.Exception.Message)"
        }

        # Intentar 3: PowerShell Out-Printer
        try {
            Get-Content $tempFile -Raw | Out-Printer -Name $Script:PrinterName
            Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
            return $true
        }
        catch {
            Write-Log "  Falló Out-Printer: $($_.Exception.Message)"
        }

        Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
        return $false
    }
    catch {
        Write-Log "  Excepción al imprimir: $($_.Exception.Message)"
        return $false
    }
}

function Process-Pending {
    <#
    .SYNOPSIS
      Consulta colas pendientes y procesa cada item.
      Returns $true si había trabajo, $false si no.
    #>
    Write-Log "Consultando colas pendientes..."
    $response = Invoke-Api -Endpoint "/pending"

    if (-not $response -or -not $response.success) {
        return $false
    }

    $queues = $response.queues
    if (-not $queues -or $queues.Count -eq 0) {
        Write-Log "No hay colas pendientes."
        return $false
    }

    Write-Log "Se encontraron $($queues.Count) cola(s) para procesar."

    foreach ($queue in $queues) {
        Write-Log "──────────────────────────────────────────"
        Write-Log "Cola #$($queue.queue_id) — $($queue.total_items) item(s)"
        Write-Log "Impresora: $($queue.printer_name)"

        if ($queue.printer_name -ne $Script:PrinterName) {
            Write-Log "⚠  El nombre de impresora NO coincide. Configurado: '$Script:PrinterName', esperado: '$($queue.printer_name)'"
            Write-Log "   Si el nombre es correcto, actualizá `$Script:PrinterName en el script."
        }

        foreach ($item in $queue.items) {
            Write-Log "  Item #$($item.item_id) (seq $($item.sequence)): imprimiendo..."

            $success = Send-ToZebra -ZplContent $item.zpl_content

            if ($success) {
                $result = Invoke-Api -Method POST -Endpoint "/$($queue.queue_id)/item/$($item.item_id)/complete"
                if ($result -and $result.success) {
                    Write-Log "  ✓ Item #$($item.item_id) impreso y reportado."
                } else {
                    Write-Log "  ⚠  Item impreso pero NO se pudo reportar al servidor."
                }
            } else {
                $result = Invoke-Api -Method POST -Endpoint "/$($queue.queue_id)/item/$($item.item_id)/failed"
                Write-Log "  ✗ Item #$($item.item_id) FALLÓ al imprimir."
            }

            Start-Sleep -Milliseconds 200
        }

        # Marcar cola como completada
        $result = Invoke-Api -Method POST -Endpoint "/$($queue.queue_id)/complete"
        if ($result -and $result.success) {
            Write-Log "✓ Cola #$($queue.queue_id) finalizada: $($result.status)"
        }
    }

    return $true
}

function Install-Task {
    <#
    .SYNOPSIS
      Instala el agente como tarea programada de Windows.
      Se ejecuta cada minuto (o al arrancar la PC).
    #>
    $scriptPath = $MyInvocation.MyCommand.Path
    if (-not $scriptPath) {
        $scriptPath = Join-Path $PSScriptRoot "zebra-agent.ps1"
    }

    $action = New-ScheduledTaskAction `
        -Execute "powershell.exe" `
        -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -once"

    $trigger = @(
        New-ScheduledTaskTrigger -AtStartup
        New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
    )

    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

    Register-ScheduledTask `
        -TaskName $Script:TaskName `
        -Action $action `
        -Trigger $trigger `
        -Principal $principal `
        -Description "Agente de impresión Zebra para Sistema Garantías" `
        -Force

    Write-Host ""
    Write-Host "✓ Tarea '$Script:TaskName' instalada correctamente." -ForegroundColor Green
    Write-Host "  Se ejecuta al iniciar Windows y cada 1 minuto." -ForegroundColor Green
    Write-Host "  Logs: $Script:LogPath" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Para verificar: Get-ScheduledTask -TaskName '$Script:TaskName' | fl" -ForegroundColor Cyan
}

function Uninstall-Task {
    Unregister-ScheduledTask -TaskName $Script:TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "✓ Tarea '$Script:TaskName' desinstalada." -ForegroundColor Yellow
}

function Show-Status {
    Write-Host ""
    Write-Host "═══════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "  Zebra Print Agent — Estado" -ForegroundColor Cyan
    Write-Host "═══════════════════════════════════════" -ForegroundColor Cyan
    Write-Host ""

    # Verificar tarea
    $task = Get-ScheduledTask -TaskName $Script:TaskName -ErrorAction SilentlyContinue
    if ($task) {
        Write-Host "Tarea programada: INSTALADA" -ForegroundColor Green
        Write-Host "  Estado: $($task.State)"
        Write-Host "  Última ejecución: $($task.LastRunTime)"
        Write-Host "  Resultado: $($task.LastTaskResult)"
    } else {
        Write-Host "Tarea programada: NO INSTALADA" -ForegroundColor Yellow
    }

    # Verificar impresora
    $printer = Get-Printer -Name $Script:PrinterName -ErrorAction SilentlyContinue
    if ($printer) {
        Write-Host "Impresora: $($printer.Name)" -ForegroundColor Green
        Write-Host "  Driver: $($printer.DriverName)"
        Write-Host "  Puerto: $($printer.PortName)"
        Write-Host "  Estado: $($printer.PrinterStatus) (3=Ready)"
    } else {
        Write-Host "Impresora: NO ENCONTRADA" -ForegroundColor Red
        Write-Host "  Nombre configurado: $Script:PrinterName"
        Write-Host "  Para ver impresoras: Get-Printer | Format-Table Name, DriverName"
    }

    # Probar conexión con servidor
    Write-Host ""
    Write-Host "Probando conexión con servidor..." -ForegroundColor Gray
    $response = Invoke-Api -Endpoint "/status"
    if ($response -and $response.success) {
        Write-Host "Servidor: CONECTADO" -ForegroundColor Green
        Write-Host "  Pendientes: $($response.pending)"
        Write-Host "  Procesando: $($response.processing)"
        Write-Host "  Hora server: $($response.time)"
    } else {
        Write-Host "Servidor: SIN CONEXIÓN" -ForegroundColor Red
        Write-Host "  URL: $Script:ApiBaseUrl"
        Write-Host "  Verificá que la IP/URL sea correcta y que el servidor esté accesible."
    }

    Write-Host ""
    Write-Host "Log: $Script:LogPath" -ForegroundColor Gray
    if (Test-Path $Script:LogPath) {
        Write-Host "Últimas líneas del log:" -ForegroundColor Gray
        Get-Content $Script:LogPath -Tail 5
    }
}

# ═══════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════

Write-Log "═══════════════════════════════════════"
Write-Log "Zebra Print Agent iniciado"
Write-Log "Servidor: $Script:ApiBaseUrl"
Write-Log "Impresora: $Script:PrinterName"
Write-Log "═══════════════════════════════════════"

# ── Modos ────────────────────────────────────────────────────

if ($install) {
    Install-Task
    return
}

if ($uninstall) {
    Uninstall-Task
    return
}

if ($status) {
    Show-Status
    return
}

# ── Modo -once ───────────────────────────────────────────────
# Ejecuta UNA iteración y sale. Ideal para triggers rápidos
# desde el escritorio o webhook local.
# ─────────────────────────────────────────────────────────────

if ($once) {
    Write-Log "Modo -once: ejecutando una iteración..."
    Test-Printer | Out-Null
    Process-Pending
    Write-Log "Modo -once: finalizado."
    return
}

# ── Modo interactivo (loop con polling adaptativo) ───────────

# Verificar impresora al inicio
$printerOk = Test-Printer
if (-not $printerOk) {
    Write-Log ""
    Write-Log "⚠  La impresora no se encuentra. Revisá el nombre en `$Script:PrinterName"
    Write-Log "   Ejecutá: Get-Printer | Format-Table Name, DriverName, PortName"
    Write-Log "   y actualizá el script con el nombre correcto."
    Write-Log ""
    Write-Log "El agente va a seguir intentando igual..."
}

# Polling adaptativo: si hay colas pendientes, acelera a 5s
# Si no hay, vuelve a PollInterval normal (30s)
$currentInterval = $Script:PollInterval

# Loop principal
while ($true) {
    $hadWork = Process-Pending

    if ($hadWork) {
        $currentInterval = 5
    } else {
        $currentInterval = $Script:PollInterval
    }

    Write-Log "Esperando $currentInterval segundos..."
    Start-Sleep -Seconds $currentInterval
}
