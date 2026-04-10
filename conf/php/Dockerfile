# Partir de l'image officielle de PHP comme base
# https://hub.docker.com/_/php
FROM php:8.2-apache

# Installer l'extension pdo_pgsql, postgresql-client pour pg_isready, et dos2unix
RUN apt-get update && apt-get install -y libpq-dev postgresql-client dos2unix && \
    docker-php-ext-install pdo_pgsql && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer le module de réécriture d'URL et les headers de sécurité
RUN a2enmod rewrite headers

# Ajouter configuration PHP custom
COPY conf/php/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Désactiver le vhost par défaut
RUN a2dissite 000-default.conf

# Définir et activer notre vhost custom
COPY conf/php/site.conf /etc/apache2/sites-available/site.conf
RUN a2ensite site

# Ajouter le script d'entrypoint et corriger les fins de ligne Windows (CRLF → LF)
COPY conf/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Utiliser le script comme entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
