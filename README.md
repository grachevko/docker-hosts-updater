docker-hosts-updater
----------
Automatic update `/etc/hosts` on start/stop containers by labels.

Requirements
-----
* **Native linux**  
_This tool has no effect on macOS or windows, because docker on this OS running in 
VM and you can't directly access from host to each container via ip._  

Usage
-----
Start up `docker-hosts-updater`:

```bash
$ docker run -d --restart=always \
    --name docker-hosts-updater \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v /etc/hosts:/opt/hosts \
    grachev/docker-hosts-updater
```
    
Start containers with label `ru.grachevko.dhu` option

    % docker run -d --label ru.grachevko.dhu=nginx.local nginx
      
Try to ping from host

    % ping nginx.local

Multiple Hosts
-----
You can add multiple hosts, just separate it by semicolon:

```bash
$ docker run --label ru.grachevko.dhu="nginx.local;nginx.ru" nginx
$ ping nginx.local
$ ping nginx.ru
```

Subdomains
-----
Add subdomains by used pattern `{www,api}.nginx.local`:

```bash
$ docker run -d --label ru.grachevko.dhu="{www,api}.nginx.local" nginx
$ ping nginx.local
$ ping www.nginx.local
$ ping api.nginx.local
```

Priority
----
If you want to run two containers with same hosts and want that one will override another, 
just add priority after colon:

```bash
$ docker run -d --label ru.grachevko.dhu="nginx.local" nginx
$ docker run -d --label ru.grachevko.dhu="nginx.local:10" nginx
$ ping nginx.local
```
Container with greater priority will be used, by default priority is 0. 
If priority are the same then early created container will be used.

Load Balancer
----
In order to pass traffic through loadbalancer you can define container name which ip will be used to record in hosts. 
Just add one more colon and container name after.
```bash
$ docker run -d --name lb nginx
$ docker run -d --label ru.grachevko.dhu="nginx1.local:0:lb" nginx
$ docker run -d --label ru.grachevko.dhu="nginx2.local:0:lb" nginx
$ ping nginx1.local // ip of lb
$ ping nginx2.local // ip of lb
```
Keep in mind, loadbalancer container must have fixed name.