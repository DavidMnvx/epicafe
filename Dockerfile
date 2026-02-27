FROM php:8.4-cli-alpine

# System deps + PHP extensions for PostgreSQL + intl
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    postgresql-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install intl pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# PHP upload limits
RUN printf "upload_max_filesize=64M\npost_max_size=64M\nmemory_limit=256M\nmax_file_uploads=50\nmax_execution_time=120\n" \
  > /usr/local/etc/php/conf.d/uploads.ini