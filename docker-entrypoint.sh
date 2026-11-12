#!/bin/sh
set -e

# Support dynamic PORT from cloud platforms (defaults to 8000 on Koyeb)
PORT="${PORT:-8000}"

sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Ensure data and sites directories are writable by Apache
mkdir -p /var/www/html/data /var/www/html/sites
chown -R www-data:www-data /var/www/html/data /var/www/html/sites
chmod -R 775 /var/www/html/data /var/www/html/sites

exec "$@"

