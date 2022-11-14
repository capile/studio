## tecnodesign/studio:v1.2
#
# docker build -f data/docker/02-studio.dockerfile  data/docker -t tecnodesign/studio:v1.2 -t tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.2
# docker push tecnodesign/studio:latest
FROM tecnodesign/php-node:v1.0
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/app && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/app && \
    composer install --no-dev && \
    composer clear-cache && \
    rm -rf ~/.composer/cache \
    ln -s /var/www/app/studio /usr/local/bin/studio
WORKDIR /var/www/app
VOLUME /studio
ENV STUDIO_DATA=/studio
