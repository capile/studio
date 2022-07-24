## tecnodesign/dev-php-node:v1.0
#
# docker build -f Dockerfile  . -t tecnodesign/dev-php-node:v1.0
# docker push tecnodesign/dev-php-node:v1.0
FROM tecnodesign/php-node:v1.0

RUN apt-get install -y \
    mariadb-client \
    rsync \
    vim

USER root
WORKDIR /app

#COPY . /var/www/app
#RUN composer install --no-dev