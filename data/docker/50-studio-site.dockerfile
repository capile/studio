## tecnodesign/studio-site:v1.0
#
# docker build -f data/docker/50-studio-site.dockerfile data/docker -t tecnodesign/studio-site:v1.0 -t tecnodesign/studio-site:latest
# docker push tecnodesign/studio-site:v1.0
# docker push tecnodesign/studio-site:latest
FROM tecnodesign/studio:alpine-v1.2
ENV STUDIO_SITE_REPO=https://github.com/capile/www.tecnodz.com.git
USER root
RUN mkdir -p "$STUDIO_DATA/web" && chown -R www-data "$STUDIO_DATA/web" && rm -rf data/web && ln -s "$STUDIO_DATA/web" data/web
USER www-data
WORKDIR /var/www/studio
ENTRYPOINT /var/www/studio/data/docker/site-entrypoint.sh