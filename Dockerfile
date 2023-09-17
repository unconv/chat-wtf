FROM php:8.2-apache

WORKDIR /var/www/html/

RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip

RUN apt-get install -y libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

COPY requirements.txt .
RUN python3 -m pip install --break-system-packages -r requirements.txt

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
