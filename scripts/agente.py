"""
Agente de impresion Zebra — Paraiso
Consulta el sistema cada 10 segundos y manda las etiquetas a la impresora.
"""

import socket
import time
import sys
import requests
import os

# ── CONFIGURACION ─────────────────────────────────────────────────────────────
VPS_URL      = "http://108.174.152.179:8081"
POLL_SECONDS = 10
TIMEOUT      = 10  # segundos para conexion a la impresora

# Impresion por RED (TCP/IP): poner la IP de la Zebra
PRINTER_IP   = ""        # Ej: "192.168.1.200"  — dejar vacio para usar USB
PRINTER_PORT = 9100

# Impresion por USB (Windows): nombre exacto de la impresora en Windows
PRINTER_NAME = "ZDesigner ZT411-203dpi ZPL"  # nombre de la impresora Zebra en Windows
AGENT_KEY    = "zebra-agent-key-2026"         # debe coincidir con PRINT_AGENT_KEY en el .env del VPS
# ─────────────────────────────────────────────────────────────────────────────


def log(msg: str, level: str = "INFO"):
    ts = time.strftime("%H:%M:%S")
    print(f"[{ts}] [{level}] {msg}", flush=True)


def send_zpl_network(zpl: str, ip: str, port: int) -> bool:
    """Manda ZPL directo a la Zebra por TCP (puerto 9100)."""
    try:
        with socket.create_connection((ip, port), timeout=TIMEOUT) as s:
            s.sendall(zpl.encode("utf-8"))
        return True
    except Exception as e:
        log(f"Error TCP {ip}:{port} — {e}", "ERROR")
        return False


def send_zpl_usb(zpl: str, printer_name: str) -> bool:
    """Manda ZPL a impresora USB en Windows via win32print."""
    try:
        import win32print
        handle = win32print.OpenPrinter(printer_name)
        try:
            job = win32print.StartDocPrinter(handle, 1, ("ZPL", None, "RAW"))
            win32print.StartPagePrinter(handle)
            win32print.WritePrinter(handle, zpl.encode("utf-8"))
            win32print.EndPagePrinter(handle)
            win32print.EndDocPrinter(handle)
        finally:
            win32print.ClosePrinter(handle)
        return True
    except ImportError:
        log("win32print no instalado. Instala: pip install pywin32", "ERROR")
        return False
    except Exception as e:
        log(f"Error USB {printer_name} — {e}", "ERROR")
        return False


def send_zpl(zpl: str) -> bool:
    if PRINTER_IP:
        return send_zpl_network(zpl, PRINTER_IP, PRINTER_PORT)
    else:
        return send_zpl_usb(zpl, PRINTER_NAME)


HEADERS = {"X-Agent-Key": AGENT_KEY}


def api_get(path: str):
    try:
        r = requests.get(f"{VPS_URL}/api/agent/{path}", headers=HEADERS, timeout=TIMEOUT)
        r.raise_for_status()
        return r.json()
    except Exception as e:
        log(f"GET {path} fallido — {e}", "WARN")
        return None


def api_post(path: str):
    try:
        r = requests.post(f"{VPS_URL}/api/agent/{path}", headers=HEADERS, timeout=TIMEOUT)
        r.raise_for_status()
        return r.json()
    except Exception as e:
        log(f"POST {path} fallido — {e}", "WARN")
        return None


def check_server() -> bool:
    data = api_get("status")
    return data is not None and data.get("success") is True


def process_queues():
    data = api_get("pending")
    if not data or not data.get("success"):
        return

    queues = data.get("queues", [])
    if not queues:
        log("Sin colas pendientes")
        return

    for queue in queues:
        queue_id    = queue["queue_id"]
        printer     = queue.get("printer_name", PRINTER_NAME)
        items       = queue.get("items", [])
        total       = queue.get("total_items", len(items))

        log(f"Cola #{queue_id} — {total} etiqueta(s) para '{printer}'")

        for item in items:
            item_id = item["item_id"]
            zpl     = item["zpl_content"]
            seq     = item.get("sequence", "?")

            log(f"  Imprimiendo item #{seq} ...")
            ok = send_zpl(zpl)

            if ok:
                api_post(f"{queue_id}/item/{item_id}/complete")
                log(f"  [OK] Item #{seq} impreso y reportado")
            else:
                api_post(f"{queue_id}/item/{item_id}/failed")
                log(f"  [FALLO] Item #{seq} fallido y reportado", "WARN")

        api_post(f"{queue_id}/complete")
        log(f"Cola #{queue_id} finalizada")


def main():
    log("=" * 50)
    log("  Agente de impresion Zebra — Paraiso")
    log("=" * 50)

    if PRINTER_IP:
        log(f"Modo: RED — {PRINTER_IP}:{PRINTER_PORT}")
    else:
        log(f"Modo: USB — {PRINTER_NAME}")

    log(f"Servidor: {VPS_URL}")
    log("Verificando conexion al servidor...")

    # Reintentar hasta que el servidor responda (no cerrar nunca)
    while not check_server():
        log("Sin conexion al servidor. Reintentando en 30 segundos...", "WARN")
        time.sleep(30)

    log("[OK] Servidor conectado. Agente activo — revisando cada 10 segundos.")
    log("Presiona Ctrl+C para detener.")
    log("-" * 50)

    try:
        while True:
            try:
                process_queues()
            except Exception as e:
                log(f"Error inesperado: {e} — el agente sigue activo", "ERROR")
            time.sleep(POLL_SECONDS)
    except KeyboardInterrupt:
        log("Agente detenido por el usuario.")


if __name__ == "__main__":
    main()
