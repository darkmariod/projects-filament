#!/usr/bin/env bash
# =============================================================================
# Instalador del Sistema de Garantías — Productos Paraíso
#
# Deja el sistema funcionando en un servidor Ubuntu limpio: instala Docker,
# trae el código, genera las claves, levanta los contenedores, crea la base
# y el usuario administrador, y verifica que responda.
#
# Uso, como root en el servidor:
#     curl -fsSL https://raw.githubusercontent.com/darkmariod/projects-filament/master/instalar.sh -o instalar.sh
#     bash instalar.sh
#
# Se puede volver a correr: no rompe lo que ya está.
# =============================================================================

set -euo pipefail

REPO_SSH="git@github.com:darkmariod/projects-filament.git"
REPO_HTTPS="https://github.com/darkmariod/projects-filament.git"
DESTINO="/opt/sistema-garantias"
RESPALDOS="/root/backups-garantias"

rojo()   { printf '\033[1;31m%s\033[0m\n' "$*"; }
verde()  { printf '\033[1;32m%s\033[0m\n' "$*"; }
titulo() { printf '\n\033[1m== %s ==\033[0m\n' "$*"; }

[[ $EUID -eq 0 ]] || { rojo "Este instalador tiene que correr como root."; exit 1; }

# -----------------------------------------------------------------------------
# 1. Preguntas
# -----------------------------------------------------------------------------
titulo "Configuración"

echo "Dirección por la que se va a abrir el sistema."
echo "Puede ser una IP (203.0.113.10) o un dominio (garantias.paraiso.com.ec)."
read -rp "Dirección: " DIRECCION
DIRECCION="${DIRECCION#http://}"; DIRECCION="${DIRECCION#https://}"; DIRECCION="${DIRECCION%/}"
[[ -n "$DIRECCION" ]] || { rojo "Hace falta la dirección."; exit 1; }

echo
echo "Puerto por el que responde el sistema. Si no hay otro servicio usando el"
echo "80 en este servidor, dejar 80: así panel y agente van por la misma puerta."
read -rp "Puerto [80]: " APP_PORT
APP_PORT="${APP_PORT:-80}"

if [[ "$APP_PORT" == "80" ]]; then
    APP_URL="http://${DIRECCION}"
else
    APP_URL="http://${DIRECCION}:${APP_PORT}"
fi

echo
echo "Zona horaria [America/Guayaquil]:"
read -rp "> " TZ_APP
TZ_APP="${TZ_APP:-America/Guayaquil}"

# Claves: se generan solas. No hay motivo para inventarlas a mano.
DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-32)"
DB_ROOT_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-32)"
PRINT_AGENT_KEY="paraiso-$(openssl rand -hex 16)"

# Si ya existe un .env, se respetan sus claves: reinstalar no debe cambiar la
# clave de la base ni la del agente que ya está en la planta.
if [[ -f "$DESTINO/.env" ]]; then
    echo
    echo "Ya hay una instalación en $DESTINO. Se conservan sus claves."
    _leer() { grep -E "^$1=" "$DESTINO/.env" | head -1 | cut -d= -f2- | tr -d '"'; }
    DB_PASSWORD="$(_leer DB_PASSWORD)";             DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -hex 16)}"
    DB_ROOT_PASSWORD="$(_leer DB_ROOT_PASSWORD)";   DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-$(openssl rand -hex 16)}"
    PRINT_AGENT_KEY="$(_leer PRINT_AGENT_KEY)";     PRINT_AGENT_KEY="${PRINT_AGENT_KEY:-paraiso-$(openssl rand -hex 16)}"
    APP_KEY_PREVIA="$(_leer APP_KEY)"
else
    APP_KEY_PREVIA=""
fi

echo
echo "Resumen:"
echo "  Sistema:   $APP_URL"
echo "  Carpeta:   $DESTINO"
echo "  Zona:      $TZ_APP"
echo
read -rp "¿Continuar? [s/N]: " OK
[[ "${OK,,}" == "s" ]] || { echo "Cancelado."; exit 0; }

# -----------------------------------------------------------------------------
# 2. Sistema base
# -----------------------------------------------------------------------------
titulo "Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl git ufw openssl >/dev/null
verde "Listo."

titulo "Docker"
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1
docker compose version >/dev/null 2>&1 || { rojo "Docker Compose no quedó disponible."; exit 1; }
verde "$(docker --version)"

titulo "Cortafuegos"
ufw allow 22/tcp    >/dev/null
ufw allow "${APP_PORT}/tcp" >/dev/null
ufw --force enable  >/dev/null
verde "Abiertos: 22 y ${APP_PORT}."

# -----------------------------------------------------------------------------
# 3. Código
# -----------------------------------------------------------------------------
titulo "Código"
if [[ -d "$DESTINO/.git" ]]; then
    git -C "$DESTINO" pull --ff-only
    verde "Actualizado desde el repositorio."
else
    mkdir -p "$(dirname "$DESTINO")"
    # SSH si el servidor tiene llave en GitHub; si no, HTTPS.
    if git clone -q "$REPO_SSH" "$DESTINO" 2>/dev/null; then
        verde "Clonado por SSH."
    else
        git clone -q "$REPO_HTTPS" "$DESTINO"
        verde "Clonado por HTTPS."
    fi
fi

# -----------------------------------------------------------------------------
# 4. Configuración
# -----------------------------------------------------------------------------
titulo "Archivo .env"
cat > "$DESTINO/.env" <<EOF
APP_NAME="Productos Paraíso"
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY_PREVIA}
APP_URL=${APP_URL}
APP_PORT=${APP_PORT}
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
TZ=${TZ_APP}

DB_CONNECTION=mysql
DB_PORT=3306
DB_DATABASE=sistema_garantias
DB_PASSWORD=${DB_PASSWORD}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}

PRINT_AGENT_KEY=${PRINT_AGENT_KEY}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_COOKIE=garantias_session
CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
LOG_CHANNEL=stack
LOG_LEVEL=warning

MAIL_MAILER=log
MAIL_FROM_ADDRESS=garantias@paraiso.com.ec
MAIL_FROM_NAME="\${APP_NAME}"
EOF
chmod 600 "$DESTINO/.env"
verde "Escrito con permisos solo para root."

# -----------------------------------------------------------------------------
# 5. Levantar
# -----------------------------------------------------------------------------
titulo "Contenedores"
cd "$DESTINO"
docker compose up -d --build
echo "Esperando a la base de datos..."
for _ in $(seq 1 30); do
    if docker compose exec -T mysql mysqladmin ping -uroot -p"$DB_ROOT_PASSWORD" --silent >/dev/null 2>&1; then break; fi
    sleep 2
done

if [[ -z "$APP_KEY_PREVIA" ]]; then
    docker compose exec -T app php artisan key:generate --force >/dev/null
    verde "Clave de cifrado generada."
fi

docker compose exec -T app php artisan migrate --force
# SOLO el seeder de roles: el genérico (db:seed a secas) carga datos de demostración.
docker compose exec -T app php artisan db:seed --class=RolesAndPermissionsSeeder --force
docker compose exec -T app php artisan storage:link >/dev/null 2>&1 || true
docker compose exec -T app php artisan optimize >/dev/null

mkdir -p "$RESPALDOS"

# -----------------------------------------------------------------------------
# 6. Comprobar
# -----------------------------------------------------------------------------
titulo "Comprobación"
sleep 3
PANEL=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1:${APP_PORT}/admin/login" || echo 000)
API=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 -H "X-Agent-Key: ${PRINT_AGENT_KEY}" "http://127.0.0.1:${APP_PORT}/api/agent/status" || echo 000)

[[ "$PANEL" == "200" ]] && verde "Panel:           responde (200)" || rojo "Panel:           HTTP $PANEL"
[[ "$API"   == "200" ]] && verde "API del agente:  responde (200)" || rojo "API del agente:  HTTP $API"

# -----------------------------------------------------------------------------
# 7. Lo que hay que guardar
# -----------------------------------------------------------------------------
titulo "INSTALACIÓN TERMINADA"
cat <<EOF

  Panel:     ${APP_URL}/admin/login
  Usuario:   admin@paraiso.com
  Clave:     password123      <-- CAMBIARLA en el primer ingreso

  Las claves de la base y del agente quedaron en ${DESTINO}/.env

-------------------------------------------------------------------------------
  AGENTE DE IMPRESIÓN — config.json para la computadora de la planta
-------------------------------------------------------------------------------
  Reemplazar el contenido de C:\\agente-zebra\\config.json por esto:

{
  "vps_url": "${APP_URL}",
  "poll_seconds": 10,
  "timeout": 10,
  "printer_ip": "",
  "printer_port": 9100,
  "printer_name": "ZDesigner ZT411-203dpi ZPL",
  "agent_key": "${PRINT_AGENT_KEY}",
  "batch_size": 50
}

  Después, en esa computadora:  python setup.py estado
-------------------------------------------------------------------------------

  Para actualizar más adelante:   bash ${DESTINO}/instalar.sh
  Respaldos de la base en:        ${RESPALDOS}

EOF
