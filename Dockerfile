## tecnodesign/studio:v1.2
#
# docker build -t tecnodesign/studio:latest -t tecnodesign/studio:v1.2 "git@github.com:capile/studio.git#main"
# docker push tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.2
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
      curl-dev \
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
      openssl-dev \
      zlib-dev \
    && \
    pecl install mongodb igbinary redis \
    && \
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
      --with-webp \
    && \
    docker-php-ext-configure ldap \
    && \
    docker-php-ext-install \
      ctype \
      dom \
      fileinfo \
      gd \
      ldap \
      mbstring \
      opcache \
      pdo \
      pdo_mysql \
      simplexml \
      soap \
      zip \
    && \
    docker-php-ext-enable igbinary memcached mongodb redis \
    && \
    rm -rf /tmp/* \
    && \
    apk del .deps \
    && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
WORKDIR /var/www/studio
COPY . .
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    && \
    cp /var/www/studio/data/deploy/opcache.ini /usr/local/etc/php/conf.d/opcache.ini \
    && \
    sed -E 's/expose_php = On/expose_php = Off/' \
        -E 's/max_execution_time = 30/max_execution_time = 10/' \
        -E 's/max_input_time = 60/max_input_time = 5/' \
        -E '/catch_workers_output/s/^;//'  \
        -E 's/^error_log.*/error_log = \/dev\/stderr/' \
        -E 's/^;error_log.*/error_log = \/dev\/stderr/' \
        -E 's/^memory_limit.*/memory_limit = 32M/' \
        -E 's/post_max_size = 8M/post_max_size = 4M/' \
        -E 's/;?default_charset = "UTF-8"/default_charset = "UTF-8"/' \
        -E 's/;?max_input_vars = 1000/max_input_vars = 10000/' \
        -E 's/;?date.timezone =/date.timezone = UTC/' \
        -i $PHP_INI_DIR/php.ini && \
    echo 'max_input_vars = 10000' > $PHP_INI_DIR/conf.d/x-config.ini \
    && \
    sed -E 's/^listen = .*/listen = 9000/' \
        -E 's/^listen\.allowed_clients/;listen.allowed_clients/' \
        -E 's/^user = .*/;user = www-data/' \
        -E 's/^group = .*/;group = www-data/' \
        -E 's/;catch_workers_output.*/catch_workers_output = yes/' \
        -E 's/^pm.max_children = .*/pm.max_children = 1000/' \
        -E 's/^pm.start_servers = .*/pm.start_servers = 5/' \
        -E 's/^pm.max_spare_servers .*/pm.max_spare_servers = 100/' \
        -E 's/^;?pm.max_requests = .*/pm.max_requests = 500/' \
        -E 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -E 's/^php_admin_value\[memory_limit\] = .*/;php_admin_value[memory_limit] = 32M/' \
        -i /usr/local/etc/php-fpm.d/www.conf \
    && \
    sed -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf \
    && \
    echo -e "[safe]\n\tdirectory = *" > /var/www/.gitconfig \
    && \
    mkdir -p \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /var/www/studio/data \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config \
    && \
    chown -R 1000:www-data \
      /var/www/studio \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.gitconfig \
      /var/www/.npm \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config \
      /usr/local/etc/php-fpm.d/ \
    && \
    chmod 775 \
      /var/www/studio \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.npm \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config \
      /usr/local/etc/php-fpm.d/ \
    && \
    chmod 664 \
      /usr/local/etc/php-fpm.conf \
      && \
    rm -rf /var/www/studio/data/web \
    && \
    ln -s "/opt/studio/data/web" /var/www/studio/data/web
USER www-data
ENV HOME=/var/www
RUN composer install --no-dev -n \
    && \
    composer clear-cache \
    && \
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
ENV STUDIO_CACHE_KEY="studio"
ENV STUDIO_CACHE_STORAGE=""
ENV STUDIO_MAIL_SERVER=""
ENV PHP_FCGI_CHILDREN="1000"
ENV PHP_FCGI_START_SERVERS="5"
ENV PHP_FCGI_SPARE_SERVERS="100"
ENV PHP_FCGI_MAX_REQUESTS="500"

VOLUME /opt/studio
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]