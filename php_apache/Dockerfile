FROM php:8.4-apache

RUN apt-get update && \
	apt-get install -y openssl libpng-dev libjpeg-dev libfreetype6-dev && \
	docker-php-ext-configure gd --with-jpeg --with-freetype && \
	docker-php-ext-install gd pdo pdo_mysql && \
	a2enmod ssl && \
	a2enmod rewrite 

COPY ./000-default-ssl.conf /etc/apache2/sites-available/000-default-ssl.conf
RUN a2ensite 000-default-ssl

COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY ./script /usr/local/bin/script
RUN chmod 755 /usr/local/bin/script

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]