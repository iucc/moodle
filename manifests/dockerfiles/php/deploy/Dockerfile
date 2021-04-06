ARG BASE_TAG
ARG CLIENT_NAME
FROM eu.gcr.io/zabbixtest-906/$CLIENT_NAME/php:$BASE_TAG
ARG APP_ENV
ENV APP_ENV=${APP_ENV:-prod}

COPY --chown=www-data:www-data ./ /var/www/html
RUN rm -rf /var/www/html/manifests
