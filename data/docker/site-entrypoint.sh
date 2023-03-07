#!/bin/sh
set -e

if [ ! -d "$STUDIO_DATA/web/.git" ]; then
    git clone $STUDIO_SITE_REPO "$STUDIO_DATA/web"
fi

studio :assets -v S site

exec php-fpm
