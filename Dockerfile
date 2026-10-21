## tecnodesign/studio:v2-dev
# docker pull dhi.io/php:8-alpine-dev

FROM dhi.io/alpine-base:3.24-dev AS bin

FROM php:8.5-fpm-alpine AS ext
RUN apk add curl curl-dev --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main \
    && \
    apk add --no-cache --update --virtual .deps $PHPIZE_DEPS \
      cyrus-sasl-dev \
      freetype-dev \
      icu \
      libjpeg-turbo \
      libldap \
      libpng \
      libwebp \
      libpq-dev \
      icu-dev \
      ldb-dev \
      libtool \
      samba-dev \
      libdrm-dev \
      libjpeg-turbo-dev \
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
      yaml-dev \
      zlib \
      zlib-dev \
    && \
    curl -fsL --output /usr/bin/pie https://github.com/php/pie/releases/latest/download/pie.phar && chmod +x /usr/bin/pie \
    && \
    pie install -n mongodb/mongodb-extension \
    && \
    pie install -n thomas-0816/pdo-duckdb-php \
    && \
    pie install -n phpredis/phpredis \
    && \
    pie install -n pecl/yaml \
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
      intl \
      mbstring \
      pdo \
      pdo_mysql \
      pdo_pgsql \
      simplexml \
      soap \
    && \
    rm -rf /tmp/* /usr/bin/pie \
    && \
    apk del .deps curl curl-dev \
    && \
    echo 'max_input_vars = 10000' > $PHP_INI_DIR/conf.d/x-studio.ini \
    && \
    echo 'nonroot:x:65532:65532:nonroot:/var/www:/bin/sh' >> /etc/passwd \
    && \
    chown nonroot $PHP_INI_DIR/conf.d/x-studio.ini \
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
    chown -R 65532 /usr/local/etc/php-fpm.d \
    && \
    sed -E \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf
FROM dhi.io/php:8.5-alpine-dev AS builder
COPY --from=bin /bin /bin
COPY --from=bin /sbin /sbin
RUN apk upgrade --update \
    && \
    apk add curl --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main \
    && \
    apk add --no-cache --update \
      curl \
      freetype \
      git \
      gnupg \
      icu \
      libjpeg-turbo \
      libldap \
      libpng \
      libwebp \
      libpq-dev \
      nodejs \
      npm \
      openssh-client \
      yarn \
      yaml-dev \
      zlib \
      zip
WORKDIR /var/www/studio
COPY . .
COPY --from=ext /usr/local/lib/php/extensions /usr/lib/php/extensions
COPY --from=ext /usr/local/etc/php/conf.d /etc/php-8.5/conf.d
ARG PHP_INI_DIR="/etc/php-8.5"
RUN cd /var/www/studio \
    && \
    rm /etc/php-8.5/conf.d/docker-php-ext-sodium.ini \
      /etc/php-8.5/conf.d/docker-php-ext-intl.ini \
    && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer --2.2 \
    && \
    HOME=/var/www composer install --no-dev -n --ignore-platform-req=ext-zip \
    && \
    composer clear-cache \
    && \
    rm -rf /var/www/.composer/cache /var/www/.npm/* /tmp/* \
    && \
    mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    && \
    sed -E \
        -e 's/expose_php = On/expose_php = Off/' \
        -e 's/max_execution_time = 30/max_execution_time = 10/' \
        -e 's/max_input_time = 60/max_input_time = 5/' \
        -e 's/^;catch_workers_output.*/catch_workers_output = yes/g' \
        -e 's/^error_log.*/error_log = \/dev\/stderr/g' \
        -e 's/^;error_log.*/error_log = \/dev\/stderr/g' \
        -e 's/^memory_limit.*/memory_limit = 32M/g' \
        -e 's/post_max_size = 8M/post_max_size = 4M/g' \
        -e 's/;?default_charset = "UTF-8"/default_charset = "UTF-8"/g' \
        -e 's/;?max_input_vars = 1000/max_input_vars = 10000/g' \
        -e 's/;?date.timezone =/date.timezone = UTC/g' \
        -e 's/;?session.upload_progress.enabled.*/session.upload_progress.enabled = Off/g' \
        -e 's/;?fatal_error_backtraces.*/fatal_error_backtraces = Off/g' \
        -i $PHP_INI_DIR/php.ini \
    && \
    echo -e "[safe]\n\tdirectory = *" > /var/www/.gitconfig \
    && \
    mkdir -p \
      /var/www/.cache \
      /var/www/.composer \
      /var/www/.duckdb \
      /var/www/.npm \
      /var/www/studio/data \
      /opt/studio/data \
      /opt/studio/data/web \
      /opt/studio/config \
   && \
   chown -R nonroot:82 \
     /var/www/studio \
     /var/www/.cache \
     /var/www/.composer \
     /var/www/.duckdb \
     /var/www/.gitconfig \
     /var/www/.npm \
     /opt/studio/data \
     /opt/studio/data/web \
     /opt/studio/config \
   && \
   chmod 775 \
     /var/www/studio \
     /var/www/.cache \
     /var/www/.composer \
     /var/www/.npm \
     /opt/studio/data \
     /opt/studio/data/web \
     /opt/studio/config \
    && \
    rm -rf /var/www/studio/data/web \
    && \
    ln -s "/opt/studio/data/web" /var/www/studio/data/web \
    && \
    apk --purge del apk-tools curl tar yarn zip \
    && \
    rm -f /usr/bin/pear* /usr/bin/pecl /usr/bin/phar* /usr/bin/phpize
FROM dhi.io/php:8.5-alpine-fpm AS src
FROM dhi.io/php:8.5-alpine-fpm
COPY --from=builder /bin /bin
COPY --from=builder /var/www /var/www
COPY --from=builder /usr /usr
COPY --from=src     /usr/local /usr/local
COPY --from=builder /usr/lib/php/extensions /usr/lib/php/extensions
COPY --from=builder /etc/php-8.5/conf.d /etc/php-8.5/conf.d
COPY --from=builder /etc/php-8.5/php.ini /etc/php-8.5/php.ini
COPY --from=builder /opt/studio /opt/studio
COPY --from=ext     /usr/local/etc/php-fpm.d /etc/php-8.5/php-fpm.d
COPY --from=ext     /usr/local/etc/php-fpm.conf /etc/php-8.5/php-fpm.conf
COPY ./data/deploy/opcache.ini /etc/php-8.5/conf.d/opcache.ini
ARG STUDIO_VERSION="2.1.X"
ARG PHP_INI_DIR="/etc/php-8.5"
WORKDIR /var/www/studio
USER 0
RUN sed -E -e 's@:nonroot:/home/nonroot:@:nonroot:/var/www:@' -i /etc/passwd \
    && \
    echo 'www-data:x:82:nonroot' >> /etc/group \
    && \
    chown nonroot:www-data -R /etc/php-8.5/php-fpm.d /var/www /opt/studio \
    && \
    chmod 775 /etc/php-8.5/php-fpm.d /opt/studio \
    && \
    chmod 664 /etc/php-8.5/php-fpm.d/*.conf \
    && \
    sed -E \
        -e "s@/usr/local/etc/php-fpm.d/@$PHP_INI_DIR/php-fpm.d/@g" \
        -i /etc/php-8.5/php-fpm.conf
USER nonroot
ENV HOME=/var/www \
    PATH="${PATH}:/var/www/studio" \
    STUDIO_VERSION="$STUDIO_VERSION" \
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
    STUDIO_FASTLOAD_DIR="" \
    STUDIO_ENV="prod" \
    STUDIO_INIT="" \
    STUDIO_TAG="" \
    STUDIO_CACHE_STORAGE="" \
    STUDIO_MAIL_SERVER="" \
    PHP_INI_DIR=/etc/php-8.5 \
    PHP_FCGI_CHILDREN="1000" \
    PHP_FCGI_START_SERVERS="5" \
    PHP_FCGI_SPARE_SERVERS="100" \
    PHP_FCGI_MAX_REQUESTS="500" \
    PHP_FCGI_QUIT_TIMEOUT="5" \
    FASTCGI_ACCESS_LOG="/dev/stderr" \
    FASTCGI_STATUS_LISTEN=""
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]