## tecnodesign/dev-php-node:v1.0
#
# docker build -f data/docker/11-dev-php-node.dockerfile  data/docker -t tecnodesign/dev-php-node:v1.0
# docker push tecnodesign/dev-php-node:v1.0
FROM tecnodesign/php-node:v1.0
USER root
RUN apt-get update && apt-get install -y \
    mariadb-client \
    rsync \
    vim \
    && rm -rf /var/lib/apt/lists/*
