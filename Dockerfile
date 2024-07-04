## tecnodesign/studio:v1.1
#
# docker build -t tecnodesign/studio:latest -t tecnodesign/studio:v1.1 "git@github.com:capile/studio.git#main"
# docker push tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.1
FROM php:8.3-fpm-alpine
RUN apk add --no-cache --update \
      ffmpeg \
      git \
      gnupg \
      libldap \
      libmemcached-libs \
      nodejs \
      npm \
      openssh-client \
      libzip-dev \
      yarn \
      zip \
      zlib
RUN apk add --no-cache --update --virtual .deps $PHPIZE_DEPS \
      cyrus-sasl-dev \
      freetype-dev \
      ldb-dev \
      libdrm-dev \
      libjpeg-turbo-dev \
      libmemcached-dev \
      libpng-dev \
      libwebp-dev \
      libxcomposite-dev \
      libxdamage-dev \
      libxkbcommon-dev \
      libxml2-dev \
      libxrandr-dev \
      libxshmfence-dev \
      oniguruma-dev \
      openldap-dev \
      zlib-dev && \
    pecl install mongodb igbinary && \
    ( \
        pecl install --nobuild memcached && \
        cd "$(pecl config-get temp_dir)/memcached" && \
        phpize && \
        ./configure --enable-memcached-igbinary && \
        make -j$(nproc) && \
        make install && \
        cd /tmp/ \
    ) && \
    docker-php-ext-configure gd \
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
      zip && \
    docker-php-ext-enable igbinary memcached mongodb && \
    rm -rf /tmp/* && \
    apk del .deps && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer --2.2
WORKDIR /var/www/studio
COPY . .
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini && \
    sed -e 's/expose_php = On/expose_php = Off/' \
        -e 's/max_execution_time = 30/max_execution_time = 10/' \
        -e 's/max_input_time = 60/max_input_time = 5/' \
        -e '/catch_workers_output/s/^;//'  \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^;error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^memory_limit.*/memory_limit = 32M/' \
        -e 's/post_max_size = 8M/post_max_size = 4M/' \
        -e 's/;default_charset = "UTF-8"/default_charset = "UTF-8"/' \
        -e 's/;max_input_vars = 1000/max_input_vars = 10000/' \
        -e 's/;date.timezone =/date.timezone = UTC/' \
        -i $PHP_INI_DIR/php.ini && \
    echo 'max_input_vars = 10000' > $PHP_INI_DIR/conf.d/x-config.ini && \
    sed -e 's/^listen = .*/listen = 9000/' \
        -e 's/^listen\.allowed_clients/;listen.allowed_clients/' \
        -e 's/^user = .*/;user = www-data/' \
        -e 's/^group = .*/;group = www-data/' \
        -e 's/;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^pm.max_children = .*/pm.max_children = 100/' \
        -e 's/^pm.start_servers = .*/pm.start_servers = 5/' \
        -e 's/^pm.max_spare_servers .*/pm.max_spare_servers = 10/' \
        -e 's/^;?pm.max_requests = .*/pm.max_requests = 500/' \
        -e 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -e 's/^php_admin_value\[memory_limit\] = .*/;php_admin_value[memory_limit] = 32M/' \
        -i /usr/local/etc/php-fpm.d/www.conf && \
    sed -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf && \
    echo -e "[safe]\n\tdirectory = *" > /var/www/.gitconfig && \
    chown www-data:www-data /var/www/.gitconfig && \
    mkdir -p \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /var/www/studio/data \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config && \
    chown 1000:www-data \
      /var/www/studio \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config && \
    chmod 775 \
      /var/www/studio \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config && \
    rm -rf /var/www/studio/data/web && \
    ln -s "/opt/studio/data/web" /var/www/studio/data/web
USER www-data
WORKDIR /var/www/studio
ENV HOME=/var/www
RUN composer install --no-dev -n && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
ENV PATH="${PATH}:/var/www/studio"
ENV STUDIO_IP="0.0.0.0"
ENV STUDIO_PORT="9999"
ENV STUDIO_DEBUG=""
ENV STUDIO_MODE="app"
ENV STUDIO_APP_ROOT=/opt/studio
ENV STUDIO_PROJECT_ROOT=/opt/studio
ENV STUDIO_DATA=/opt/studio/data
ENV STUDIO_CONFIG=/var/www/studio/app.yml
ENV STUDIO_AUTOLOAD=/var/www/studio/vendor/autoload.php
ENV STUDIO_ENV="prod"
ENV STUDIO_INIT=""
VOLUME /opt/studio
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]