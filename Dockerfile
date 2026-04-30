# =============================================================================
# Multi-stage Dockerfile : DEV (php-cli + serveur intégré) ET PROD (FrankenPHP)
# Build dev :  docker compose build           (target: dev par défaut)
# Build prod : docker build --target prod -t epicafe:prod .
# =============================================================================

# -----------------------------------------------------------------------------
# Stage commun "base" — outils partagés
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS base

RUN install-php-extensions \
        intl \
        pdo_pgsql \
        opcache \
        zip \
        gd

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN { \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "memory_limit=256M"; \
        echo "max_file_uploads=50"; \
        echo "max_execution_time=120"; \
        echo "date.timezone=Europe/Paris"; \
    } > /usr/local/etc/php/conf.d/app.ini

# Outils utiles pour l'entrypoint runtime (pg_isready + bash)
RUN apk add --no-cache postgresql-client bash

WORKDIR /app

# -----------------------------------------------------------------------------
# Stage DEV — code monté en volume
# -----------------------------------------------------------------------------
FROM base AS dev

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

# -----------------------------------------------------------------------------
# Stage PROD — runtime FrankenPHP
# -----------------------------------------------------------------------------
FROM base AS prod

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=":80" \
    FRANKENPHP_CONFIG="worker ./public/index.php"

# Copie du code applicatif
COPY --chown=www-data:www-data . /app

# Install Composer en mode prod, SANS exécuter les scripts (qui ont besoin de
# variables d'env Symfony qu'on n'a pas au build). Tout sera fait au runtime
# par l'entrypoint avec les vraies env vars.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --classmap-authoritative \
 && chown -R www-data:www-data /app

# Caddyfile pour FrankenPHP
RUN { \
        echo '{$SERVER_NAME}'; \
        echo '  root * /app/public'; \
        echo '  encode zstd br gzip'; \
        echo '  php_server'; \
    } > /etc/caddy/Caddyfile

# Entrypoint qui prépare l'app puis lance FrankenPHP
COPY docker/entrypoint.prod.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
EXPOSE 443

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
