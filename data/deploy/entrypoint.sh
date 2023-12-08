#!/bin/sh
set -e

studio :build $STUDIO_INIT

exec "$@"
ls