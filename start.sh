#!/bin/bash

# Start MySQL
service mysql start

# Secure MySQL root user 
if ! mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1" >/dev/null 2>&1; then
  mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}'; FLUSH PRIVILEGES;"
fi

# Translate Service
cd /app
/opt/venv/bin/uvicorn app:app --host 0.0.0.0 --port 8000 >> /var/log/translator.log 2>&1 &

# fix permissions ( for latest added files)
(
  while true; do
    find /var/www/html -not -user www-data -exec chown www-data:www-data {} + 2>/dev/null
    find /var/www/html -type d ! -perm -o+rx -exec chmod o+rx {} + 2>/dev/null
    find /var/www/html -type f ! -perm -o+r -exec chmod o+r {} + 2>/dev/null
    sleep 3
  done
) &

# Start Apache in foreground (en sonda olmalı)
cd /var/www/html
exec apache2ctl -D FOREGROUND