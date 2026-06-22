@echo off
echo.
echo ================================================================
echo  PASO 1: Ejecuta primero 1-VER-IMPRESORAS.cmd
echo  PASO 2: Cambia PRINTER_NAME abajo con el nombre EXACTO
echo ================================================================
echo.

REM *** CAMBIAR ESTE NOMBRE al que mostro 1-VER-IMPRESORAS.cmd ***
set PRINTER_NAME=ZDesigner ZT411-203dpi ZPL

echo Enviando etiqueta de prueba a: %PRINTER_NAME%
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zebra-raw-agent.ps1" -TestPrint -PrinterName "%PRINTER_NAME%"
pause
