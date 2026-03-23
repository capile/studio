## tecnodesign/studio:v1.3
FROM tecnodesign/studio:dev
USER root
RUN apk --purge del apk-tools npm tar
USER www-data