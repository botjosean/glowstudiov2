# syntax=docker/dockerfile:1

# ---- assets: build the Vite/Tailwind frontend -----------------------------
FROM node:22-alpine AS assets
WORKDIR /app
ENV NODE_OPTIONS=--max-old-space-size=1536
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- vendor: install PHP dependencies -------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---- final: php-fpm + nginx + supervisord ----------------------------------
FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        libpq \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        libexif \
        icu \
        imagemagick \
        libwebp-tools \
        # Para sacarle un fotograma a un video de referencia y poder leerlo
        # con el mismo modelo de visión que ya lee las fotos. Ver
        # App\Actions\Content\ExtractVideoFrame.
        ffmpeg \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        g++ \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        libexif-dev \
        icu-dev \
        imagemagick-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        gd \
        exif \
        zip \
        intl \
        pcntl \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
