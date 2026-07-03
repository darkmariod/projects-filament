"""
Supervisor del agente de impresion Zebra — Paraiso.

Ejecuta agente.py, lo reinicia automaticamente si se detiene y guarda
todo el registro en agente.log (junto a este archivo).

La extension .pyw hace que corra SIN ventana: ideal para dejarlo en el
inicio automatico de Windows. Para ver la actividad, abrir agente.log
con el Bloc de Notas.
"""

import os
import subprocess
import sys
import time

BASE_DIR      = os.path.dirname(os.path.abspath(__file__))
AGENT_PATH    = os.path.join(BASE_DIR, "agente.py")
LOG_PATH      = os.path.join(BASE_DIR, "agente.log")
RESTART_DELAY = 10                  # segundos entre reinicios
MAX_LOG_BYTES = 5 * 1024 * 1024     # rotar el log al superar 5 MB


def rotate_log():
    """Evita que el log crezca sin limite: conserva una copia .1."""
    try:
        if os.path.exists(LOG_PATH) and os.path.getsize(LOG_PATH) > MAX_LOG_BYTES:
            backup = LOG_PATH + ".1"
            if os.path.exists(backup):
                os.remove(backup)
            os.replace(LOG_PATH, backup)
    except OSError:
        pass


def stamp() -> str:
    return time.strftime("%d/%m/%Y %H:%M:%S")


def main():
    while True:
        rotate_log()
        with open(LOG_PATH, "a", encoding="utf-8") as log:
            log.write(f"\n=== [{stamp()}] Supervisor: iniciando agente ===\n")
            log.flush()
            try:
                proc = subprocess.Popen(
                    [sys.executable, "-u", AGENT_PATH],
                    stdout=log,
                    stderr=subprocess.STDOUT,
                    cwd=BASE_DIR,
                )
                proc.wait()
                log.write(
                    f"=== [{stamp()}] Supervisor: el agente termino "
                    f"(codigo {proc.returncode}); reinicio en {RESTART_DELAY}s ===\n"
                )
            except Exception as e:
                log.write(
                    f"=== [{stamp()}] Supervisor: error al iniciar ({e}); "
                    f"reinicio en {RESTART_DELAY}s ===\n"
                )
        time.sleep(RESTART_DELAY)


if __name__ == "__main__":
    main()
