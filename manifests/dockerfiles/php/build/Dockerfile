FROM php:7.4-fpm

ARG APP_ENV
ARG UID
ARG GID
ARG REMOTE_HOST
ARG PHP_IDE_CONFIG
ARG XDEBUG_CONFIG

ENV APP_ENV=${APP_ENV:-development}
ENV REMOTE_HOST=${REMOTE_HOST:-172.17.0.1}
ENV UID=${UID:-33}
ENV GID=${GID:-33}
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV REMOTE_HOST=${REMOTE_HOST:-172.17.0.1}
ENV PHP_IDE_CONFIG=${PHP_IDE_CONFIG:-"serverName=moealt"}
ENV XDEBUG_CONFIG=${XDEBUG_CONFIG:-"idekey=moealt"}
ENV UNO_PATH=/usr/lib/libreoffice/program
ARG REMOTE_HOST

LABEL org.opencontainers.image.vendor=SysBind
LABEL org.opencontainers.image.authors="SysBind"
LABEL org.opencontainers.image.revision=$CI_COMMIT_SHA
LABEL org.opencontainers.image.source="https://gitlab.sysbind.biz/developers/moodle-iucc"
LABEL org.opencontainers.image.documentation="https://gitlab.sysbind.biz/developers/moodle-iucc/-/blob/master/README.txt"
LABEL org.opencontainers.image.licenses="https://gitlab.sysbind.biz/developers/moodle-iucc/-/blob/master/LICENSE.txt"
LABEL org.opencontainers.image.url="https://gitlab.sysbind.biz:4567/developers/moodle-iucc/php"

RUN set -eux; \
    	apt-get update; \
    	apt-get install -y --no-install-recommends \
    	    apt-utils

RUN ln -s  /usr/lib/x86_64-linux-gnu/libsybdb.so /usr/lib/ \
    && ln -fs /usr/share/zoneinfo/Israel /etc/localtime \
    && dpkg-reconfigure -f noninteractive tzdata

RUN apt update && apt install -y locales \
    && echo 'en_US.UTF-8 UTF-8' > /etc/locale.gen \
    && echo 'en_AU.UTF-8 UTF-8' >> /etc/locale.gen \
    && echo 'he_IL.utf8 UTF-8' >> /etc/locale.gen \
    && locale-gen

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        gd \
        iconv \
        igbinary \
        imagick \
        intl \
        lzf \
        opcache \
        pdo_pgsql \
        pgsql \
        redis \
        soap \
        xdebug \
        xml \
        xmlrpc \
        xsl \
        zip

RUN set -xe; \
    usermod -u "$UID" www-data; \
    groupmod -g "$UID" www-data;

VOLUME /var/www/moodledata

RUN chown -R www-data /var/www/ \
    && mkdir /var/sessions \
    && mkdir /var/local/cache \
    && chown www-data /var/sessions \
    && chown www-data /var/local/cache

ENV current_os=linux
RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
  && architecture=$(case $(uname -m) in i386 | i686 | x86) echo "i386" ;; x86_64 | amd64) echo "amd64" ;; aarch64 | arm64 | armv8) echo "arm64" ;; *) echo "amd64" ;; esac) \
  && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/$architecture/$version \
  && mkdir -p /tmp/blackfire \
  && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire \
  && mv /tmp/blackfire/blackfire-*.so $(php -r "echo ini_get ('extension_dir');")/blackfire.so \
  && printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8707\n" > $PHP_INI_DIR/conf.d/blackfire.ini \
  && rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz \
  && mkdir -p /tmp/blackfire \
  && architecture=$(case $(uname -m) in i386 | i686 | x86) echo "i386" ;; x86_64 | amd64) echo "amd64" ;; aarch64 | arm64 | armv8) echo "arm64" ;; *) echo "amd64" ;; esac) \
  && curl -A "Docker" -L https://blackfire.io/api/v1/releases/client/linux/$architecture | tar zxp -C /tmp/blackfire \
  && mv /tmp/blackfire/blackfire /usr/bin/blackfire \
  && rm -Rf /tmp/blackfire

RUN chmod a=rwx,o+t /tmp

RUN mkdir -p /var/local/cache \
	&& chown -R www-data:www-data /var/local/cache \
	&& chmod 775 /var/local/cache

RUN set -eux; \
        apt-get update; \
    	apt-get install -y --no-install-recommends \
            aspell \
            ca-certificates \
            curl \
            gawk \
            ghostscript \
            git \
            graphviz \
            libreoffice \
            mawk \
            python3 \
            sassc \
            sendmail \
            texlive-full \
            vim \
            wget \
        ; \
        rm -rf /var/lib/apt/lists/*

RUN wget https://raw.githubusercontent.com/dagwieers/unoconv/master/unoconv -O /usr/bin/unoconv \
    && chmod +x /usr/bin/unoconv \
    && mkdir -p /var/lib/sitedata \
	&& sed -i -e "s%/usr/bin/env python%/usr/bin/python3%" /usr/bin/unoconv

COPY manifests/dockerfiles/scripts/unoconv-script /usr/local/bin/
COPY manifests/dockerfiles/scripts/composer /usr/local/bin/

RUN chmod +x /usr/local/bin/unoconv-script \
	&& sed -i -e "s%/usr/bin/env python%/usr/bin/python3%" /usr/bin/unoconv \
	&& chmod +x /usr/local/bin/composer

WORKDIR /var/www/html
