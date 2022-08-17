#!/usr/bin/env sh

crond -l 2 -f > /dev/stdout 2> /dev/stderr &

set -e
composer update --working-dir=/home/hibob/app
tail -f /dev/null