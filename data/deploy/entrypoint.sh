#!/bin/sh
set -e

DIR=$(dirname `realpath "$0"`)
"$DIR/configure-php-fpm.sh"

[ "$STUDIO_INIT" != "" ] && studio :build $STUDIO_INIT &

exec "$@"

exit 0