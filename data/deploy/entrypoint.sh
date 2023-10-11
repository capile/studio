#!/bin/sh
set -e

#if [ ! -z "$STUDIO_SITE_REPO" ]; then
#    if [ ! -d "$STUDIO_DATA/web/.git" ]; then
#        git clone $STUDIO_SITE_REPO "$STUDIO_DATA/web"
#    fi
#fi

studio :build

exec "$@"
