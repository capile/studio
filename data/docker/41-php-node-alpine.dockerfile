## tecnodesign/php-node:alpine-v1.0
#
# docker build -f data/docker/41-php-node-alpine.dockerfile  data/docker -t tecnodesign/php-node:alpine-v1.0
# docker push tecnodesign/php-node:v1.0
FROM php:fpm-alpine
RUN apk --no-cache add \
    git \
    gnupg \
    freetype-dev \
    libdrm-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxcomposite-dev \
    libxdamage-dev \
    libxkbcommon-dev \
    libxml2-dev \
    libxrandr-dev \
    libxshmfence-dev \
    libzip-dev \
    ldb-dev libldap openldap-dev \
    oniguruma-dev \
    nodejs \
    npm \
    yarn \
    zip
RUN docker-php-ext-configure gd \
    --enable-gd \
    --with-freetype \
    --with-jpeg \
    --with-webp && \
    docker-php-ext-configure ldap && \
    docker-php-ext-install \
    ctype \
    dom \
    fileinfo \
    gd \
    ldap \
    mbstring \
    pdo \
    pdo_mysql \
    simplexml \
    soap \
    zip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini && \
    sed -e 's/expose_php = On/expose_php = Off/' \
        -e 's/max_execution_time = 30/max_execution_time = 10/' \
        -e 's/max_input_time = 60/max_input_time = 5/' \
        -e '/catch_workers_output/s/^;//'  \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^;error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^memory_limit.*/memory_limit = 16M/' \
        -e 's/post_max_size = 8M/post_max_size = 4M/' \
        -e 's/;default_charset = "UTF-8"/default_charset = "UTF-8"/' \
        -e 's/;max_input_vars = 1000/max_input_vars = 10000/' \
        -e 's/;date.timezone =/date.timezone = UTC/' \
        -i $PHP_INI_DIR/php.ini && \
    echo 'max_input_vars = 10000' > $PHP_INI_DIR/conf.d/x-config.ini && \
    sed -e 's/^listen = .*/listen = 9000/' \
        -e 's/^listen\.allowed_clients/;listen.allowed_clients/' \
        -e 's/^user = apache/user = www-data/' \
        -e 's/;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^group = apache/group = www-data/' \
        -e 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -e 's/^;?php_admin_value[memory_limit] = .*/php_admin_value[memory_limit] = 32M/' \
        -i /usr/local/etc/php-fpm.d/www.conf && \
    sed -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf && \
    mkdir -p \
      /var/www/app \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /data && \
    chown 1000:www-data \
      /var/www/app \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /data && \
    chmod 775 \
      /var/www/app \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /data
USER www-data
WORKDIR /var/www