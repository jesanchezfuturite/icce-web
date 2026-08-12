FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libzip-dev libicu-dev libonig-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli gd zip intl bcmath opcache exif pcntl

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuario no-root alineado con el UID/GID del host para evitar archivos root en el volumen
RUN groupadd -g "${GID}" -o icce \
    && useradd -m -u "${UID}" -g "${GID}" -o -s /bin/bash icce

WORKDIR /var/www/html

USER icce

CMD ["php-fpm"]
