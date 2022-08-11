FROM alpine:3.16
RUN apk update && apk add --no-cache php curl php-mbstring php8-sqlite3 php-openssl php-phar php-curl tini libc6-compat


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


RUN mkdir -p /home/calendar/app
COPY docker-entrypoint.sh /home/calendar/docker-entrypoint.sh
RUN chmod 777 -R /home/calendar/

ENTRYPOINT ["/sbin/tini", "--", "/home/calendar/docker-entrypoint.sh"]
