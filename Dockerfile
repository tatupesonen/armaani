# ---- Build frontend assets ----
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Application image ----
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install system packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    lib32gcc-s1 \
    lib32stdc++6 \
    nginx \
    software-properties-common \
    sqlite3 \
    supervisor \
    unzip \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    php8.4-fpm \
    php8.4-cli \
    php8.4-sqlite3 \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-tokenizer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install SteamCMD
RUN echo steam steam/question select "I AGREE" | debconf-set-selections \
    && echo steam steam/license note '' | debconf-set-selections \
    && dpkg --add-architecture i386 \
    && apt-get update \
    && apt-get install -y --no-install-recommends steamcmd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && ln -sf /usr/games/steamcmd /usr/local/bin/steamcmd

# Configure PHP-FPM
RUN sed -i 's|^listen = .*|listen = /run/php/php-fpm.sock|' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.owner = .*|listen.owner = www-data|' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's|^;listen.group = .*|listen.group = www-data|' /etc/php/8.4/fpm/pool.d/www.conf \
    && mkdir -p /run/php

# Configure Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN mkdir -p /var/log/supervisor

# Copy application
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=frontend /app/public/build public/build

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create default SQLite database
RUN touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

# Storage directories for server files and mods
RUN mkdir -p /data/servers /data/mods \
    && chown -R www-data:www-data /data

ENV STEAMCMD_PATH=/usr/games/steamcmd
ENV SERVERS_BASE_PATH=/data/servers
ENV MODS_BASE_PATH=/data/mods

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
