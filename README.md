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

You can start using it directly with:
```
docker run --rm -v studio-data:/data -p 9999:9999 tecnodesign/studio-app:latest
```

### Running Docker with source code

If you'd like to work with studio code and repository, you can mount the source repository (remember to fix permissions to user `www-data`):
```
git clone https://github.com/capile/studio.git studio
cd studio
docker run --rm -u $UID -e HOME=/tmp -v $PWD:/var/www/app tecnodesign/studio-app:latest composer install
find app.yml data/{cache,web*,config} -type f -uid $UID -print0 | xargs -0 chmod 666
find data/{cache,web*,config} -type d -uid $UID -print0 | xargs -0 chmod 777
docker run --rm -v studio-data:/data -v $PWD:/var/www/app -p 9999:9999 tecnodesign/studio-app:latest
```

Or using docker-compose:
```
git clone https://github.com/capile/studio.git studio
cd studio
docker-compose -f data/docker/docker-compose.yml up
```

Running with local source code might require a filesystem check for the writable condition of the container user, so you should either run docker with the `-u $UID` option (might lead to some errors), or adjust the local permissions on the `data/` folder:
```
find data/{cache,web*,config} -type f -uid $UID -print0 | xargs -0 chmod 666
find data/{cache,web*,config} -type d -uid $UID -print0 | xargs -0 chmod 777
```

Now access the demo studio on <http://127.0.0.1:9999/_studio>