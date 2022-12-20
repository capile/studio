## tecnodesign/dev-studio-mongodb:v1.0
#
# docker build -f data/docker/14-dev-studio-mongodb.dockerfile  . -t tecnodesign/dev-studio-mongodb:v1.0 -t tecnodesign/dev-studio-mongodb:latest
# docker push tecnodesign/dev-studio-mongodb:v1.0
# docker push tecnodesign/dev-studio-mongodb:latest
FROM tecnodesign/dev-php-mongodb:v1.0
USER www-data
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/studio && \
    composer install --no-dev && \
    composer clear-cache && \
    rm -rf ~/.composer/cache

WORKDIR /var/www/studio
VOLUME /opt/studio/data
VOLUME /opt/studio/config
ENV STUDIO_DATA=/opt/studio/data
ENV PATH="${PATH}:/var/www/studio"