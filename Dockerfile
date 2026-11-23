FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

ENV PORT=10000
WORKDIR /var/www
COPY public/ /var/www/public/
COPY src/ /var/www/src/
COPY test-db.php /var/www/test-db.php

RUN printf '%s\n' 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf '%s\n' \
      '<VirtualHost *:${PORT}>' \
      '  DocumentRoot /var/www/public' \
      '  Alias /health /var/www/public/api/incidents/list.php' \
      '  <Directory /var/www/public>' \
      '    Options -Indexes' \
      '    AllowOverride None' \
      '    Require all granted' \
      '  </Directory>' \
      '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' 'display_errors=Off' 'log_errors=On' 'expose_php=Off' > /usr/local/etc/php/conf.d/api.ini

EXPOSE 10000
CMD ["sh", "-c", "php /var/www/test-db.php --init && exec apache2-foreground"]
