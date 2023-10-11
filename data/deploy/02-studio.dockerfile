## tecnodesign/studio:v1.1
#
# docker build -f data/deploy/02-studio-alpine.dockerfile data/deploy -t tecnodesign/studio:latest -t tecnodesign/studio:v1.1
# docker push tecnodesign/studio:latest
# docker push tecnodesign/studio:v1.1
FROM tecnodesign/php-node:latest
RUN curl -sL https://github.com/capile/studio/archive/refs/tags/latest.tar.gz|tar -xzC /tmp && \
    mv /tmp/studio-latest/* /var/www/studio && \
    rm -rf /tmp/studio-latest && \
    cd /var/www/studio && \
    composer install --no-dev -n && \
    composer clear-cache && \
    rm -rf ~/.composer/cache
ENV PATH="${PATH}:/var/www/studio"
ENV STUDIO_IP="0.0.0.0"
ENV STUDIO_PORT="9999"
ENV STUDIO_DEBUG=""
ENV STUDIO_MODE="app"
ENV STUDIO_DATA=/opt/studio/data
WORKDIR /var/www/studio
USER root
RUN mkdir -p "$STUDIO_DATA/web" && chown -R www-data "$STUDIO_DATA/web" && rm -rf data/web && ln -s "$STUDIO_DATA/web" data/web
USER www-data
VOLUME /opt/studio/data
VOLUME /opt/studio/config
ENTRYPOINT ["/var/www/studio/data/deploy/entrypoint.sh"]
CMD ["php-fpm"]