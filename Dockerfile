# syntax=docker/dockerfile:1

# =============================================================================
# Stage 1: Frontend & Static Site Generator (SSG) Builder (Node.js 24)
# =============================================================================
FROM node:24-bookworm-slim AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json vite.config.ts biome.json render.tsx ./
COPY src ./src
COPY public ./public

ARG MERCADOPAGO_PUBLIC_KEY=""
ENV MERCADOPAGO_PUBLIC_KEY=${MERCADOPAGO_PUBLIC_KEY}
RUN npm run build

# =============================================================================
# Stage 2: Composer Dependency Builder
# =============================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader

# =============================================================================
# Stage 3: Runtime Image (PHP 8.3 + Apache + Node.js 24 + Composer)
# =============================================================================
FROM php:8.3-apache AS runtime

# Install system packages & PHP extensions (mbstring, pdo, pdo_sqlite, sodium, curl, json, argon2 are built-in)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        sendmail \
    && docker-php-ext-install -j"$(nproc)" \
        opcache \
        pdo_mysql \
    && a2enmod rewrite headers mime env dir \
    && rm -rf /var/lib/apt/lists/*

# Copy Node.js 24 & Composer so CLI builds and verification suites work inside the container
COPY --from=frontend-builder /usr/local/bin/node /usr/local/bin/node
COPY --from=frontend-builder /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY --from=composer-builder /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/ovf

# Copy application source
COPY . /var/www/ovf

# Copy installed dependencies and compiled SSG artifacts
COPY --from=composer-builder /app/vendor /var/www/ovf/vendor
COPY --from=frontend-builder /app/node_modules /var/www/ovf/node_modules
COPY --from=frontend-builder /app/dist /var/www/ovf/dist

# Stage cPanel Pattern A public_html/ from compiled dist/ and unify cache directory
RUN mkdir -p /var/www/ovf/public_html \
    && cp -rf /var/www/ovf/dist/. /var/www/ovf/public_html/ \
    && mkdir -p /var/www/ovf/public_html/cache \
    && rm -rf /var/www/ovf/public/cache \
    && ln -sfn /var/www/ovf/public_html/cache /var/www/ovf/public/cache \
    && chown -R www-data:www-data /var/www/ovf/public_html/cache

# Configure Apache VirtualHosts (Unprivileged Ports 8080 -> Public Portal, 8081 -> Admin Console)
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && chown -R www-data:www-data /var/www/ovf/public_html /var/www/ovf/public /var/run/apache2 /var/log/apache2 /var/lock/apache2

# Configure entrypoint
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 8080 8081

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
