From php:8.2-apache
Run docker-php-ext-install pdo pdo_sqlite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80