# Preserve the existing RoadRunner while replacing the unsupported PHP runtime.
FROM shanginn/temporal-php-worker:latest@sha256:5785678c35b6a39f8ce5074ff652eafaf9c1d82c3e2c94776897fb288f756499 AS roadrunner
FROM composer:2 AS composer
FROM php:8.2.35-cli-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions grpc-1.74.0 protobuf-4.31.1 pcntl sockets xdebug
COPY --from=roadrunner /usr/local/bin/rr /usr/local/bin/rr
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN addgroup -g 1337 worker \
    && adduser -u 1337 -G worker -s /bin/sh -D worker
WORKDIR /worker
COPY --chown=worker:worker . /worker

RUN composer install --no-dev --no-scripts --no-plugins --prefer-dist --no-progress --no-interaction \
    && composer check-platform-reqs --no-dev \
    && php -r 'require "vendor/autoload.php"; if (PHP_VERSION !== "8.2.35") { throw new RuntimeException("PHP 8.2.35 is required"); }'

USER worker
ENTRYPOINT ["/usr/local/bin/rr", "serve", "-c", "/worker/.rr.yaml"]
