## tecnodesign/dev-php-mongodb:v1.0
#
# docker build -f data/docker/13-dev-php-mongodb.dockerfile  . -t tecnodesign/dev-php-mongodb:v1.0
# docker push tecnodesign/dev-php-mongodb:v1.0
FROM tecnodesign/php-node:v1.0

ENV EXT_MONGODB_VERSION=1.14.1

USER root
RUN docker-php-source extract \
    && apt-get update && apt-get install -y \
    rsync \
    vim \
    && rm -rf /var/lib/apt/lists/* \
    && git clone --branch $EXT_MONGODB_VERSION --depth 1 https://github.com/mongodb/mongo-php-driver.git /usr/src/php/ext/mongodb \
    && cd /usr/src/php/ext/mongodb && git submodule update --init \
    && docker-php-ext-install mongodb