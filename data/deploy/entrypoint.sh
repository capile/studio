#!/bin/sh
set -e

PHP_FCGI_CHILDREN=${PHP_FCGI_CHILDREN:="1000"}
PHP_FCGI_START_SERVERS=${PHP_FCGI_START_SERVERS:="5"}
PHP_FCGI_SPARE_SERVERS=${PHP_FCGI_SPARE_SERVERS:="100"}
PHP_FCGI_MAX_REQUESTS=${PHP_FCGI_MAX_REQUESTS:="500"}

sed -E \
    -e "s/^pm.max_children = .*/pm.max_children = $PHP_FCGI_CHILDREN/" \
    -e "s/^pm.start_servers = .*/pm.start_servers = $PHP_FCGI_START_SERVERS/" \
    -e "s/^pm.max_spare_servers .*/pm.max_spare_servers = $PHP_FCGI_SPARE_SERVERS/" \
    -e "s/^;?pm.max_requests = .*/pm.max_requests = $PHP_FCGI_MAX_REQUESTS/" \
    -i /usr/local/etc/php-fpm.d/www.conf

[ "$STUDIO_INIT" != "" ] && studio :build $STUDIO_INIT &

exec "$@"

exit 0