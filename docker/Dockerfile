FROM dunglas/frankenphp:1.13.3-php8.5.12-trixie

WORKDIR /app

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer
COPY . .

RUN install-php-extensions pdo_mysql curl \
    && composer install --no-interaction --no-progress --prefer-dist

CMD ["frankenphp", "run", "--config", "/app/docker/Caddyfile"]
