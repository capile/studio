## tecnodesign/studio-app:v1.2
#
# docker build -f data/docker/03-studio-app.dockerfile data/docker -t tecnodesign/studio-app:v1.2 -t tecnodesign/studio-app:latest
# docker push tecnodesign/studio-app:v1.2
# docker push tecnodesign/studio-app:latest
FROM tecnodesign/studio:v1.2
EXPOSE 9999
COPY ./docker-entrypoint.sh /
ENTRYPOINT ["/docker-entrypoint.sh"]
ENV STUDIO_MODE=app
ENV STUDIO_IP=0.0.0.0
CMD ["studio-server"]
