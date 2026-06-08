FROM php:8.4-apache

RUN apt-get update && \
	apt-get install -y msmtp msmtp-mta mailutils gettext-base && \
	docker-php-ext-install pdo pdo_mysql && \
	echo "sendmail_path = \"/usr/bin/msmtp -t\"" >> /usr/local/etc/php/conf.d/sendmail.ini

COPY ./msmtprc_template /etc/msmtprc_template
COPY ./mail_queue.php /usr/local/bin/mail_queue.php
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
