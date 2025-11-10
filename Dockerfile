## tecnodesign/studio:v2.0
#
# docker build -t tecnodesign/studio:v2 -t tecnodesign/studio:v2.0 "git@github.com:capile/studio.git#v2.0" && \
# docker push tecnodesign/studio:v2 && \
# docker push tecnodesign/studio:v2.0
FROM php:8.5-rc-fpm-alpine
ARG PHP_PEAR_PHP_BIN="php -d error_reporting=0"
WORKDIR /var/www/studio
COPY . .
RUN apk upgrade --update \
    && \
    apk add --no-cache --update \
      freetype \
      git \
      gnupg \
      libjpeg-turbo \
      libldap \
      libpng \
      libwebp \
      libzip-dev \
      nodejs \
      npm \
      openssh-client \
      postgresql-client \
      yarn \
      zlib \
      && \
    apk add --no-cache --update --virtual .deps $PHPIZE_DEPS \
      curl-dev \
      cyrus-sasl-dev \
      freetype-dev \
      ldb-dev \
      samba-dev \
      libdrm-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      libpq-dev \
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
      zip \
    && \
    pecl install mongodb redis \
    && \
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
      fileinfo \
      gd \
      ldap \
      mbstring \
      pdo \
      pdo_mysql \
      pdo_pgsql \
      simplexml \
      soap \
      zip \
    && \
    rm -rf /tmp/* \
    && \
    docker-php-ext-enable mongodb redis \
    && \
    cd /var/www/studio \
    && \
    apk del .deps \
    && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer --2.2 \
    && \
    cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    && \
    cp /var/www/studio/data/deploy/opcache.ini /usr/local/etc/php/conf.d/opcache.ini \
    && \
    sed -E \
        -e 's/expose_php = On/expose_php = Off/' \
        -e 's/max_execution_time = 30/max_execution_time = 10/' \
        -e 's/max_input_time = 60/max_input_time = 5/' \
        -e 's/^;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^;error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^memory_limit.*/memory_limit = 32M/' \
        -e 's/post_max_size = 8M/post_max_size = 4M/' \
        -e 's/;?default_charset = "UTF-8"/default_charset = "UTF-8"/' \
        -e 's/;?max_input_vars = 1000/max_input_vars = 10000/' \
        -e 's/;?date.timezone =/date.timezone = UTC/' \
        -i $PHP_INI_DIR/php.ini \
    && \
    echo 'max_input_vars = 10000' > $PHP_INI_DIR/conf.d/x-studio.ini \
    && \
    chown www-data $PHP_INI_DIR/conf.d/x-studio.ini \
    && \
    sed -E \
        -e 's/^listen = .*/listen = 9000/' \
        -e 's/^listen\.allowed_clients/;listen.allowed_clients/' \
        -e 's/^user = .*/;user = www-data/' \
        -e 's/^group = .*/;group = www-data/' \
        -e 's/^;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^pm.max_children = .*/pm.max_children = 1000/' \
        -e 's/^pm.start_servers = .*/pm.start_servers = 5/' \
        -e 's/^pm.max_spare_servers .*/pm.max_spare_servers = 100/' \
        -e 's/^;?pm.max_requests = .*/pm.max_requests = 500/' \
        -e 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -e 's/^php_admin_value\[memory_limit\] = .*/;php_admin_value[memory_limit] = 32M/' \
        -e 's/^;?access.format = .*/access.format = "%{REMOTE_ADDR}e %u %t %{HTTP_HOST}e \"%m %r%Q%q\" %s %{milli}d %{kilo}M %C%%"/' \
        -i /usr/local/etc/php-fpm.d/www.conf \
    && \
    sed -E \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
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
ENV HOME=/var/www \
    PATH="${PATH}:/var/www/studio" \
    STUDIO_IP="0.0.0.0" \
    STUDIO_PORT="9999" \
    STUDIO_DEBUG="" \
    STUDIO_MODE="app" \
    STUDIO_APP="" \
    STUDIO_APP_ROOT=/opt/studio \
    STUDIO_PROJECT_ROOT=/opt/studio \
    STUDIO_DATA=/opt/studio/data \
    STUDIO_CONFIG=/var/www/studio/app.yml \
    STUDIO_AUTOLOAD=/var/www/studio/vendor/autoload.php \
    STUDIO_ENV="prod" \
    STUDIO_INIT="" \
    STUDIO_TAG="" \
    STUDIO_CACHE_STORAGE="" \
    STUDIO_MAIL_SERVER="" \
    PHP_FCGI_CHILDREN="1000" \
    PHP_FCGI_START_SERVERS="5" \
    PHP_FCGI_SPARE_SERVERS="100" \
    PHP_FCGI_MAX_REQUESTS="500" \
    FASTCGI_ACCESS_LOG="/dev/stderr" \
    FASTCGI_STATUS_LISTEN=""
RUN composer install --no-dev -n \
    && \
    composer clear-cache \
    && \
    rm -rf ~/.composer/cache ~/.npm/*
VOLUME /opt/studio
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]