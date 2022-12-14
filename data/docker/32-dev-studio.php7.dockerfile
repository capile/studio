## tecnodesign/dev-studio:php7-v1.2
FROM tecnodesign/dev-php-node:php7
USER www-data
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && rm -rf /tmp/studio-latest && cd /var/www/studio && \
    composer install
USER root
WORKDIR /var/www/studio
