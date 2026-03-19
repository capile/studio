FROM tecnodesign/alpine:latest
RUN apk upgrade --update --no-cache \
    && \
    addgroup -g 998 -S nginx \
    && \
    adduser -h /var/lib/nginx -u 998 -G nginx -D -S nginx \
    && \
    apk add nginx --update --no-cache \
    && \
    printf "worker_processes  auto;\npcre_jit on;\nerror_log /var/log/nginx/error.log warn;\npid /run/nginx/nginx.pid;\nevents {\n  worker_connections  1024;\n}\nhttp {\n  include /etc/nginx/mime.types;\n  default_type application/octet-stream;\n  server_tokens off;\n  client_max_body_size 10m;\n  sendfile on;\n  tcp_nopush on;\n  ssl_protocols TLSv1.2 TLSv1.3;\n  ssl_session_cache shared:SSL:2m;\n  ssl_session_timeout 1h;\n  ssl_session_tickets off;\n  gzip_vary on;\n  keepalive_timeout  65;\n  include /etc/nginx/conf.d/*.conf;\n}" > /etc/nginx/nginx.conf \
    && \
    mv /etc/nginx/http.d /etc/nginx/conf.d \
    && \
    mkdir /var/www \
    && \
    chown -R nginx:nginx /var/www \
    && \
    apk --purge del apk-tools \
    && \
    rm /bin/sh /bin/busybox
USER nginx
WORKDIR /var/www
ENTRYPOINT ["nginx", "-g", "daemon off;"]