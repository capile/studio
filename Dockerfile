## tecnodesign/studio:v2.0
FROM tecnodesign/studio:v2-dev
USER root
RUN apk --purge del apk-tools npm
USER www-data
