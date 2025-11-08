#!/bin/sh
set -e

PHP_FCGI_CHILDREN=${PHP_FCGI_CHILDREN:="1000"}
PHP_FCGI_START_SERVERS=${PHP_FCGI_START_SERVERS:="5"}
PHP_FCGI_SPARE_SERVERS=${PHP_FCGI_SPARE_SERVERS:="100"}
PHP_FCGI_MAX_REQUESTS=${PHP_FCGI_MAX_REQUESTS:="500"}
FASTCGI_STATUS_LISTEN=${FASTCGI_STATUS_LISTEN:=""}
FASTCGI_ACCESS_LOG=${FASTCGI_ACCESS_LOG:=""}

sed -E \
    -e "s/^pm.max_children = .*/pm.max_children = $PHP_FCGI_CHILDREN/" \
    -e "s/^pm.start_servers = .*/pm.start_servers = $PHP_FCGI_START_SERVERS/" \
    -e "s/^pm.min_spare_servers .*/pm.min_spare_servers = $PHP_FCGI_START_SERVERS/" \
    -e "s/^pm.max_spare_servers .*/pm.max_spare_servers = $PHP_FCGI_SPARE_SERVERS/" \
    -e "s/^;?pm.max_requests = .*/pm.max_requests = $PHP_FCGI_MAX_REQUESTS/" \
    -i /usr/local/etc/php-fpm.d/www.conf

if [[ "$FASTCGI_STATUS_LISTEN" != "" ]]; then
    sed -E \
        -e "s/;?pm.status_path = .*/pm.status_path = \/status/" \
        -e "s/;?pm.status_listen = .*/pm.status_listen = $FASTCGI_STATUS_LISTEN/" \
        -i /usr/local/etc/php-fpm.d/www.conf
else
    sed -E \
        -e "s/^pm.status_path = /;pm.status_path = /g" \
        -e "s/^pm.status_listen =/;pm.status_listen =/g" \
        -i /usr/local/etc/php-fpm.d/www.conf
fi

if [[ "$FASTCGI_ACCESS_LOG" != "" ]] && [[ "$FASTCGI_ACCESS_LOG" != "off" ]]; then
    if [[ "$FASTCGI_ACCESS_LOG" == "on" ]]; then
        FASTCGI_ACCESS_LOG=/dev/stderr
    fi
    sed -E \
        -e "s|;?access.log = .*|access.log = $FASTCGI_ACCESS_LOG|" \
        -i /usr/local/etc/php-fpm.d/docker.conf
else
    sed -E \
        -e "s/^access.log = /;access.log = /g" \
        -i /usr/local/etc/php-fpm.d/docker.conf
fi

if [[ "$1" == "restart" ]]; then
    PID=$(pgrep 'php-fpm: master process')
    if [[ "$PID" != "" ]]; then
        kill -USR2 $PID
    fi
fi

exit 0