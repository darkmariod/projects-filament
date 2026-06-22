@echo off
echo.
echo ================================================================
echo  Instalar agente como tarea automatica de Windows
echo  Se iniciara solo al encender la PC - sin que el operador
echo  tenga que hacer nada.
echo ================================================================
echo.

REM *** CAMBIAR ESTE NOMBRE al que mostro 1-VER-IMPRESORAS.cmd ***
set PRINTER_NAME=ZDesigner ZT411-203dpi ZPL

echo Instalando tarea automatica para: %PRINTER_NAME%
echo.

REM Requiere ejecutar como Administrador
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zebra-raw-agent.ps1" -Install -PrinterName "%PRINTER_NAME%"
pause
