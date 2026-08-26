## tecnodesign/studio:v2.0
FROM tecnodesign/studio:v2-dev
USER root
RUN apk --purge del apk-tools curl npm tar yarn \
    && \
    rm -rf /usr/local/bin/docker-php* /usr/local/bin/pear* /usr/local/bin/pecl /usr/local/bin/phpize /usr/bin/composer
USER www-data
