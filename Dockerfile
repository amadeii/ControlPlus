FROM php:8.2-fpm

ARG UID=1000
ARG GID=33

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl \
    libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libicu-dev libonig-dev libxml2-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath exif gd intl pcntl pdo_mysql sockets zip soap \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -g ${GID} www-data \
    && usermod -u ${UID} -g ${GID} www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp/composer
ENV HOME=/var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

COPY docker/check-env.sh /usr/local/bin/check-env.sh

RUN chmod +x /usr/local/bin/check-env.sh \
    && mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000 5173

ENTRYPOINT ["/usr/local/bin/check-env.sh"]
CMD ["php-fpm"]