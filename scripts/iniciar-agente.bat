@echo off
title Agente Impresion Zebra - Paraiso
:inicio
echo [%time%] Iniciando agente de impresion...
python "%~dp0agente.py"
echo.
echo [%time%] El agente se detuvo. Reiniciando en 10 segundos...
timeout /t 10 /nobreak > nul
goto inicio
