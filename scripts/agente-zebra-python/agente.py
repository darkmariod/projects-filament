"""
Agente de impresion Zebra - Paraiso
Consulta el sistema cada 10 segundos y manda las etiquetas a la impresora.

Motor de impresion (send_zpl_usb / send_zpl_network) IDENTICO al que ya se
probo con exito en la impresora real - no se toco esa parte.
Se agrego: configuracion en config.json (no hay que editar este archivo a
mano), la clave correcta que usa el servidor hoy, y un seguro para que nunca
queden dos copias del agente corriendo al mismo tiempo.
"""

import json
import os
import socket
import sys
import time

import requests

# ── CONFIGURACION - se lee de config.json en la misma carpeta ─────────────
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.path.join(SCRIPT_DIR, "config.json")

DEFAULTS = {
    "vps_url": "http://108.174.152.179:8081",
    "poll_seconds": 10,
    "timeout": 10,
    "printer_ip": "",
    "printer_port": 9100,
    "printer_name": "ZDesigner ZT411-203dpi ZPL",
    "agent_key": "zebra-agent-key-2026",
    # Cuantas etiquetas pide y reporta por vez. Un lote de mil son 11,7 MB
    # si se piden todas juntas; de a 50 son tandas de ~600 KB. Y reportar
    # 50 en una peticion en vez de 50 peticiones baja los veinte minutos de
    # avisos a menos de uno.
    "batch_size": 50,
}


def load_config() -> dict:
    cfg = dict(DEFAULTS)
    if os.path.isfile(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                loaded = json.load(f)
            for key in DEFAULTS:
                if key in loaded and loaded[key] not in (None, ""):
                    cfg[key] = loaded[key]
        except Exception as e:
            print(f"[!] config.json invalido ({e}), usando valores por defecto")
    return cfg


CFG          = load_config()
VPS_URL      = CFG["vps_url"]
POLL_SECONDS = CFG["poll_seconds"]
TIMEOUT      = CFG["timeout"]
PRINTER_IP   = CFG["printer_ip"]
PRINTER_PORT = CFG["printer_port"]
PRINTER_NAME = CFG["printer_name"]
AGENT_KEY    = CFG["agent_key"]
BATCH_SIZE   = int(CFG["batch_size"])
HEADERS      = {"X-Agent-Key": AGENT_KEY, "Accept": "application/json"}


# El agente corre sin consola (pythonw.exe desde el Programador de tareas),
# asi que todo queda escrito en agente.log, al lado de este archivo. Sin esto
# no hay forma de saber que paso cuando algo falla de madrugada.
LOG_PATH = os.path.join(SCRIPT_DIR, "agente.log")
LOG_MAX_BYTES = 5_000_000


def log(msg: str, level: str = "INFO"):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    linea = f"[{ts}] [{level}] {msg}"
    print(linea, flush=True)
    try:
        # Con ~1000 etiquetas diarias el archivo crece rapido: al pasar los
        # 5 MB se guarda como agente.log.old y se empieza uno nuevo.
        if os.path.exists(LOG_PATH) and os.path.getsize(LOG_PATH) > LOG_MAX_BYTES:
            os.replace(LOG_PATH, LOG_PATH + ".old")
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(linea + "\n")
    except Exception:
        # Un fallo al escribir el log jamas debe detener la impresion.
        pass


# ── SEGURO DE INSTANCIA UNICA ───────────────────────────────────────────────
# Se reserva un puerto local fijo (no se usa para nada mas que esto). Si otro
# agente ya lo tiene tomado, esta copia se cierra sola de inmediato. El
# sistema operativo libera el puerto solo si el proceso muere sin avisar.
_LOCK_PORT = 47821
_lock_socket = None


def acquire_single_instance_lock() -> bool:
    global _lock_socket
    _lock_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        _lock_socket.bind(("127.0.0.1", _LOCK_PORT))
        _lock_socket.listen(1)
        return True
    except OSError:
        return False


# ── IMPRESION (sin cambios respecto a la version que ya funciono) ─────────
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


# ── API ──────────────────────────────────────────────────────────────────
def api_get(path: str):
    try:
        r = requests.get(f"{VPS_URL}/api/agent/{path}", headers=HEADERS, timeout=TIMEOUT)
        if r.status_code == 401:
            log("401 No autorizado - la clave del agente (config.json) no coincide con el servidor", "ERROR")
            return None
        r.raise_for_status()
        return r.json()
    except Exception as e:
        log(f"GET {path} fallido - {e}", "WARN")
        return None


def api_post(path: str, body=None):
    try:
        r = requests.post(f"{VPS_URL}/api/agent/{path}", headers=HEADERS, json=body, timeout=TIMEOUT)
        r.raise_for_status()
        return r.json()
    except Exception as e:
        log(f"POST {path} fallido - {e}", "WARN")
        return None


def check_server() -> bool:
    data = api_get("status")
    return data is not None and data.get("success") is True


def reportar_impresas(queue_id: int, item_ids: list) -> None:
    """Avisa al servidor que estas etiquetas ya salieron, en una sola peticion.

    Si el servidor no conoce el endpoint en bloque (una version anterior), se
    cae al aviso de una por una para no dejar nada sin reportar.
    """
    if not item_ids:
        return
    r = api_post(f"{queue_id}/items/complete", {"item_ids": item_ids})
    if r is not None and r.get("success"):
        log(f"  [OK] {len(item_ids)} etiqueta(s) reportadas en bloque")
        return
    log("  Reporte en bloque no disponible, avisando una por una", "WARN")
    for item_id in item_ids:
        api_post(f"{queue_id}/item/{item_id}/complete")


def process_queues():
    # Mientras queden etiquetas se sigue pidiendo sin esperar los 10 segundos
    # del ciclo. Se corta si el servidor deja de responder o ya no hay nada.
    while _procesar_tanda():
        pass


def _procesar_tanda() -> bool:
    """Pide una tanda, la imprime y la reporta. Devuelve True si quedan mas."""
    # Se pide de a tandas. Si el servidor no entiende el parametro lo ignora y
    # devuelve todo, como siempre: funciona con cualquiera de las dos versiones.
    data = api_get(f"pending?limit={BATCH_SIZE}")
    if not data or not data.get("success"):
        return False

    queues = data.get("queues", [])
    if not queues:
        return False

    hay_mas = False

    for queue in queues:
        queue_id  = queue["queue_id"]
        printer   = queue.get("printer_name", PRINTER_NAME)
        items     = queue.get("items", [])
        total     = queue.get("total_items", len(items))
        remaining = queue.get("remaining", total)

        log(f"Cola #{queue_id} - {total} etiqueta(s) para '{printer}'"
            + (f" ({remaining} en total por imprimir)" if remaining > total else ""))

        impresas = []
        for item in items:
            item_id = item["item_id"]
            zpl     = item["zpl_content"]
            seq     = item.get("sequence", "?")

            log(f"  Imprimiendo item #{seq} ...")
            ok = send_zpl(zpl)

            if ok:
                impresas.append(item_id)
                log(f"  [OK] Item #{seq} impreso")
            else:
                api_post(f"{queue_id}/item/{item_id}/failed")
                log(f"  [FALLO] Item #{seq} fallido y reportado", "WARN")

        reportar_impresas(queue_id, impresas)

        # Quedan mas en esta cola: se vuelve a pedir enseguida, sin esperar
        # los 10 segundos del ciclo. El cierre de la cola se manda recien
        # cuando ya no queda nada.
        if remaining > total:
            hay_mas = True
        else:
            api_post(f"{queue_id}/complete")
            log(f"Cola #{queue_id} finalizada")

    return hay_mas


def main():
    log("=" * 50)
    log("  Agente de impresion Zebra - Paraiso")
    log("=" * 50)

    if not acquire_single_instance_lock():
        log("Ya hay un agente corriendo en esta PC. Cerrando esta copia.", "WARN")
        sys.exit(0)

    if PRINTER_IP:
        log(f"Modo: RED - {PRINTER_IP}:{PRINTER_PORT}")
    else:
        log(f"Modo: USB - {PRINTER_NAME}")

    log(f"Servidor: {VPS_URL}")
    log("Verificando conexion al servidor...")

    # Al prender la PC el agente arranca antes que la red este lista. Por eso
    # espera en vez de cerrarse: reintenta subiendo de a poco hasta 60s. Lo
    # mismo sirve si el servidor se cae en mitad del turno.
    intento = 0
    while not check_server():
        intento += 1
        espera = min(60, 5 * intento)
        log(f"Servidor no disponible (intento {intento}). Reintento en {espera}s.", "WARN")
        time.sleep(espera)

    log("[OK] Servidor conectado. Agente activo - revisando cada %s segundos." % POLL_SECONDS)
    log("-" * 50)

    # Deja una senal de vida cada 5 minutos. Sirve para que "python setup.py estado"
    # pueda decir si el agente respira, incluso en un turno sin impresiones.
    ultimo_latido = 0.0

    while True:
        try:
            process_queues()
        except Exception as e:
            log(f"Error en el ciclo principal: {e}", "ERROR")

        if time.time() - ultimo_latido >= 300:
            log("Agente vivo, esperando trabajos.")
            ultimo_latido = time.time()

        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()
