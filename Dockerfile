FROM dunglas/frankenphp:php8.4-bookworm

RUN apt-get update && apt-get install -y git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean

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