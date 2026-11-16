# =============================================================================
# backend/Dockerfile — dashboard PHP (FrankenPHP + Caddy)
#
# Satu image dipakai untuk:
#   - docker-compose (listen port 80 di dalam container)
#   - Vercel Container preset (listen $PORT, default 80)
#
# Build lokal:  docker build -t bogana-php ./backend
# Jalankan    :  docker run --rm -p 8080:80 -e AI_SERVICE_URL=http://host.docker.internal:8000 bogana-php
# =============================================================================

FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apk add --no-cache unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
COPY src ./src

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY public ./public
COPY templates ./templates
COPY Caddyfile /etc/caddy/Caddyfile

ENV PORT=80

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]