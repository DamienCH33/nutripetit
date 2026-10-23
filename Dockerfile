FROM dunglas/frankenphp:1.13.2-php8.4-bookworm

RUN apt-get update && apt-get install -y git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean

# Sans php.ini, PHP tourne avec ses valeurs par défaut (display_errors=1,
# expose_php=1 -> en-tête X-Powered-By). On part du profil production.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\nopcache.validate_timestamps = 0\nsession.upload_progress.enabled = 0\n' > "$PHP_INI_DIR/conf.d/zz-nutripetit.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=prod
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8080

CMD set -e && \
    php -m && \
    php bin/console cache:clear --env=prod --no-debug && \
    php bin/console asset-map:compile --env=prod && \
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && \
    php bin/console app:sync-scoring-rules && \
    exec frankenphp run --config /app/Caddyfile