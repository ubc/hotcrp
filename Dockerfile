
FROM php:8.2-fpm

RUN docker-php-ext-install mysqli


RUN apt-get update && \
    apt-get install -y \
        zlib1g-dev libzip-dev \
	libicu-dev libgmp-dev \
	re2c libmhash-dev \
	libmcrypt-dev file \
	poppler-utils netcat-openbsd

RUN apt-get install -y -q --no-install-recommends \
		msmtp

RUN ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/local/include/
RUN docker-php-ext-configure gmp
RUN docker-php-ext-install gmp

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

RUN docker-php-ext-install zip

# And clean up the image

RUN rm -rf /var/lib/apt/lists/*

COPY docker/www.conf /usr/local/etc/php-fpm.d/
COPY docker/php.ini /usr/local/etc/php/
COPY --chmod=755 docker/docker-entrypoint.sh /docker-entrypoint.sh
COPY . /var/www/html

RUN touch /var/log/msmtp.log && chown www-data:www-data /var/log/msmtp.log && mkdir /shared

WORKDIR /var/www/html

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
