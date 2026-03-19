## tecnodesign/studio:v1.3
FROM tecnodesign/studio:dev
USER root
RUN apk --purge del apk-tools npm openssh-client
USER www-data