#!/bin/sh
DOMAIN=${DOMAIN:-localhost}

mkdir -p /etc/apache2/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl/apache.key -out /etc/apache2/ssl/apache.crt -subj "/C=FR/ST=IDF/L=Paris/O=42/CN=${DOMAIN}"

exec apache2-foreground
