@echo off
echo.
REM *** CAMBIAR ESTE NOMBRE al que mostro 1-VER-IMPRESORAS.cmd ***
set PRINTER_NAME=ZDesigner ZT411-203dpi ZPL

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zebra-raw-agent.ps1" -Status -PrinterName "%PRINTER_NAME%"
pause
