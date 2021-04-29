FROM php:7.4-fpm

ARG APP_ENV
ARG UID
ARG GID
ARG REMOTE_HOST
ARG PHP_IDE_CONFIG
ARG XDEBUG_CONFIG
ENV APP_ENV=${APP_ENV:-development}
ENV REMOTE_HOST=${REMOTE_HOST:-172.17.0.1}
ENV UID=${UID:-1000}
ENV GID=${GID:-1000}
ENV REMOTE_HOST=${REMOTE_HOST:-172.17.0.1}
ENV PHP_IDE_CONFIG=${PHP_IDE_CONFIG:-"serverName=moealt"}
ENV XDEBUG_CONFIG=${XDEBUG_CONFIG:-"idekey=moealt"}
ENV UNO_PATH=/usr/lib/libreoffice/program
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    	libxml2 \
    	libzip4 \
    	libpng16-16 \
    	ghostscript \
    	aspell \
    	graphviz \
    	libreoffice \
    	python3 \
    	sassc \
    	wget \
    	locales-all \
    	vim \
    	texlive-full \
    	texmaker \
    	;
RUN set -eux; \
	    apt-get install -y --no-install-recommends \
		$PHPIZE_DEPS \
		libpq-dev \
		libjpeg-dev \
		libpng-dev \
		libfreetype6-dev \
		libxml2-dev \
		libzip-dev \
		libxslt-dev
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) \
    	iconv \
    	intl \
    	soap \
    	xml \
    	xmlrpc \
    	opcache \
  	    pdo_pgsql \
    	pgsql \
    	gd \
    	xsl \
    	zip
RUN pecl channel-update pecl.php.net
RUN pecl install xdebug
RUN pecl install lzf-1.6.8
RUN pecl install igbinary-3.1.5
RUN pecl install --onlyreqdeps --nobuild redis-5.3.1 \
    && cd "$(pecl config-get temp_dir)/redis"  \
    && phpize \
    && ./configure --enable-redis-igbinary --enable-redis-lzf \
    && make \
    && make install \
    && docker-php-ext-enable igbinary lzf redis xdebug
RUN set -xe \
	&& apt-get remove -y \
	    $PHPIZE_DEPS \
    		linux-headers \
    		libpq-dev \
    		libpq-dev \
    		libjpeg-dev \
    		libpng-dev \
    		libfreetype6-dev \
    		libxml2-dev \
    		libzip-dev \
    		libxslt-dev \
    		libldap2-dev \
	&& pecl update-channels \
	&& rm -rf /tmp/pear ~/.pearrc

RUN sed -i -e "s%pm.max_children = 5%pm.max_children = 200%" /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%pm.start_servers = 2%pm.start_servers = 10%" /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%pm.max_spare_servers = 3%pm.max_spare_servers = 15%" /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%pm.min_spare_servers = 1%pm.min_spare_servers = 5%" /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%;ping.path = /ping%ping.path = /ping%" /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%;ping.response = pong%ping.response = pong%"  /usr/local/etc/php-fpm.d/www.conf \
	&& sed -i -e "s%;pm.status_path = /status%pm.status_path = /status%"  /usr/local/etc/php-fpm.d/www.conf

RUN set -xe; \
    usermod -u "$UID" www-data; \
    groupmod -g "$UID" www-data;

RUN set -xe; \
     mv /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini; \
     version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;"); \
     curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/alpine/amd64/$version; \
     mkdir -p /tmp/blackfire; \
     tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire; \
     mv /tmp/blackfire/blackfire-*.so $(php -r "echo ini_get('extension_dir');")/blackfire.so; \
     printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8707\nblackfire.log_level=0\n" > $PHP_INI_DIR/conf.d/blackfire.ini; \
     rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz; \
     mkdir -p /tmp/blackfire; \
     curl -A "Docker" -L https://blackfire.io/api/v1/releases/client/linux_static/amd64 | tar zxp -C /tmp/blackfire; \
     mv /tmp/blackfire/blackfire /usr/bin/blackfire; \
     rm -Rf /tmp/blackfire;

RUN wget https://raw.githubusercontent.com/dagwieers/unoconv/master/unoconv -O /usr/bin/unoconv \
    && chmod +x /usr/bin/unoconv \
    && mkdir -p /var/lib/sitedata \
	&& sed -i -e "s%/usr/bin/env python%/usr/bin/python3%" /usr/bin/unoconv
WORKDIR /var/www/html
RUN mkdir /var/local/cache \
	&& chown -R www-data:www-data /var/local/cache \
	&& chmod 775 /var/local/cache
