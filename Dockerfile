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

# Outils utiles à l'entrypoint (postgres-client pour pg_isready)
RUN apk add --no-cache postgresql-client bash

WORKDIR /app

# -----------------------------------------------------------------------------
# Stage DEV — code monté en volume, php server intégré
# -----------------------------------------------------------------------------
FROM base AS dev

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

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

# Variables d'env nécessaires au BUILD uniquement (dummies, écrasés au runtime
# par les vraies valeurs venant de compose.prod.yaml + Coolify env vars)
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build_time_only_dummy_secret_will_be_overridden_at_runtime \
    DATABASE_URL=postgresql://dummy:dummy@localhost:5432/dummy?serverVersion=16 \
    MAILER_DSN=null://null \
    DEFAULT_URI=http://localhost \
    SERVER_NAME=":80" \
    FRANKENPHP_CONFIG="worker ./public/index.php"

# Récupère le code + vendor depuis le stage vendor
COPY --from=vendor --chown=www-data:www-data /app /app

# Compilation des assets (pas de DB requise) + cache clear
# cache:warmup et migrations sont déplacés à l'entrypoint (vraies env vars dispo)
RUN php bin/console asset-map:compile --no-debug \
 && php bin/console cache:clear --no-debug --no-warmup \
 && chown -R www-data:www-data var

# Caddyfile minimaliste pour FrankenPHP
RUN { \
        echo '{$SERVER_NAME}'; \
        echo '  root * /app/public'; \
        echo '  encode zstd br gzip'; \
        echo '  php_server'; \
    } > /etc/caddy/Caddyfile

# Entrypoint : attente DB + migrations + warmup cache puis lance FrankenPHP
COPY docker/entrypoint.prod.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
EXPOSE 443

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
