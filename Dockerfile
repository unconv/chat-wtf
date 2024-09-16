FROM php:8.2-apache

RUN mkdir -p /var/www/chatwtf

WORKDIR /var/www/chatwtf

ENV APACHE_DOCUMENT_ROOT /var/www/chatwtf/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip

RUN apt-get install -y libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

COPY requirements.txt .
RUN python3 -m pip install --break-system-packages -r requirements.txt

COPY . /var/www/chatwtf

RUN chown -R www-data:www-data /var/www/chatwtf

EXPOSE 80

CMD ["apache2-foreground"]
