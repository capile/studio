## tecnodesign/studio:alpine-v1.2
#
# docker build -f data/docker/42-studio-alpine.dockerfile  data/docker -t tecnodesign/studio:alpine-v1.2 -t tecnodesign/studio:alpine-latest
# docker push tecnodesign/studio:alpine-v1.2
# docker push tecnodesign/studio:alpine-latest
FROM tecnodesign/php-node:alpine-v1.0
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/app && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/app && \
    composer install --no-dev && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
WORKDIR /var/www/app
VOLUME /data
ENV PATH="${PATH}:/var/www/app"
ENV STUDIO_DATA=/data