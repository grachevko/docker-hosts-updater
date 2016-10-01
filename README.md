docker-hosts-updater
----------

Automatic update `/etc/hosts` on start/stop containers which defined `hostname` option and contain `.local` postfix.

Usage
-----

Start up `docker-hosts-updater`:

    % docker run -d --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /etc:/opt/etc grachev/docker-hosts-updater
    
Start containers with `hostname` option

    % docker run -d --hostname nginx.local nginx
      
Try to ping from host

    % ping nginx.local
