## tecnodesign/dev-php7-node:v1.0
#
# docker build -f data/docker/dev-php7-node.dockerfile  . -t tecnodesign/dev-php7-node:v1.0
# docker push tecnodesign/dev-php7-node:v1.0
FROM tecnodesign/php7-node:v1.0
USER root
RUN apt-get update && apt-get install -y \
    mariadb-client \
    rsync \
    vim \
    && rm -rf /var/lib/apt/lists/*
