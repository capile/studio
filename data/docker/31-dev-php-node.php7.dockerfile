## tecnodesign/dev-php-node:php7-v1.0
FROM tecnodesign/php-node:php7
USER root
RUN apt-get update && apt-get install -y \
    mariadb-client \
    rsync \
    vim \
    && rm -rf /var/lib/apt/lists/*
