@echo off
echo.
echo ================================================================
echo  Agente Zebra — Loop continuo
echo  Cambia PRINTER_NAME si es necesario (ver 1-VER-IMPRESORAS.cmd)
echo ================================================================
echo.

REM *** CAMBIAR ESTE NOMBRE al que mostro 1-VER-IMPRESORAS.cmd ***
set PRINTER_NAME=ZDesigner ZT411-203dpi ZPL

echo Iniciando agente para: %PRINTER_NAME%
echo Presiona CTRL+C para detener
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zebra-raw-agent.ps1" -Loop -PrinterName "%PRINTER_NAME%"
pause
