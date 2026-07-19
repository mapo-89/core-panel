#!/bin/sh
set -eu

# -----------------------------
# 🔧 Logging function
# -----------------------------
log() {
    level="$1"
    message="$2"
    printf '[%s] %s %s\n' "$(date '+%d-%b-%Y %H:%M:%S')" "$level" "$message"
}

app_version() {
    version_file="${APP_ROOT}/config/app-version.json"

    if [ ! -f "$version_file" ]; then
        return
    fi

    display_version="$(sed -nE 's/^[[:space:]]*"display_version"[[:space:]]*:[[:space:]]*"([^"]+)".*$/\1/p' "$version_file" | head -n 1)"

    if [ -n "$display_version" ]; then
        printf '%s\n' "$display_version"
    fi
}

is_enabled() {
    value="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]')"

    case "$value" in
        1|true|yes|on)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

ensure_public_storage_link() {
    public_storage_path="${APP_ROOT}/public/storage"
    storage_target="${APP_ROOT}/storage/app/public"

    mkdir -p "$storage_target"
    mkdir -p "$(dirname "$public_storage_path")"

    if [ -L "$public_storage_path" ]; then
        ln -sfn "$storage_target" "$public_storage_path"
        log "✅ success " "Updated public/storage symlink"
        return
    fi

    if [ -e "$public_storage_path" ]; then
        log "⚠️ WARNING " "Skipping public/storage symlink because ${public_storage_path} already exists and is not a symlink"
        return
    fi

    ln -s "$storage_target" "$public_storage_path"
    log "✅ success " "Created public/storage symlink"
}

APP_ROOT="${APP_ROOT:-/var/www/html}"
MAX_RETRIES="${MAX_RETRIES:-30}"
SLEEP_SECONDS="${SLEEP_SECONDS:-5}"
ENTRYPOINT_DIR="${APP_ROOT}/.docker/php"
WAIT_FOR_NGINX="${WAIT_FOR_NGINX:-auto}"
APP_RUNTIME_USER="${APP_RUNTIME_USER:-www-data}"
APP_RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"

command_name="${1:-}"

if [ "$command_name" = "docker-php-entrypoint" ] && [ "$#" -gt 1 ]; then
    command_name="$2"
fi

if [ -x "${ENTRYPOINT_DIR}/banner.sh" ]; then
    /bin/sh "${ENTRYPOINT_DIR}/banner.sh"
elif [ -x /usr/local/bin/banner.sh ]; then
    /usr/local/bin/banner.sh
else
    echo "Application startup"
fi

APP_VERSION="$(app_version || true)"

echo
if [ -n "$APP_VERSION" ]; then
    echo "🏷️  Version:   ${APP_VERSION}"
fi
echo "👤 User:      $(id -un)  PUID:$(id -u)"
echo "👥 Group:     $(id -gn)  PGID:$(id -g)"
echo "🐘 PHP:       $(php -v | head -n 1)"
echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo

# -----------------------------
# 📂 Load Laravel .env
# -----------------------------
if [ -f "${APP_ROOT}/.env" ]; then
    log "📥 info    " ".env detected at ${APP_ROOT}/.env"
elif [ -n "${APP_ENV:-}" ] || [ -n "${APP_KEY:-}" ]; then
    log "📥 info    " ".env not found at ${APP_ROOT}/.env; using injected environment variables"
else
    log "⚠️ WARNING " ".env not found at ${APP_ROOT}/.env"
fi

# -----------------------------
# 🌐 Wait for nginx
# -----------------------------
should_wait_for_nginx=0

if [ "$WAIT_FOR_NGINX" = "1" ] || [ "$WAIT_FOR_NGINX" = "true" ] || [ "$WAIT_FOR_NGINX" = "yes" ]; then
    should_wait_for_nginx=1
elif [ "$WAIT_FOR_NGINX" = "auto" ] && [ "$command_name" = "php-fpm" ]; then
    should_wait_for_nginx=1
fi

if [ "$should_wait_for_nginx" -eq 1 ]; then
    NGINX_HOST=${NGINX_HOST:-nginx}
    NGINX_PORT=${NGINX_PORT:-80}
    MAX_RETRIES=${MAX_RETRIES:-10}
    RETRY_COUNT=0
    NGINX_HEALTH_URL=${NGINX_HEALTH_URL:-http://${NGINX_HOST}:${NGINX_PORT}/nginx-health}

    log "🔍 info    " "Checking nginx connection at ${NGINX_HEALTH_URL}..."

    until curl -fsS "${NGINX_HEALTH_URL}" >/dev/null 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT+1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            log "🚫 ERROR   " "nginx unreachable after $MAX_RETRIES attempts!"
            exit 1
        fi
        log "⏱ WARNING  " "Attempt $RETRY_COUNT/$MAX_RETRIES – waiting 5 seconds..."
        sleep "$SLEEP_SECONDS"
    done

    log "✅ success " "nginx is reachable!"
else
    log "ℹ️ info    " "Skipping nginx wait for command: ${command_name:-unknown}"
fi

# -----------------------------
# 🐬 Wait for Database
# -----------------------------
if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    db_host="${DB_HOST:-postgres}"
    db_port="${DB_PORT:-5432}"
    db_user="${DB_USERNAME:-postgres}"
    db_name="${DB_DATABASE:-postgres}"
    attempt=0

    log "🔍 info    " "Waiting for PostgreSQL at ${db_host}:${db_port}/${db_name}"

    export DB_HOST="$db_host"
    export DB_PORT="$db_port"
    export DB_USERNAME="$db_user"
    export DB_DATABASE="$db_name"

    until php -r '
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));

        try {
            new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
            exit(0);
        } catch (Throwable $exception) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$MAX_RETRIES" ]; then
            log "🚫 ERROR   " "PostgreSQL not reachable after ${MAX_RETRIES} attempts"
            exit 1
        fi
        log "⏱ WARNING  " "Attempt $attempt/$MAX_RETRIES – waiting 5 seconds..."
        sleep "$SLEEP_SECONDS"
    done

    log "✅ success " "PostgreSQL is reachable"
elif [ "${DB_CONNECTION:-}" = "mysql" ] && command -v mysql >/dev/null 2>&1; then
    db_host="${DB_HOST:-mysql}"
    db_port="${DB_PORT:-3306}"
    db_user="${DB_USERNAME:-root}"
    db_name="${DB_DATABASE:-}"
    attempt=0

    log "🔍 info    " "Waiting for MySQL at ${db_host}:${db_port}"

    until mysql \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        -p"${DB_PASSWORD:-}" \
        -e "SELECT 1" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$MAX_RETRIES" ]; then
            log "🚫 ERROR   " "MySQL not reachable after ${MAX_RETRIES} attempts"
            exit 1
        fi
        log "⏱ WARNING  " "Attempt $attempt/$MAX_RETRIES – waiting 5 seconds..."
        sleep "$SLEEP_SECONDS"
    done

    log "✅ success " "MySQL is reachable${db_name:+ for ${db_name}}"
else
    log "⚠️ WARNING " "Skipping database wait for DB_CONNECTION=${DB_CONNECTION:-unset}"
fi

ensure_public_storage_link

if is_enabled "${RUN_MIGRATIONS:-}"; then
    if [ "$command_name" = "php-fpm" ]; then
        log "ℹ️ info    " "RUN_MIGRATIONS enabled; running php artisan migrate:recursive --force"
        php artisan migrate:recursive --force
        log "✅ success " "Central recursive migrations completed"
        log "ℹ️ info    " "RUN_MIGRATIONS enabled; running php artisan tenants:migrate --force"
        php artisan tenants:migrate --force
        log "✅ success " "Tenant migrations completed"
    else
        log "ℹ️ info    " "RUN_MIGRATIONS enabled; skipping migrations for command: ${command_name:-unknown}"
    fi
fi

log "📥 info    " "Starting: $*"

if [ "$command_name" != "php-fpm" ] \
    && [ "$(id -u)" -eq 0 ] \
    && [ "${APP_RUNTIME_USER}" != "root" ] \
    && command -v gosu >/dev/null 2>&1; then
    exec gosu "${APP_RUNTIME_USER}:${APP_RUNTIME_GROUP}" "$@"
fi

exec "$@"
