#!/bin/bash
set +e

source funcs.sh

if [ "$APP_ENV" = "prod" ] || [ "$APP_ENV" = "stage" ]; then

  echo "Check if Moodle DB already Installed"
  php /var/www/html/admin/cli/check_database_schema.php
  dbinstalled=$?

  cd /var/www/html;
  sed -i 's/_hostname_/$(hostname)/g'  /usr/local/etc/php/php.ini

  # IDC Join AD Domain
  if [ $dbinstalled -eq 2 ]; then
    php /var/www/html/admin/cli/install_database.php  --lang=en  \
      --adminpass="Vo0Yw965AZt!9rw4f9ZG" --adminuser="admin" --agree-license \
      --fullname="PRE IDC ${APP_YEAR}" --shortname="IDCPRE${APP_YEAR}" --adminemail=service@sysbind.co.il
  fi
fi

set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

case $1 in
    pause)
        echo "entrypoint pause, sleeping"
        sleep 24h
    ;;
    cron)
        echo "running cron"
        cron -f
esac

exec "$@"
