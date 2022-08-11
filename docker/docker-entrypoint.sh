#!/usr/bin/env sh

set -e
composer update --working-dir=/home/calendar/app
tail -f /dev/null