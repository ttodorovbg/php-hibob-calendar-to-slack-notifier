FROM alpine:3.16
RUN apk update && apk add --no-cache php curl php-mbstring php8-sqlite3 php-openssl php-phar php-curl php-zip tini libc6-compat

RUN apk add --no-cache tzdata
ARG TIMEZONE
ARG CRONTAB

ENV TZ=${TIMEZONE}
ENV CT=${CRONTAB}

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN printf '[PHP]\ndate.timezone = "%s"\n' $TZ > /etc/php8/conf.d/tzone.ini

RUN printf '%s /usr/bin/php /home/hibob/app/index.php\n\n' "$CT" >> /etc/crontabs/root

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir -p /home/hibob/app
COPY docker-entrypoint.sh /home/hibob/docker-entrypoint.sh
RUN chmod +x /home/hibob/docker-entrypoint.sh
WORKDIR /home/hibob/app

ENTRYPOINT ["/sbin/tini", "--", "/home/hibob/docker-entrypoint.sh"]
