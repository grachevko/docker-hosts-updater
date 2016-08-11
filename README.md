docker-hosts-updater
----------

Automatic create files with same format as `/etc/hosts` on start/stop containers which defined `hostname` option.

Usage
-----

Start up `docker-hosts-updater`:

    % docker run -d --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v /var/hosts:/var/hosts grachev/docker-hosts-updater
    
For `dnsmasq` put line to `/etc/default/dnsmasq`

    % DNSMASQ_OPTS="--hostsdir=/var/hosts"

Restart dnsmasq

    % sudo service dnsmasq restart
    
Start containers with `hostname` and `domainname` option

    % docker run -d --hostname nginx --domainname local nginx
      
Try to ping from host

    % ping nginx.local
