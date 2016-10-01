FROM php:alpine

MAINTAINER Konstantin Grachev <ko@grachev.io>

ENV APP_DIR /usr/local/app
ENV PATH ${APP_DIR}/bin:${PATH}
ENV HOSTS_DIR /var/hosts

WORKDIR ${APP_DIR}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apk add --no-cache docker

COPY ./ ${APP_DIR}/
RUN composer install --optimize-autoloader --no-interaction

ENTRYPOINT ["console", "--no-ansi"]
