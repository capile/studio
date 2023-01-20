## tecnodesign/studio:v1.2
#
# docker build -f data/docker/02-studio.dockerfile  data/docker -t tecnodesign/studio:v1.2 -t tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.2
# docker push tecnodesign/studio:latest
FROM tecnodesign/php-node:v1.0
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/studio && \
    composer install --no-dev -n && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
WORKDIR /var/www/studio
VOLUME /opt/studio/data
VOLUME /opt/studio/config
ENV STUDIO_DATA=/opt/studio/data
ENV PATH="${PATH}:/var/www/studio"
