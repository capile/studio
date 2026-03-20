## tecnodesign/studio:v2.0
FROM tecnodesign/studio:v2-dev
USER root
RUN apk --purge del apk-tools curl npm tar \
    && \
    rm -f /usr/local/bin/docker-php* /usr/local/bin/pear* /usr/local/bin/pecl /usr/local/bin/phpize
USER www-data
