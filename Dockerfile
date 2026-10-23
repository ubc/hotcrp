
FROM php:8.3.36-fpm

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

# PHP 8.3's bundled timezone database (2026.1) predates BC's move to permanent UTC-7 (no fall
# back on 2026-11-01), so America/Vancouver deadlines from November on would be shown and saved
# an hour off. The PECL timezonedb extension replaces it with the current database. The check
# fails the build if America/Vancouver would still fall back to UTC-8 in December 2026.
RUN pecl install timezonedb \
    && docker-php-ext-enable timezonedb \
    && rm -rf /tmp/pear \
    && php -r 'exit((new DateTime("2026-12-01 12:00", new DateTimeZone("America/Vancouver")))->getOffset() === -7 * 3600 ? 0 : 1);'

# And clean up the image

RUN rm -rf /var/lib/apt/lists/*

COPY docker/www.conf /usr/local/etc/php-fpm.d/
COPY docker/php.ini /usr/local/etc/php/
COPY --chmod=755 docker/docker-entrypoint.sh /docker-entrypoint.sh
COPY . /var/www/html
# workaround: add Root CA as Debian bookwarm doesn't have this one in the stable release packages.
# It likely will include in the next release.
COPY docker/SSL.com_TLS_RSA_Root_CA_2022.pem /etc/ssl/certs/SSL.com_TLS_RSA_Root_CA_2022.pem

RUN touch /var/log/msmtp.log && chown www-data:www-data /var/log/msmtp.log && mkdir /shared && update-ca-certificates --fresh

WORKDIR /var/www/html

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
