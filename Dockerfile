FROM php:8.2-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev default-mysql-client && docker-php-ext-install pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json /app/composer.json
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress || true

COPY . /app
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

EXPOSE 8080
CMD ["sh", "-lc", "php -S 0.0.0.0:8080 -t public"]