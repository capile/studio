## tecnodesign/studio:v2.1
FROM tecnodesign/studio:v2-dev
USER 0
RUN rm -rf /usr/bin/curl /usr/bin/zip* /usr/bin/npm /usr/bin/yarn* /usr/share/node_modules /usr/bin/pear* /usr/bin/pecl /usr/bin/phar* /usr/bin/phpize /usr/bin/composer
USER nonroot
