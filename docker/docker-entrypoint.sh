#!/bin/sh
set -e

wait_for_mysql() {
    until nc -z -v -w30 "mysql" "3306"; do
        echo "Waiting for MySQL to be ready..."
        sleep 2
    done
    echo "Database is ready!"
}

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

# cp static files to a mount shared with static container
cp -R /var/www/html/stylesheets /var/www/html/scripts /var/www/html/images /shared/

if [ ! -e "conf/options.php" ]; then
    echo "Creating conf/options.php from docker/hotcrp-options.php"
    cp docker/hotcrp-options.php conf/options.php
fi

wait_for_mysql

# provision db, db user and schema
if [ -n "$MYSQL_ROOT_PASSWORD" ]; then
    if php batch/createdb.php -u root --password=$MYSQL_ROOT_PASSWORD -n $MYSQL_DATABASE --batch --dbuser $MYSQL_USER,$MYSQL_PASSWORD --host mysql --grant-host '%'; then
        echo "Database has been initialized."
    fi
fi

# create sysadmin user
if [ -n "$HOTCRP_ADMIN_EMAIL" ]; then
    php batch/saveusers.php -u $HOTCRP_ADMIN_EMAIL -r "sysadmin"
fi

exec "$@"
