FROM php:alpine

MAINTAINER Konstantin Grachev <ko@grachev.io>

ENV APP_DIR /usr/local/app
ENV PATH ${APP_DIR}/bin:${PATH}

WORKDIR ${APP_DIR}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apk add --no-cache \
		ca-certificates \
		curl \
		openssl

ENV DOCKER_BUCKET get.docker.com
ENV DOCKER_VERSION 1.12.0
ENV DOCKER_SHA256 3dd07f65ea4a7b4c8829f311ab0213bca9ac551b5b24706f3e79a97e22097f8b

RUN set -x \
	&& curl -fSL "https://${DOCKER_BUCKET}/builds/Linux/x86_64/docker-${DOCKER_VERSION}.tgz" -o docker.tgz \
	&& echo "${DOCKER_SHA256} *docker.tgz" | sha256sum -c - \
	&& tar -xzvf docker.tgz \
	&& mv docker/* /usr/local/bin/ \
	&& rmdir docker \
	&& rm docker.tgz \
	&& docker -v

COPY ./ ${APP_DIR}

RUN chmod +x -R $APP_DIR/bin/* \
    && composer install --optimize-autoloader --no-interaction --quiet

ENTRYPOINT ["app.php", "--no-ansi"]
