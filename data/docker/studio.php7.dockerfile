## tecnodesign/studio:php7-v1.2
#
# docker build -f data/docker/studio.php7.dockerfile  . -t tecnodesign/studio:php7-v1.2 -t tecnodesign/studio:php7
# docker push tecnodesign/studio:php7-v1.2
# docker push tecnodesign/studio:php7
FROM tecnodesign/php7-node:v1.0
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/app && rm -rf /tmp/studio-latest && cd /var/www/app && \
    composer install --no-dev
WORKDIR /var/www/app
