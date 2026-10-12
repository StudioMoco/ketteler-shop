# Shop-Bild für Staging (später Live): offizielles Produktionsbild von Shopware, darin der Stand eines Tags.
# Gebaut von ops/staging/ausliefern.sh auf dem Server, nie von Hand. Kein Bauschritt für JavaScript nötig: Kern-Oberflächen
# sind fertig in den Shopware-Paketen, die eigenen Verwaltungs-Skripte sind ohne Bauschritt geschrieben, das Theme
# übersetzt der Shop beim Start (theme:compile).
FROM composer:2.8 AS composer

FROM ghcr.io/shopware/docker-base:8.3-caddy AS bau
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader \
    && rm -rf /tmp/composer

FROM ghcr.io/shopware/docker-base:8.3-caddy
COPY --from=bau --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 ops/staging/start.sh /usr/local/bin/ketteler-start
ARG STAND=unbekannt
ENV KETTELER_STAND=$STAND
ENTRYPOINT ["/usr/local/bin/ketteler-start"]
