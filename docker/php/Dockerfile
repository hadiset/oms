FROM docker.io/library/php:8.5-fpm-alpine

RUN apk update && apk add \
  libpq-dev \
  zip \
  unzip \
  git \
  curl \
  && docker-php-ext-install pdo pdo_pgsql pgsql

COPY --from=docker.io/library/composer:latest /usr/bin/composer /usr/bin/composer

RUN composer global require laravel/installer
ENV PATH="$PATH:/root/.composer/vendor/bin"

WORKDIR /var/www

