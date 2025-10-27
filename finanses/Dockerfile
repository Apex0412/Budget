FROM php:8.1-apache
RUN apt-get update && apt-get install -y libzip-dev zip unzip git && docker-php-ext-install pdo pdo_mysql mysqli && a2enmod rewrite
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data storage
