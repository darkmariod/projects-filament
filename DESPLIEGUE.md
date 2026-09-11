# Despliegue en un servidor nuevo

Guía para levantar el Sistema de Garantías en un servidor del cliente desde
cero. Probado sobre Ubuntu Server 22.04 LTS.

---

## 1. Qué necesita el servidor

Con más de mil etiquetas diarias y un lote de mil que pesa 11,7 MB en ZPL:

| Recurso | Mínimo | Recomendado |
|---|---|---|
| CPU | 2 núcleos | 4 núcleos |
| Memoria | 4 GB | 8 GB |
| Disco | 40 GB SSD | 100 GB SSD |
| Sistema | Ubuntu Server 22.04 LTS | Ubuntu Server 24.04 LTS |
| Red | IP pública fija | IP pública fija + dominio |

El disco importa más de lo que parece: las imágenes de Docker y su caché de
construcción crecen con cada despliegue. El servidor actual llegó a tener 32 GB
solo de caché.

Puertos que deben estar abiertos hacia afuera:

| Puerto | Para qué |
|---|---|
| 22 | Administración por SSH |
| 80 | Panel de administración y consultas públicas del QR |
| 8081 | API del agente de impresión (la computadora de planta se conecta acá) |

---

## 2. Preparar el servidor

Como `root` o con `sudo`:

```bash
apt update && apt upgrade -y
apt install -y ca-certificates curl git ufw

# Docker
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker

# Cortafuegos
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 8081/tcp
ufw --force enable
```

Verificar:

```bash
docker --version && docker compose version
```

---

## 3. Traer el código

```bash
mkdir -p /opt && cd /opt
git clone git@github.com:darkmariod/projects-filament.git sistema-garantias
cd sistema-garantias
```

Si el servidor no tiene llave SSH registrada en GitHub, usar HTTPS:
`git clone https://github.com/darkmariod/projects-filament.git sistema-garantias`.

**Siempre desde el repositorio, nunca copiando archivos a mano.** El servidor
anterior llegó a tener nueve archivos que no existían en ningún commit y se
perdían con cada despliegue.

---

## 4. Configurar

Crear el archivo `.env` en la raíz del proyecto con este contenido, completando
los valores entre `<...>`:

```ini
APP_NAME="Productos Paraíso"
APP_ENV=production
APP_DEBUG=false
APP_KEY=

# Dirección por la que se abre el panel. SIN puerto si va detrás de un proxy
# en el 80; CON puerto si se accede directo. Si no coincide con la dirección
# real, el login falla en silencio.
APP_URL=http://<ip-o-dominio>

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
TZ=America/Guayaquil

DB_CONNECTION=mysql
DB_PORT=3306
DB_DATABASE=sistema_garantias
DB_PASSWORD=<clave-larga-y-unica>
DB_ROOT_PASSWORD=<otra-clave-larga-y-unica>

# La misma que "agent_key" en el config.json del agente de impresión.
PRINT_AGENT_KEY=<clave-larga-y-unica>

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
MAIL_FROM_NAME="${APP_NAME}"
```

Para generar claves largas y únicas:

```bash
openssl rand -base64 32
```

`DB_HOST` y `DB_USERNAME` no van en el `.env`: los fija `docker-compose.yml`.

---

## 5. Levantar

```bash
cd /opt/sistema-garantias
docker compose up -d --build
```

La primera vez tarda varios minutos: construye la imagen e instala las
dependencias de PHP. Cuando termina:

```bash
# Clave de cifrado de la aplicación (una sola vez)
docker compose exec app php artisan key:generate --force

# Estructura de la base de datos
docker compose exec app php artisan migrate --force

# Roles, permisos y usuario administrador. SOLO este seeder: el genérico
# (db:seed a secas) carga además datos de demostración.
docker compose exec app php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

El seeder crea `admin@paraiso.com` con la clave `password123`. **Cambiarla en el
primer ingreso**, desde el menú de usuario del panel.

Verificar que los tres contenedores estén sanos:

```bash
docker compose ps
```

Los tres deben decir `healthy` o `running`: `garantias-app`, `garantias-queue`,
`garantias-mysql`.

---

## 6. Comprobar

| Qué | Cómo | Esperado |
|---|---|---|
| Panel | Abrir `http://<ip>/admin/login` en el navegador | Formulario de ingreso, en español |
| API del agente | `curl -H "X-Agent-Key: <clave>" http://<ip>:8081/api/agent/status` | `{"success":true,...}` |
| Ingreso | `admin@paraiso.com` / `password123` | Entra al tablero; cambiar la clave ahí mismo |

Si el panel carga pero el ingreso no funciona, casi seguro `APP_URL` no coincide
con la dirección real por la que se está entrando. Corregirlo en `.env` y
reiniciar: `docker compose restart app`.

---

## 7. El agente de impresión

Va en la computadora de planta que tiene conectada la Zebra. Todo está en
`scripts/agente-zebra-python/`:

1. Editar `config.json`: poner la IP del servidor nuevo en `vps_url` (con el
   `:8081`) y la misma clave que `PRINT_AGENT_KEY` en `agent_key`.
2. Comprimir la carpeta y seguir `GUIA-INSTALACION.pdf` en la computadora de
   planta.

La guía fija `C:\agente-zebra` como ubicación obligatoria: la tarea corre con la
cuenta de sistema de Windows, que puede perder acceso a las carpetas del perfil
de usuario tras reiniciar.

---

## 8. Actualizar más adelante

```bash
cd /opt/sistema-garantias
git pull
docker compose up -d --build app queue-worker
docker compose exec app php artisan migrate --force
```

El código va horneado en la imagen —solo `storage` es volumen—, así que **hay
que reconstruir**; copiar archivos no alcanza. Si se tocaron imágenes o logos,
limpiar además la caché de gráficos:

```bash
docker compose exec app sh -c "rm -rf storage/app/zpl-cache/*"
```

---

## 9. Respaldos

Antes de cualquier cambio en la base:

```bash
mkdir -p /root/backups-garantias
docker compose exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' \
  > /root/backups-garantias/$(date +%Y%m%d-%H%M).sql
```

Conviene dejarlo en un `cron` diario. Los archivos subidos (logos, imágenes) viven
en el volumen `garantias-storage`; respaldarlo con
`docker run --rm -v garantias-storage:/data -v /root/backups-garantias:/out alpine tar czf /out/storage-$(date +%Y%m%d).tar.gz -C /data .`.
