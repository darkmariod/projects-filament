@echo off
echo.
echo Listando impresoras instaladas en este equipo...
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zebra-raw-agent.ps1" -ListPrinters
pause
