FROM dunglas/frankenphp:php8.5-bookworm

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN install-php-extensions pdo_mysql curl \
    && composer install --no-interaction --no-progress --prefer-dist

CMD ["frankenphp", "run", "--config", "/app/docker/Caddyfile"]
