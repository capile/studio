## tecnodesign/studio:v1.1
#
# docker build -f data/deploy/02-studio-alpine.dockerfile data/deploy -t tecnodesign/studio:latest -t tecnodesign/studio:v1.1
# docker push tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.1
FROM php:8.2-fpm-alpine
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
    openssh-client \
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
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer --2.2
WORKDIR /var/www/studio
COPY . .
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
RUN composer install --no-dev -n && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
ENV PATH="${PATH}:/var/www/studio"
ENV STUDIO_IP="0.0.0.0"
ENV STUDIO_PORT="9999"
ENV STUDIO_DEBUG=""
ENV STUDIO_MODE="app"
ENV STUDIO_DATA=/opt/studio/data
ENV STUDIO_CONFIG=/var/www/studio/app.yml
VOLUME /opt/studio/data
VOLUME /opt/studio/config
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]