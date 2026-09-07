"""
Instalador guiado del agente de impresion Zebra.
No editar nada a mano: este programa hace las preguntas necesarias,
guarda config.json, prueba la impresora, y deja el agente encendido
para siempre (tarea automatica de Windows).
"""

import json
import os
import re
import subprocess
import sys
import time

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.path.join(SCRIPT_DIR, "config.json")
AGENT_PATH = os.path.join(SCRIPT_DIR, "agente.py")
TASK_NAME = "ZebraPrintAgentPython"

ZEBRA_PATTERN = re.compile(r"zebra|zdesigner|zt\d|zd\d", re.IGNORECASE)


def load_config() -> dict:
    if os.path.isfile(CONFIG_PATH):
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    return {}


def save_config(cfg: dict):
    with open(CONFIG_PATH, "w", encoding="utf-8") as f:
        json.dump(cfg, f, indent=2, ensure_ascii=False)


def is_admin() -> bool:
    try:
        import ctypes
        return ctypes.windll.shell32.IsUserAnAdmin() != 0
    except Exception:
        return False


def relanzar_como_admin() -> bool:
    """Le pide el permiso a Windows y vuelve a abrir este mismo programa.

    Crear la tarea automatica exige permisos de administrador. En vez de
    obligar a abrir la consola con el boton derecho, se pide aca.
    """
    try:
        import ctypes
        args = [os.path.abspath(__file__)] + sys.argv[1:]
        params = " ".join(f'"{a}"' for a in args)
        rc = ctypes.windll.shell32.ShellExecuteW(
            None, "runas", sys.executable, params, SCRIPT_DIR, 1
        )
        # ShellExecuteW devuelve <= 32 cuando falla (por ejemplo, si el
        # usuario cancela el cartel de permisos de Windows).
        return int(rc) > 32
    except Exception:
        return False


def list_printers() -> list:
    """Devuelve los nombres de todas las impresoras instaladas en Windows."""
    import win32print
    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
    return [p[2] for p in win32print.EnumPrinters(flags)]


def detect_zebra_candidates(printers: list) -> list:
    return [p for p in printers if ZEBRA_PATTERN.search(p)]


def elegir_impresora() -> str:
    print("\n>>> PASO 1 de 3 - Buscando la impresora Zebra ...")
    try:
        todas = list_printers()
    except ImportError:
        print("[ERROR] Falta pywin32. Instalando dependencias...")
        instalar_dependencias()
        todas = list_printers()

    candidatas = detect_zebra_candidates(todas)

    if len(candidatas) == 1:
        print(f"[OK] Encontrada automaticamente: {candidatas[0]}")
        return candidatas[0]

    if len(candidatas) > 1:
        print("Se encontro mas de una impresora Zebra:")
        for i, nombre in enumerate(candidatas, 1):
            print(f"    [{i}] {nombre}")
        sel = input("Escribe el numero de la impresora correcta: ").strip()
        if sel.isdigit() and 1 <= int(sel) <= len(candidatas):
            return candidatas[int(sel) - 1]

    print("[!] No se detecto ninguna impresora con nombre tipo Zebra/ZDesigner.")
    print("Impresoras instaladas en esta PC:")
    for i, nombre in enumerate(todas, 1):
        print(f"    [{i}] {nombre}")
    sel = input("Escribe el numero de tu impresora Zebra (Enter para cancelar): ").strip()
    if sel.isdigit() and 1 <= int(sel) <= len(todas):
        return todas[int(sel) - 1]

    return ""


def instalar_dependencias():
    subprocess.run([sys.executable, "-m", "pip", "install", "--quiet", "requests", "pywin32"], check=False)


def probar_impresion(printer_name: str) -> bool:
    print("\n>>> PASO 2 de 3 - Prueba de impresion")
    import win32print
    zpl = ("^XA^FO50,50^A0N,40,40^FDPRUEBA AGENTE ZEBRA^FS"
           f"^FO50,110^A0N,30,30^FD{time.strftime('%d/%m/%Y %H:%M:%S')}^FS^XZ")
    try:
        handle = win32print.OpenPrinter(printer_name)
        try:
            win32print.StartDocPrinter(handle, 1, ("ZPL", None, "RAW"))
            win32print.StartPagePrinter(handle)
            win32print.WritePrinter(handle, zpl.encode("utf-8"))
            win32print.EndPagePrinter(handle)
            win32print.EndDocPrinter(handle)
        finally:
            win32print.ClosePrinter(handle)
        print("[OK] Enviado a la impresora.")
    except Exception as e:
        print(f"[ERROR] No se pudo imprimir: {e}")
        return False

    resp = input("\n  Salio la etiqueta de prueba? (s/n): ").strip().lower()
    return resp.startswith("s")


def instalar_tarea_automatica():
    print("\n>>> PASO 3 de 3 - Dejando el agente encendido para siempre")

    pythonw = sys.executable.replace("python.exe", "pythonw.exe")
    if not os.path.isfile(pythonw):
        pythonw = sys.executable  # fallback: usa python.exe normal

    # Se borra si ya existia, para poder reinstalar sin error
    subprocess.run(["schtasks", "/Delete", "/TN", TASK_NAME, "/F"],
                    capture_output=True)

    cmd_create = [
        "schtasks", "/Create",
        "/TN", TASK_NAME,
        "/TR", f'"{pythonw}" "{AGENT_PATH}"',
        "/SC", "ONSTART",
        "/RU", "SYSTEM",
        "/RL", "HIGHEST",
        "/F",
    ]
    result = subprocess.run(cmd_create, capture_output=True, text=True)

    if result.returncode == 0:
        # Segundo disparador: cada 1 minuto, como respaldo (el seguro de
        # instancia unica en agente.py evita que se dupliquen procesos).
        subprocess.run([
            "schtasks", "/Create",
            "/TN", TASK_NAME + "-Watchdog",
            "/TR", f'"{pythonw}" "{AGENT_PATH}"',
            "/SC", "MINUTE",
            "/MO", "1",
            "/RU", "SYSTEM",
            "/RL", "HIGHEST",
            "/F",
        ], capture_output=True)

        print("[OK] AGENTE INSTALADO. Se enciende solo con la PC.")
        print("El seguro de instancia unica evita copias duplicadas.")
        return True
    else:
        print(f"[ERROR] No se pudo crear la tarea: {result.stderr}")
        return False


def ver_estado():
    """Muestra si el agente esta instalado, si corre, y que dice el registro."""
    print("=" * 55)
    print("  ESTADO DEL AGENTE ZEBRA")
    print("=" * 55)

    print("\n[1] Arranque automatico con la PC")
    r = subprocess.run(["schtasks", "/Query", "/TN", TASK_NAME],
                       capture_output=True)
    if r.returncode == 0:
        print("    OK - la tarea esta instalada.")
    else:
        print("    FALTA - ejecuta:  python setup.py")

    print("\n[2] El agente esta corriendo AHORA?")
    r = subprocess.run(["tasklist", "/FI", "IMAGENAME eq pythonw.exe"],
                       capture_output=True, text=True)
    if "pythonw.exe" in (r.stdout or ""):
        print("    OK - hay un proceso del agente activo.")
    else:
        print("    NO se ve corriendo. El vigilante lo levanta en 1 minuto.")

    print("\n[3] Impresora configurada")
    cfg = load_config()
    print(f"    {cfg.get('printer_name') or '(ninguna configurada)'}")

    print("\n[4] Ultimas 15 lineas del registro")
    print("    " + "-" * 45)
    log_path = os.path.join(SCRIPT_DIR, "agente.log")
    if os.path.isfile(log_path):
        with open(log_path, "r", encoding="utf-8", errors="replace") as f:
            for linea in f.readlines()[-15:]:
                print("    " + linea.rstrip())
    else:
        print("    Todavia no hay registro. El agente no arranco nunca.")
    print("    " + "-" * 45)
    print("\nSi ves 'Agente vivo' con hora reciente, esta todo bien.")


def desinstalar():
    """Saca las dos tareas de Windows. No borra archivos ni configuracion."""
    print("=" * 55)
    print("  DESINSTALAR EL AGENTE ZEBRA")
    print("=" * 55)

    if not is_admin():
        print("\nHace falta permiso de administrador. Pidiendoselo a Windows...")
        if relanzar_como_admin():
            return
        print("[ERROR] No se pudo obtener el permiso.")
        return

    for nombre in (TASK_NAME, TASK_NAME + "-Watchdog"):
        r = subprocess.run(["schtasks", "/Delete", "/TN", nombre, "/F"],
                           capture_output=True)
        estado = "eliminada" if r.returncode == 0 else "no existia"
        print(f"  Tarea {nombre}: {estado}")

    print("\nLISTO. El agente ya no arranca con la PC.")
    print("Los archivos y config.json siguen en la carpeta, por si lo volves")
    print("a instalar mas adelante con:  python setup.py")


def instalar():
    print("=" * 55)
    print("  INSTALACION DEL AGENTE DE IMPRESION ZEBRA")
    print("  Sistema de Garantias - Paraiso")
    print("=" * 55)

    if not is_admin():
        print("\nHace falta permiso de administrador para dejar el agente")
        print("encendido con la PC. Pidiendoselo a Windows...")
        if relanzar_como_admin():
            print("Se abrio una ventana nueva con el permiso. Segui ahi.")
            return
        print("\n[ERROR] No se pudo obtener el permiso de administrador.")
        print("Abri el simbolo del sistema con boton derecho >")
        print("'Ejecutar como administrador', y volve a correr:")
        print("    python setup.py")
        input("\nPresiona Enter para salir...")
        return

    instalar_dependencias()

    impresora = elegir_impresora()
    if not impresora:
        print("\n[ERROR] No se eligio ninguna impresora. Conecta la Zebra e intenta de nuevo.")
        input("\nPresiona Enter para salir...")
        return

    cfg = load_config()
    cfg["printer_name"] = impresora
    save_config(cfg)
    print(f"[OK] Impresora guardada: {impresora}")

    if not probar_impresion(impresora):
        print("\n[!] Revisa el cable/USB de la impresora y volve a ejecutar:  python setup.py")
        input("\nPresiona Enter para salir...")
        return

    if instalar_tarea_automatica():
        print("\nLISTO. Ya podes cerrar esta ventana.")
        print("A partir de ahora, el agente se prende solo con la PC.")

    input("\nPresiona Enter para salir...")

def main():
    """Despacha segun lo que se pida en la linea de comandos.

        python setup.py              -> instala
        python setup.py estado       -> muestra si funciona
        python setup.py desinstalar  -> saca el arranque automatico
    """
    accion = (sys.argv[1].lower() if len(sys.argv) > 1 else "instalar")

    if accion in ("estado", "status"):
        ver_estado()
    elif accion in ("desinstalar", "uninstall"):
        desinstalar()
    elif accion in ("instalar", "install"):
        instalar()
        return  # instalar() ya hace su propia pausa
    else:
        print(f"Opcion no reconocida: {accion}")
        print("Usa:  python setup.py  |  python setup.py estado  |  python setup.py desinstalar")

    input("\nPresiona Enter para salir...")


if __name__ == "__main__":
    main()
