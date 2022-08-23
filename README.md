# Studio CMS

Studio is a multi-purpose data management tool, designed to work as an API provider, CMS or application framework. The main objective is to build an open source easy to use application to build web apps or consume and analyze data.

Ready to start? You'll need:

- PHP version 7.3+
- Composer
- Git

If you already have them, just type:
```
git clone https://github.com/capile/studio.git studio
cd studio && composer install
./studio :start
```

## Docker images

Different purpose Docker images are available at <data/docker>, compatible with latest PHP/nodejs version or to PHP7. Images prefixed with `dev-` enable root access and some additional command-line tools.

If you have docker installed and would like to use this image instead, type:
```
git clone https://github.com/capile/studio.git studio
cd studio
docker-compose -f data/docker/docker-compose.yml up
```

Now access the demo studio on <http://127.0.0.1:9999/_studio>