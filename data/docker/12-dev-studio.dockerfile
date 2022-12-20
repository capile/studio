## tecnodesign/dev-studio:v1.2
#
# docker build -f data/docker/12-dev-studio.dockerfile data/docker -t tecnodesign/dev-studio:v1.2 -t tecnodesign/dev-studio:latest
# docker push tecnodesign/dev-studio:v1.2
# docker push tecnodesign/dev-studio:latest
FROM tecnodesign/dev-php-node:v1.0
#USER www-data
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/studio && \
    composer install --no-dev && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
#USER root
WORKDIR /var/www/studio
VOLUME /opt/studio/data
VOLUME /opt/studio/config
ENV STUDIO_DATA=/opt/studio/data
ENV PATH="${PATH}:/var/www/studio"