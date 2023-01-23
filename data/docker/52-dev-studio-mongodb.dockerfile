## tecnodesign/dev-studio-mongodb:v1.2
#
# docker build -f data/docker/52-dev-studio-mongodb.dockerfile data/docker -t tecnodesign/dev-studio-mongodb:v1.2 -t tecnodesign/dev-studio-mongodb:latest
# docker push tecnodesign/dev-studio-mongodb:v1.2
# docker push tecnodesign/dev-studio-mongodb:latest

FROM tecnodesign/dev-php-mongodb:v1.0

USER www-data

RUN curl -sL https://github.com/capile/studio/archive/refs/heads/feature/mongodb.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-feature-mongodb/* /var/www/studio \
    && rm -rf /tmp/studio-feature-mongodb \
    && cd /var/www/studio \
    && composer install \
    && composer clear-cache \
    && rm -rf ~/.composer/cache

WORKDIR /var/www/studio

VOLUME [ "/opt/studio/data". "/opt/studio/config" ]

ENV STUDIO_DATA=/opt/studio/data
ENV PATH="${PATH}:/var/www/studio"