#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/ovf"
cd "${APP_ROOT}"

# 1. Populate public_html/ from compiled dist/ (cPanel Pattern A topology)
mkdir -p "${APP_ROOT}/public_html"
if [ -d "${APP_ROOT}/dist" ]; then
    cp -rf "${APP_ROOT}/dist/." "${APP_ROOT}/public_html/"
fi

# 2. Ensure shared runtime cache directory exists and is writable by Apache (www-data)
mkdir -p "${APP_ROOT}/public_html/cache"
rm -rf "${APP_ROOT}/public/cache"
ln -sfn "${APP_ROOT}/public_html/cache" "${APP_ROOT}/public/cache"
chown -R www-data:www-data "${APP_ROOT}/public_html/cache" 2>/dev/null || true
chmod -R 775 "${APP_ROOT}/public_html/cache" 2>/dev/null || true

# 3. Run database migrations & optional local admin provisioning when DB_HOST is configured
if [ "${SKIP_DB_INIT:-false}" != "true" ] && [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Waiting for MySQL at ${DB_HOST}..."
    MAX_RETRIES=30
    RETRY_COUNT=0
    until php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $user = getenv("DB_USER") ?: "root";
        $pass = getenv("DB_PASS") ?: "";
        try {
            new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ "${RETRY_COUNT}" -ge "${MAX_RETRIES}" ]; then
            echo "[entrypoint] Warning: MySQL at ${DB_HOST} did not become ready in time. Continuing startup..."
            break
        fi
        sleep 2
    done

    if [ "${RETRY_COUNT}" -lt "${MAX_RETRIES}" ]; then
        echo "[entrypoint] Running database migrations..."
        php "${APP_ROOT}/scripts/migrate.php"

        if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
            echo "[entrypoint] Provisioning local admin account (${ADMIN_EMAIL})..."
            php "${APP_ROOT}/scripts/create-admin-user.php" \
                --email="${ADMIN_EMAIL}" \
                --name="${ADMIN_NAME:-Local Administrator}" \
                --password="${ADMIN_PASSWORD}" \
                --role="${ADMIN_ROLE:-superadmin}"
        fi
    fi
fi

exec "$@"
