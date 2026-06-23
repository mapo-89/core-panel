#!/usr/bin/env sh
set -eu

/bin/sh /var/www/html/.docker/bin/prepare-local-environment.sh

exec php-fpm -F
