FROM php:8.2-apache

RUN docker-php-ext-install exif

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/images && chown -R www-data:www-data /var/www/html/images

EXPOSE 80
