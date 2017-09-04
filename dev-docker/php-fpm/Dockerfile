FROM php:7-fpm-alpine
LABEL maintainer  "Ilya Gruzinov <igruzinov@yandex.ru>"
VOLUME /app
WORKDIR /app
RUN apk add --no-cache gd git openssh-client gettext-libs libintl icu-libs

#Required php extensions
RUN apk add --virtual .build-deps --no-cache libjpeg-turbo-dev libpng-dev curl-dev zlib-dev icu-dev gettext-dev cyrus-sasl-dev freetype-dev autoconf gcc libc-dev sqlite-dev make && \
    docker-php-source extract && \
    docker-php-ext-configure gd --with-gd  --with-freetype-dir=/usr/include/ --with-png-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && \
    pecl bundle -d /usr/src/php/ext xdebug && \
    docker-php-ext-install -j "$(grep processor /proc/cpuinfo | wc -l)" mbstring gettext intl opcache xdebug zip pdo_sqlite gd
#Cleanup
RUN apk del .build-deps && \
    docker-php-source delete && \
    curl -s https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

