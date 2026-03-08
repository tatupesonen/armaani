#!/bin/bash
set -e

STORAGE_PATH="/var/www/html/storage"

# -------------------------------------------------------
# Ensure storage directory structure
# -------------------------------------------------------
mkdir -p "$STORAGE_PATH"/{app/public,arma/{games,servers,mods,missions},framework/{cache,sessions,views},logs,caddy}

# -------------------------------------------------------
# SQLite database
# -------------------------------------------------------
touch "$STORAGE_PATH/database.sqlite"

# -------------------------------------------------------
# .env — generate on first boot, persist in storage volume
# -------------------------------------------------------
if [ ! -f "$STORAGE_PATH/.env" ]; then
    cp /var/www/html/.env.docker "$STORAGE_PATH/.env"
fi

ln -sf "$STORAGE_PATH/.env" /var/www/html/.env

# -------------------------------------------------------
# Auto-generate APP_KEY if not set
# -------------------------------------------------------
if ! grep -q "^APP_KEY=base64:" "$STORAGE_PATH/.env"; then
    php /var/www/html/artisan key:generate --force
fi

# -------------------------------------------------------
# Run migrations
# -------------------------------------------------------
php /var/www/html/artisan migrate --force

# -------------------------------------------------------
# Create default admin user (skips if users already exist)
# -------------------------------------------------------
php /var/www/html/artisan user:create-admin --no-interaction

# -------------------------------------------------------
# Optimize (config, routes, views)
# -------------------------------------------------------
php /var/www/html/artisan optimize

# -------------------------------------------------------
# Storage link
# -------------------------------------------------------
php /var/www/html/artisan storage:link --force 2>/dev/null || true

# -------------------------------------------------------
# Start supervisor (PID 1)
# -------------------------------------------------------
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
