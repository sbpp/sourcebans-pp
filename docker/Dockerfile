# SourceBans++ local-development image.
# Built on the official PHP 8.5 + Apache image. Adds the PHP extensions the
# panel needs at runtime, Composer, and the entrypoint that renders config.php
# and runs `composer install` on first boot. NOT intended for production.

FROM php:8.5-apache

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libgmp-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        default-mysql-client \
        gettext-base \
        nodejs \
        npm \
 && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        gmp \
        intl \
        zip \
        mbstring \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# opcache is bundled into PHP 8.5 core (no longer a separate ext module);
# `docker-php-ext-install opcache` fails on the 8.5 image with
# "cp: cannot stat 'modules/*'" because there's no .so to copy. The
# zz-sbpp-dev.ini block below still tunes the bundled opcache via
# opcache.* INI knobs — verify with `php -m` (Zend OPcache) after build.

# Composer (pinned to the official multi-stage image so we get the latest 2.x).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensible PHP defaults for development: errors on, generous limits, opcache
# revalidates on every request so file edits are picked up immediately.
# auto_prepend_file normalizes HTTP_HOST so init.php's "delete install/"
# check (which only matches a bare "localhost") doesn't trip when serving on
# localhost:8080.
COPY docker/php/dev-prepend.php /usr/local/etc/php/dev-prepend.php
RUN { \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'display_errors=On'; \
        echo 'display_startup_errors=On'; \
        echo 'error_reporting=E_ALL'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'auto_prepend_file=/usr/local/etc/php/dev-prepend.php'; \
    } > /usr/local/etc/php/conf.d/zz-sbpp-dev.ini

# Apache: serve from /var/www/html/web (the panel lives under web/ in the repo).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

COPY docker/php/web-entrypoint.sh /usr/local/bin/web-entrypoint
RUN chmod +x /usr/local/bin/web-entrypoint

WORKDIR /var/www/html/web

ENTRYPOINT ["web-entrypoint"]
CMD ["apache2-foreground"]
