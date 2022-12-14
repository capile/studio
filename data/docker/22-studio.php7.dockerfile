## tecnodesign/studio:php7-v1.2
FROM tecnodesign/php-node:php7
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && rm -rf /tmp/studio-latest && cd /var/www/studio && \
    composer install --no-dev
WORKDIR /var/www/studio
