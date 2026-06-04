#!/bin/sh
set -e

for dir in /var/www/html/www/cache /var/www/html/userdata /var/www/html/conf; do
  mkdir -p "$dir"
  chown -R www-data:www-data "$dir" 2>/dev/null || true
  chmod -R ug+rwX "$dir" 2>/dev/null || true
done

exec "$@"
