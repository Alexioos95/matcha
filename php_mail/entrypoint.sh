#!/bin/sh
envsubst < /etc/msmtprc_template > /etc/msmtprc
chmod 644 /etc/msmtprc

exec php /usr/local/bin/mail_queue.php
