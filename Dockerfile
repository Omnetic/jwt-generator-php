# syntax=docker/dockerfile:1
FROM php:8.4-cli-alpine

# Composer binary from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Git + unzip let Composer fetch and unpack packages.
# ext-openssl (needed later for RS256 signing) ships enabled in official PHP images.
RUN apk add --no-cache git unzip

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

WORKDIR /app

CMD ["php", "-v"]
