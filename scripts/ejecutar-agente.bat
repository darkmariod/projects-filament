@echo off
title AGENTE ZEBRA - Sistema Garantias
echo ============================================
echo  Iniciando Agente de Impresion Zebra
echo  Sistema de Garantias - Paraiso
echo ============================================
echo.

powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0print-agent.ps1"

echo.
echo El agente se cerro.
pause
