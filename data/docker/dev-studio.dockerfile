## tecnodesign/dev-studio:v1.2
#
# docker build -f data/docker/dev-studio.dockerfile  . -t tecnodesign/dev-studio:v1.2 -t tecnodesign/dev-studio:latest
# docker push tecnodesign/dev-studio:v1.2
# docker push tecnodesign/dev-studio:latest
FROM tecnodesign/dev-php-node:v1.0
USER www-data
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/app && rm -rf /tmp/studio-latest && cd /var/www/app && \
    composer install
USER root
WORKDIR /var/www/app
