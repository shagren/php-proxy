FROM php:7-fpm-alpine
LABEL maintainer  "Ilya Gruzinov <igruzinov@yandex.ru>"
VOLUME /app
WORKDIR /app
RUN apk add --no-cache git openssh-client gettext-libs libintl icu-libs

#Required php extensions
RUN apk add --virtual .build-deps --no-cache curl-dev zlib-dev icu-dev gettext-dev cyrus-sasl-dev freetype-dev autoconf gcc libc-dev sqlite-dev make && \
    docker-php-source extract && \
    pecl bundle -d /usr/src/php/ext xdebug && \
    docker-php-ext-install -j "$(grep processor /proc/cpuinfo | wc -l)" mbstring gettext intl opcache xdebug zip pdo_sqlite

#Cleanup
RUN apk del .build-deps && \
    docker-php-source delete && \
    curl -s https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

