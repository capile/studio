## tecnodesign/studio:v2.0
FROM tecnodesign/studio:v2-dev
USER root
RUN apk --purge del apk-tools npm openssh-client
USER www-data
