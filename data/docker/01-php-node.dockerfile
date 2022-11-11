## tecnodesign/php-node:v1.0
#
# docker build -f data/docker/01-php-node.dockerfile  data/docker -t tecnodesign/php-node:v1.0
# docker push tecnodesign/php-node:v1.0
FROM php:fpm
RUN apt-get update && apt-get install -y \
    git \
    gnupg \
    libasound-dev \
    libatk-bridge2.0-dev \
    libatk1.0-0 \
    libcairo-dev \
    libcups2 \
    libdrm-dev \
    libfreetype6-dev \
    libgbm-dev \
    libjpeg-dev \
    libldap2-dev \
    libnss3 \
    libonig-dev \
    libpango-1.0 \
    libpng-dev \
    libwebp-dev \
    libxcomposite-dev \
    libxdamage-dev \
    libxkbcommon-dev \
    libxml2-dev \
    libxrandr-dev \
    libxshmfence-dev \
    libzip-dev \
    zlib1g-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd \
    --enable-gd \
    --with-freetype \
    --with-jpeg \
    --with-webp && \
    docker-php-ext-configure ldap \
    --with-libdir=lib/x86_64-linux-gnu/ && \
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
COPY --from=node:latest /usr/local/bin/node /usr/local/bin/node
COPY --from=node:latest /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
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
        -i /usr/local/etc/php/php.ini && \
    echo 'max_input_vars = 10000' > /usr/local/etc/php/conf.d/x-config.ini && \
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
      /var/www/.npm && \
    chown www-data:www-data \
      /var/www/app \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm && \
    ln -s ../lib/node_modules/asar/bin/asar.js         /usr/local/bin/asar      && \
    ln -s ../lib/node_modules/node-gyp/bin/node-gyp.js /usr/local/bin/node-gyp  && \
    ln -s ../lib/node_modules/nopt/bin/nopt.js         /usr/local/bin/nopt      && \
    ln -s ../lib/node_modules/npm/bin/npm-cli.js       /usr/local/bin/npm       && \
    ln -s ../lib/node_modules/npm/bin/npx-cli.js       /usr/local/bin/npx       && \
    ln -s ../lib/node_modules/semver/bin/semver.js     /usr/local/bin/semver    && \
    ln -s ../lib/node_modules/yarn/bin/yarn.js         /usr/local/bin/yarn      && \
    ln -s ../lib/node_modules/yarn/bin/yarn.js         /usr/local/bin/yarnpkg
USER www-data
WORKDIR /var/www
