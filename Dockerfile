
# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www/html

# Install runtime dependencies and PHP extensions
RUN apk add --no-cache \
        bash \
        icu-libs \
        libzip \
        oniguruma \
        libpng \
        libjpeg-turbo \
        freetype \
        mysql-client \
        su-exec \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        linux-headers \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pcntl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini

# PHP-FPM configuration
RUN sed -i 's/^listen = .*/listen = 9000/' \
        /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^;clear_env = no/clear_env = no/' \
        /usr/local/etc/php-fpm.d/www.conf

# Create application user
RUN addgroup -g 1000 -S laravel \
    && adduser -u 1000 -S laravel -G laravel

# Copy Composer files first for layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy application source
COPY --chown=laravel:laravel . .

RUN php artisan package:discover --ansi \
    && php artisan config:clear

# Laravel writable directories
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R laravel:laravel \
        storage \
        bootstrap/cache

USER laravel

EXPOSE 9000

CMD ["php-fpm", "-F"]
