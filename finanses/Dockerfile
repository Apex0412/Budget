FROM php:8.2-apache

# Install Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install system dependencies and PHP extensions required by the application
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        zip \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxpm-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        intl \
        mbstring \
        bcmath \
        exif \
        zip \
        gmp \
        soap \
        pcntl \
        calendar \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

COPY docker/entrypoint.sh /usr/local/bin/finanses-entrypoint
RUN chmod +x /usr/local/bin/finanses-entrypoint \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p storage/uploads storage/pdf storage/logs \
    && chown -R www-data:www-data storage

ENTRYPOINT ["finanses-entrypoint"]
CMD ["apache2-foreground"]
