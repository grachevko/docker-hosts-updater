docker-hosts-updater
----------

Automatic update /etc/hosts file on start/stop containers which defined `hostname` option.

Usage
-----

Start up `docker-hosts-updater`:

    % docker run -d --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /etc:/opt/etc grachev/docker-hosts-updater

Start containers with `hostname` option

    % docker run --hostname nginx.local nginx  

