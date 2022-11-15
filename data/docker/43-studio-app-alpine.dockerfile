## tecnodesign/studio-app:alpine-v1.2
#
# docker build -f data/docker/43-studio-app-alpine.dockerfile data/docker -t tecnodesign/studio-app:alpine-v1.2 -t tecnodesign/studio-app:alpine-latest
# docker push tecnodesign/studio-app:alpine-v1.2
# docker push tecnodesign/studio-app:alpine-latest
FROM tecnodesign/studio:alpine-latest
EXPOSE 9999
COPY ./docker-entrypoint.sh /
ENTRYPOINT ["/docker-entrypoint.sh"]
ENV STUDIO_MODE=app
ENV STUDIO_IP=0.0.0.0
CMD ["studio-server"]
