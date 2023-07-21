#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
        set -- php-fpm "$@"
fi

# cp static files to a mount shared with static container
cp -R /var/www/html/stylesheets /var/www/html/scripts /var/www/html/images /shared/

exec "$@"
