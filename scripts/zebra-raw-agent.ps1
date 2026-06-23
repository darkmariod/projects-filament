<#
.SYNOPSIS
  Motor del agente de impresion Zebra — Sistema Garantias
.DESCRIPTION
  NO ejecutar directamente. Usar los archivos .cmd:
    1-VER-IMPRESORAS.cmd      → -ListPrinters
    2-TEST-IMPRESION.cmd      → -TestPrint
    3-INICIAR-AGENTE.cmd      → -Loop
    4-INSTALAR-INICIO-AUTOMATICO.cmd → -Install
    5-VER-ESTADO.cmd          → -Status
#>

param(
    [switch]$ListPrinters,
    [switch]$TestPrint,
    [switch]$Loop,
    [switch]$Install,
    [switch]$Status,
    [string]$PrinterName = "Zebra ZT411"
)

# ============================================================
# CONFIGURACION — Solo cambiar si el cliente lo pide
# ============================================================
$Script:VpsUrl     = "http://108.174.152.179:8081"
$Script:AgentKey   = "zebra-agent-key-2026"
$Script:PollIntSec = 10           # segundos entre consultas al VPS
$Script:TaskName   = "ZebraPrintAgent"
$Script:Headers    = @{ "X-Agent-Key" = $Script:AgentKey; "Accept" = "application/json" }
$Script:LogPath    = "$env:TEMP\zebra-agent.log"
$Script:TempDir    = "$env:TEMP\zebra-print"

# ============================================================
# FUNCIONES DE CONSOLA
# ============================================================
function Write-Info  { Write-Host "  [INFO] $args" -ForegroundColor Cyan }
function Write-Ok    { Write-Host "  [OK]   $args" -ForegroundColor Green }
function Write-Warn  { Write-Host "  [!]    $args" -ForegroundColor Yellow }
function Write-Error { Write-Host "  [ERROR] $args" -ForegroundColor Red }
function Write-Step  { Write-Host "`n>>> $args" -ForegroundColor Magenta }
function Write-Title { Write-Host "`n  $args" -ForegroundColor White }

function Add-Log {
    param([string]$M, [string]$L = "INFO")
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$L] $M"
    Add-Content -Path $Script:LogPath -Value $line -ErrorAction SilentlyContinue
}

# ============================================================
#  MODO 1: LISTAR IMPRESORAS
# ============================================================
function Do-ListPrinters {
    Clear-Host
    Write-Host ""
    Write-Host "  ╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "  ║   IMPRESORAS INSTALADAS EN ESTA PC     ║" -ForegroundColor Cyan
    Write-Host "  ╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    $printers = Get-Printer -ErrorAction SilentlyContinue
    if (-not $printers) {
        Write-Error "No se encontraron impresoras instaladas."
        Write-Warn "Anda a: Menu Inicio > 'Impresoras' > 'Agregar impresora'"
        return
    }

    Write-Info "Impresoras disponibles:"
    Write-Host ""
    Write-Host "  NOMBRE (copiar exactamente)" -ForegroundColor Yellow
    Write-Host "  ────────────────────────────" -ForegroundColor Gray
    foreach ($p in $printers) {
        Write-Host "  $($p.Name)" -ForegroundColor White
    }
    Write-Host ""

    # Sugerir la primera Zebra que encuentre
    $zebra = $printers | Where-Object { $_.Name -match "(?i)zebra|zpl|zt" } | Select-Object -First 1
    if ($zebra) {
        Write-Ok "Posible impresora Zebra detectada: '$($zebra.Name)'"
        Write-Info "Usa ese nombre en los archivos .cmd (abrilo con Notepad y cambia PRINTER_NAME)"
    } else:
        Write-Warn "No se detecto una impresora Zebra automaticamente."
        Write-Info "Si la Zebra esta instalada, fijate el nombre exacto en la lista de arriba."
    }

    Write-Host ""
    Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

# ============================================================
#  FUNCIONES DE ENVIO A IMPRESORA
# ============================================================
function Test-PrinterExists {
    param([string]$Name)
    $p = Get-Printer -Name $Name -ErrorAction SilentlyContinue
    return ($p -ne $null)
}

function Send-ZplToPrinter {
    param([string]$ZplContent, [string]$LabelId = "test")

    if (-not (Test-PrinterExists $Script:PrinterName)) {
        return $false, "Impresora '$Script:PrinterName' no encontrada"
    }

    # Crear archivo temporal con codificacion adecuada
    if (-not (Test-Path $Script:TempDir)) { New-Item -ItemType Directory -Path $Script:TempDir -Force | Out-Null }
    $tempFile = "$Script:TempDir\label-$LabelId.zpl"
    try {
        [System.IO.File]::WriteAllText($tempFile, $ZplContent, [System.Text.Encoding]::GetEncoding(28591))
    } catch {
        return $false, "No se pudo escribir archivo temporal"
    }

    # ── Metodo 1: copy /b (el mas confiable) ──────────────────
    $printerPath = "\\localhost\$($Script:PrinterName)"
    $result = cmd /c "copy /b `"$tempFile`" `"$printerPath`" 2>&1"
    if ($LASTEXITCODE -eq 0 -and $result -notmatch "(?i)(error|not found|cannot find|no se encuentra|syntax)") {
        Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
        return $true, "Impreso por copy /b"
    }

    # ── Metodo 2: fopen directo UNC ──────────────────────────
    try {
        $stream = [System.IO.File]::OpenWrite($printerPath)
        $bytes = [System.Text.Encoding]::GetEncoding(28591).GetBytes($ZplContent)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
        return $true, "Impreso por escritura directa"
    } catch {
        # fallthrough
    }

    # ── Metodo 3: Out-Printer ────────────────────────────────
    try {
        Get-Content $tempFile -Raw | Out-Printer -Name $Script:PrinterName
        Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
        return $true, "Impreso por Out-Printer"
    } catch {
        # fallthrough
    }

    Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
    return $false, "Ningun metodo de impresion funciono. ¿La impresora esta compartida?"
}

# ============================================================
#  MODO 2: TEST DE IMPRESION
# ============================================================
function Do-TestPrint {
    Clear-Host
    Write-Host ""
    Write-Host "  ╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "  ║   TEST DE IMPRESION ZEBRA              ║" -ForegroundColor Cyan
    Write-Host "  ╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    Write-Info "Impresora configurada: $Script:PrinterName"

    # Verificar que exista
    if (-not (Test-PrinterExists $Script:PrinterName)) {
        Write-Error "La impresora '$Script:PrinterName' NO esta instalada en Windows."
        Write-Warn "Ejecuta primero 1-VER-IMPRESORAS.cmd para ver el nombre exacto."
        Write-Host ""
        Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
        return
    }

    Write-Ok "Impresora encontrada en Windows"

    # Generar ZPL de prueba
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

    Write-Info "Enviando etiqueta de prueba..."
    $success, $method = Send-ZplToPrinter -ZplContent $testZpl -LabelId "test"

    if ($success) {
        Write-Ok "¡ETIQUETA IMPRESA CORRECTAMENTE!"
        Write-Info "Metodo: $method"
        Write-Host ""
        Write-Host "  ✓ La Zebra funciona. Ya podes usar 4-INSTALAR-INICIO-AUTOMATICO.cmd" -ForegroundColor Green
    } else {
        Write-Error "No se pudo imprimir."
        Write-Info "Motivo: $method"
        Write-Host ""
        Write-Warn "Solucion:"
        Write-Warn "1. Anda a Menu Inicio > 'Impresoras'"
        Write-Warn "2. Click derecho en '$Script:PrinterName' > 'Propiedades de impresora'"
        Write-Warn "3. Pestaña 'Compartir' > Marcar 'Compartir esta impresora'"
        Write-Warn "4. Anota el nombre del recurso compartido"
        Write-Warn "5. Volve a 1-VER-IMPRESORAS.cmd para confirmar el nombre exacto"
    }

    Write-Host ""
    Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

# ============================================================
#  FUNCIONES DE API
# ============================================================
function Invoke-Api {
    param([string]$Method = "GET", [string]$Path)
    $url = "$Script:VpsUrl/api/agent/$Path"
    try {
        $params = @{
            Uri = $url
            Method = $Method
            Headers = $Script:Headers
            ContentType = "application/json"
            UseBasicParsing = $true
            TimeoutSec = 30
        }
        if ($Method -eq "POST") {
            $params.Body = "{}"
        }
        return Invoke-RestMethod @params
    } catch {
        Add-Log "Error API $Method $Path : $_" "ERROR"
        return $null
    }
}

# ============================================================
#  MODO 3: LOOP (agente continuo)
# ============================================================
function Do-Loop {
    Clear-Host
    Write-Host ""
    Write-Host "  ╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "  ║   AGENTE DE IMPRESION ZEBRA            ║" -ForegroundColor Cyan
    Write-Host "  ║   Sistema Garantias — Paraiso          ║" -ForegroundColor Cyan
    Write-Host "  ╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    # ── 1. Verificar impresora ──
    Write-Step "PASO 1: Verificando impresora '$Script:PrinterName' ..."
    $printerOk = Test-PrinterExists $Script:PrinterName
    if ($printerOk) {
        Write-Ok "Impresora encontrada: $Script:PrinterName"
    } else {
        Write-Error "Impresora '$Script:PrinterName' NO encontrada"
        Write-Warn "Verifica el nombre con 1-VER-IMPRESORAS.cmd y actualiza los .cmd"
    }

    # ── 2. Verificar VPS ──
    Write-Step "PASO 2: Conectando al servidor $Script:VpsUrl ..."
    $status = Invoke-Api -Method GET -Path "status"
    $connected = ($status -and $status.success)
    if ($connected) {
        Write-Ok "Conectado al servidor"
    } else {
        Write-Error "No se pudo conectar al servidor"
        Write-Warn "Verifica que $Script:VpsUrl este accesible desde esta PC"
    }

    $anyOk = $printerOk -or $connected
    if (-not $anyOk) {
        Write-Host ""
        Write-Error "Nada funciona. Revisa los pasos anteriores."
        Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
        return
    }

    # ── 3. Loop principal ──
    Write-Step "PASO 3: AGENTE ACTIVO"
    Write-Info "Servidor:  $Script:VpsUrl"
    Write-Info "Impresora: $Script:PrinterName"
    Write-Info "Intervalo: cada $Script:PollIntSec segundos"
    Write-Info "Log:       $Script:LogPath"
    Write-Host ""
    if ($printerOk -and $connected) {
        Write-Ok "TODO LISTO — el agente ya esta esperando etiquetas"
    } elseif ($printerOk) {
        Write-Warn "Agente activo pero sin conexion al servidor (revisa internet)"
    } elseif ($connected) {
        Write-Warn "Agente activo pero sin impresora (revisa el nombre)"
    }
    Write-Host ""
    Write-Host "  Crea un lote de etiquetas en el sistema y hace click en" -ForegroundColor Yellow
    Write-Host "  'Imprimir en Zebra'. La etiqueta aparece aca solo." -ForegroundColor Yellow
    Write-Host "  NO CIERRES ESTA VENTANA mientras quieras imprimir." -ForegroundColor Yellow
    Write-Host ""

    Add-Log "=== AGENTE INICIADO ===" "OK"

    $currentInterval = $Script:PollIntSec
    while ($true) {
        try {
            $result = Invoke-Api -Method GET -Path "pending"

            if ($result -and $result.success -and $result.queues -and $result.queues.Count -gt 0) {
                $currentInterval = 3  # acelerar si hay trabajo
                foreach ($q in $result.queues) {
                    $name = if ($q.printer_name) { $q.printer_name } else { $Script:PrinterName }
                    Write-Step "Cola #$($q.queue_id) — $($q.total_items) etiqueta(s) para $name"
                    Add-Log "Procesando cola #$($q.queue_id)" "OK"

                    foreach ($item in $q.items) {
                        Write-Info "Imprimiendo etiqueta #$($item.item_id) ..."
                        $success, $method = Send-ZplToPrinter -ZplContent $item.zpl_content -LabelId $item.item_id

                        if ($success) {
                            Invoke-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/complete" | Out-Null
                            Write-Ok "Item #$($item.item_id) impreso y reportado"
                            Add-Log "Item #$($item.item_id) impreso" "OK"
                        } else:
                            Invoke-Api -Method POST -Path "$($q.queue_id)/item/$($item.item_id)/failed" | Out-Null
                            Write-Error "Item #$($item.item_id) FALLO"
                            Add-Log "Item #$($item.item_id) fallo: $method" "ERROR"
                        }

                        Start-Sleep -Milliseconds 200
                    }

                    $final = Invoke-Api -Method POST -Path "$($q.queue_id)/complete"
                    if ($final) {
                        $emoji = if ($final.status -eq "completed") { "✓" } else { "⚠" }
                        Write-Step "$emoji Cola #$($q.queue_id) finalizada — Estado: $($final.status)"
                        Add-Log "Cola #$($q.queue_id) finalizada: $($final.status)" "OK"
                    }
                }
            } else:
                $currentInterval = $Script:PollIntSec
            }
        } catch {
            Add-Log "Error en loop: $_" "CRITICAL"
        }

        Write-Info "Esperando $currentInterval segundos..."
        Start-Sleep -Seconds $currentInterval
    }
}

# ============================================================
#  MODO 4: INSTALAR COMO TAREA PROGRAMADA
# ============================================================
function Do-Install {
    Clear-Host
    Write-Host ""
    Write-Host "  ╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "  ║   INSTALAR AGENTE AUTOMATICO           ║" -ForegroundColor Cyan
    Write-Host "  ╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    # Verificar que corre como admin
    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        Write-Error "Este paso requiere ejecutar como ADMINISTRADOR."
        Write-Warn "Cerras esta ventana, haces clic derecho en el .cmd"
        Write-Warn "y elegis 'Ejecutar como administrador'."
        Write-Host ""
        Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
        return
    }
    Write-Ok "Ejecutando como administrador ✓"

    # Verificar impresora
    Write-Info "Verificando impresora '$Script:PrinterName' ..."
    if (Test-PrinterExists $Script:PrinterName) {
        Write-Ok "Impresora encontrada ✓"
    } else {
        Write-Warn "La impresora '$Script:PrinterName' no se encuentra."
        Write-Warn "Podes cambiar el nombre mas tarde editando los archivos .cmd"
    }

    # Ubicar el script
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $scriptPath = Join-Path $scriptDir "zebra-raw-agent.ps1"
    if (-not (Test-Path $scriptPath)) {
        # Fallback: buscar en el directorio actual
        $scriptPath = Join-Path (Get-Location) "zebra-raw-agent.ps1"
    }
    if (-not (Test-Path $scriptPath)) {
        Write-Error "No se encuentra zebra-raw-agent.ps1"
        Write-Warn "Asegurate de extraer TODOS los archivos del ZIP juntos"
        Write-Host ""
        Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
        return
    }

    Write-Info "Script encontrado: $scriptPath"
    Write-Info "Impresora configurada: $Script:PrinterName"

    # Crear accion: ejecuta el script en modo loop cada vez
    $action = New-ScheduledTaskAction `
        -Execute "powershell.exe" `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`" -Loop -PrinterName `"$($Script:PrinterName)`""

    $triggers = @(
        New-ScheduledTaskTrigger -AtStartup
        New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
    )

    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

    try {
        Register-ScheduledTask `
            -TaskName $Script:TaskName `
            -Action $action `
            -Trigger $triggers `
            -Principal $principal `
            -Description "Agente de impresion Zebra — Sistema Garantias" `
            -Force `
            -ErrorAction Stop

        Write-Host ""
        Write-Ok "✓ AGENTE INSTALADO CORRECTAMENTE"
        Write-Info "Tarea: $Script:TaskName"
        Write-Host ""
        Write-Info "La PC ya imprime automaticamente cuando el sistema manda etiquetas."
        Write-Info "No hace falta hacer nada mas. FUNCIONA solo."
        Write-Host ""
        Write-Info "Para verificar el estado, ejecuta 5-VER-ESTADO.cmd"
    } catch {
        Write-Error "Error al instalar: $_"
        Write-Warn "Intenta ejecutar el .cmd como Administrador (clic derecho > Ejecutar como administrador)"
    }

    Write-Host ""
    Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

# ============================================================
#  MODO 5: VER ESTADO
# ============================================================
function Do-Status {
    Clear-Host
    Write-Host ""
    Write-Host "  ╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "  ║   ESTADO DEL AGENTE ZEBRA              ║" -ForegroundColor Cyan
    Write-Host "  ╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    # ── Tarea programada ──
    Write-Info "Tarea programada:"
    $task = Get-ScheduledTask -TaskName $Script:TaskName -ErrorAction SilentlyContinue
    if ($task) {
        Write-Ok "INSTALADA — $($task.State)"
        Write-Info "  Ultima ejecucion: $($task.LastRunTime)"
        Write-Info "  Resultado: $($task.LastTaskResult) (0 = OK)"
    } else:
        Write-Warn "NO INSTALADA"
        Write-Info "  Ejecuta 4-INSTALAR-INICIO-AUTOMATICO.cmd como Administrador"
    }

    # ── Impresora ──
    Write-Host ""
    Write-Info "Impresora ('$Script:PrinterName'):"
    $printer = Get-Printer -Name $Script:PrinterName -ErrorAction SilentlyContinue
    if ($printer) {
        Write-Ok "ENCONTRADA — Estado: $($printer.PrinterStatus) (3 = Lista)"
        Write-Info "  Driver: $($printer.DriverName)"
        Write-Info "  Puerto: $($printer.PortName)"
    } else:
        Write-Error "NO ENCONTRADA"
        Write-Info "  Ejecuta 1-VER-IMPRESORAS.cmd para ver el nombre exacto"
    }

    # ── Conexion VPS ──
    Write-Host ""
    Write-Info "Conexion al servidor ($Script:VpsUrl):"
    $response = Invoke-Api -Method GET -Path "status"
    if ($response -and $response.success) {
        Write-Ok "CONECTADO"
        Write-Info "  Colas pendientes: $($response.pending)"
        Write-Info "  Procesando: $($response.processing)"
    } else {
        Write-Error "SIN CONEXION"
        Write-Info "  Verifica que la PC tenga internet"
        Write-Info "  URL: $Script:VpsUrl"
    }

    # ── Log ──
    Write-Host ""
    Write-Info "Ultimas lineas del log ($Script:LogPath):"
    if (Test-Path $Script:LogPath) {
        Get-Content $Script:LogPath -Tail 5 -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "    $_" -ForegroundColor Gray
        }
    } else {
        Write-Info "  (sin actividad todavia)"
    }

    Write-Host ""
    if ($task -and $printer -and $response -and $response.success) {
        Write-Ok "TODO EN ORDEN — El sistema esta listo para imprimir"
    } else {
        Write-Warn "Falta alguna de las partes. Segui las indicaciones de arriba."
    }

    Write-Host ""
    Write-Host "  Presiona cualquier tecla para salir..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

# ============================================================
# MAIN — Router de modos
# ============================================================

# Crear directorio temporal si no existe
if (-not (Test-Path $Script:TempDir)) { New-Item -ItemType Directory -Path $Script:TempDir -Force | Out-Null }

# Fix: asegurar que PrinterName de los parametros se use en el script
$Script:PrinterName = $PrinterName

switch ($true) {
    $ListPrinters  { Do-ListPrinters; break }
    $TestPrint     { Do-TestPrint; break }
    $Loop          { Do-Loop; break }
    $Install       { Do-Install; break }
    $Status        { Do-Status; break }
    default {
        Write-Host "Uso: ejecuta uno de los archivos .cmd de la carpeta scripts/"
        Write-Host " 1-VER-IMPRESORAS.cmd"
        Write-Host " 2-TEST-IMPRESION.cmd"
        Write-Host " 3-INICIAR-AGENTE.cmd"
        Write-Host " 4-INSTALAR-INICIO-AUTOMATICO.cmd"
        Write-Host " 5-VER-ESTADO.cmd"
        pause
    }
}
