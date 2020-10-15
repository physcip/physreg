FROM php:7.3-apache

# Setup PHP 

RUN \
    apt-get update && \
    apt-get install libldap2-dev git -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Bring physreg files into position

RUN mkdir /var/www/html/physreg

COPY . /var/www/html/physreg

RUN mv /var/www/html/physreg/phyreg-id_rsa /etc/
RUN mv /var/www/html/physreg/phyreg-allow /etc/
RUN chown -R www-data:www-data /var/www/html/physreg
RUN chown www-data:www-data /etc/phyreg-id_rsa
RUN chown www-data:www-data /etc/phyreg-allow
