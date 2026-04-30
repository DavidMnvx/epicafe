# =============================================================================
# Multi-stage Dockerfile : DEV (php-cli + serveur intégré) ET PROD (FrankenPHP)
# Build dev :  docker compose build           (target: dev par défaut)
# Build prod : docker build --target prod -t epicafe:prod .
# =============================================================================

# -----------------------------------------------------------------------------
# Stage commun "base" — outils partagés
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS base

# Extensions PHP nécessaires (Postgres + intl + opcache)
RUN install-php-extensions \
        intl \
        pdo_pgsql \
        opcache \
        zip \
        gd

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# PHP config commune
RUN { \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "memory_limit=256M"; \
        echo "max_file_uploads=50"; \
        echo "max_execution_time=120"; \
        echo "date.timezone=Europe/Paris"; \
    } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /app

# -----------------------------------------------------------------------------
# Stage DEV — code monté en volume, php server intégré
# -----------------------------------------------------------------------------
FROM base AS dev

# php.ini développement (assertions on, error reporting verbose)
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

# Le code est monté en volume via compose.override.yaml
# CMD est défini dans compose.yaml (php -S 0.0.0.0:8000 -t public)

# -----------------------------------------------------------------------------
# Stage VENDOR — install Composer en cache séparé
# -----------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

COPY . /app
RUN composer dump-autoload --no-dev --classmap-authoritative --optimize

# -----------------------------------------------------------------------------
# Stage PROD — runtime FrankenPHP optimisé
# -----------------------------------------------------------------------------
FROM base AS prod

# php.ini production
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Variables d'env de build (peuvent être surchargées au run)
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=":80" \
    FRANKENPHP_CONFIG="worker ./public/index.php"

# Récupère le code + vendor depuis le stage vendor
COPY --from=vendor --chown=www-data:www-data /app /app

# Asset Mapper compile + warmup cache prod
RUN php bin/console asset-map:compile --no-debug \
 && php bin/console cache:clear --no-debug --no-warmup \
 && php bin/console cache:warmup --no-debug \
 && chown -R www-data:www-data var

# Caddyfile minimaliste pour FrankenPHP
RUN { \
        echo '{$SERVER_NAME}'; \
        echo '  root * /app/public'; \
        echo '  encode zstd br gzip'; \
        echo '  php_server'; \
    } > /etc/caddy/Caddyfile

EXPOSE 80
EXPOSE 443

# Healthcheck pour Coolify
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1
