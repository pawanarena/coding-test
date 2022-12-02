FROM node:latest AS node
FROM php:8.1-apache

USER root

COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN apt-get update && apt-get install -y --no-install-recommends \
    zip unzip coreutils zlib1g-dev libzip-dev libpng-dev libjpeg-dev vim git gnupg

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

RUN apt-get update && apt-get install -y --no-install-recommends nodejs yarn

RUN docker-php-ext-configure gd --with-jpeg=/usr/include/ \
    && docker-php-ext-install zip pdo_mysql gd

# apache modules
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod setenvif

ENV \
    DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER="1" \
    COMPOSER_HOME="/tmp/composer" \
    COMPOSER_MEMORY_LIMIT=-1 \
    GIT_SSL_NO_VERIFY=1

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN set -xe \
    && composer install --no-interaction --no-ansi --prefer-dist --no-scripts --no-cache \
    && yarn install \
    && yarn run build

EXPOSE 8080
