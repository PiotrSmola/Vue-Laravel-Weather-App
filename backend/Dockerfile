FROM php:8.3-cli

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql zip intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
# Na Windowsie plik może trafić z końcami linii CRLF
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

RUN groupadd -g ${GID} app 2>/dev/null || true \
    && useradd -m -u ${UID} -g ${GID} -s /bin/bash app 2>/dev/null || true \
    && mkdir -p /var/www/html/vendor \
    && chown -R ${UID}:${GID} /var/www/html

WORKDIR /var/www/html
USER ${UID}:${GID}

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
