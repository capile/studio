#!/bin/bash
set -e

if [ "$1" = 'studio' ]; then
    studio :build
fi

exec "$@"
