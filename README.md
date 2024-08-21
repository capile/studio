# Studio CMS

Studio is a multi-purpose data management tool, designed to work as an API provider, CMS or application framework. The main objective is to build an open source easy to use application to build web apps or consume and analyze data.

Ready to start? You'll need:

- PHP version 8.3+
- Composer
- Git

If you already have them, just type:
```
git clone https://github.com/capile/studio.git studio
cd studio && composer install
./studio :start
```

## Docker images

Different purpose Docker images are available at <data/deploy>, compatible with latest PHP/nodejs version.

You can start using it directly with:
```
docker run --rm -v studio-data:/opt/studio/data -p 9999:9999 tecnodesign/studio:latest
```

### Custom configuration

App customization should use `*.yml` files mapped into the `/opt/studio/config` folder. For example, you can load an external git content repository by adding the configuration `web-repos`:

```studio-config/studio.yml
---
all:
  app:
    web-repos:
      - id: www
        src: https://github.com/capile/www.tecnodz.com.git
        mount: /
        mount-src: ~
```

Then running:
```
docker run --rm -v studio-config:/opt/studio/config -v studio-data:/opt/studio/data -p 9999:9999 tecnodesign/studio:latest
```

### Running Docker with source code

If you'd like to work with studio code and repository, you can mount the source repository (remember to fix permissions to user `www-data`):
```
git clone https://github.com/capile/studio.git studio
cd studio
docker run --rm -u $UID -e HOME=/tmp -v $PWD:/var/www/studio tecnodesign/studio:latest composer install --no-dev
find app.yml data/{cache,web*,config} -type f -uid $UID -print0 | xargs -0 chmod 666
find data/{cache,web*,config} -type d -uid $UID -print0 | xargs -0 chmod 777
docker run --rm -v studio-data:/opt/studio/data -v $PWD:/var/www/studio --name studio -p 9999:9999 tecnodesign/studio:latest studio-server
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

## Image/server environment variables

|----------------------|---------------------------|--------------------------------------------------------------------------------------------------------------|
|       Variable       |       Default value       |                                               Description                                                    |
|----------------------|---------------------------|--------------------------------------------------------------------------------------------------------------|
| STUDIO_IP            | "0.0.0.0"                 | IP address to bind to                                                                                        |
| STUDIO_PORT          | "9999"                    | Port to bind                                                                                                 |
| STUDIO_DEBUG         | ""                        | Set to "1" or `true` to enable debug mode                                                                    |
| STUDIO_MODE          | "app"                     | `studio-server` php-fpm mode, either "daemon" (when running on a VM) or "app" (ideal for containers)         |
| STUDIO_CONFIG        | "/var/www/studio/app.yml" | Configuration file, default configuration file loads all `/opt/studio/config/*.yml` files                    |
| STUDIO_DATA          | "/opt/studio/data"        | Folder to store persistent data.                                                                             |
| STUDIO_ENV           | "prod"                    | Current environment (`prod` or `stage` or `dev` or `test`)                                                   |
| STUDIO_INIT          | ""                        | Container initialization arguments ( `-v` for verbosity level and/or `-g` for git integration)               |
| STUDIO_CACHE_KEY     | "studio"                  | Namespace for prefixing cache entries, to avoid conflicts on shared servers                                  |
| STUDIO_CACHE_STORAGE | ""                        | Setup cache, multiple entries separated by space are allowed, can be "file", "apc", or a redis/memcached DSN |
| STUDIO_MAIL_SERVER   | ""                        | Outbound mail server setup, use a DSN (like smtp://localhost:25)                                             |
