import docker
import re
from netaddr import valid_ipv4

LABEL = 'ru.grachevko.dhu'
MARKER = '#### DOCKER HOSTS UPDATER ####'
HOSTS_PATH = '/opt/hosts'


def listen():
    for event in docker.events(decode=True):
        if 'container' == event.get('Type') and event.get('Action') in ["start", "stop", "die"]:
            handle()


def scan():
    containers = []
    for container in docker.containers.list():
        label = container.attrs.get('Config').get('Labels').get(LABEL)
        if not label:
            continue

        for string in label.split(';'):
            priority = 0
            lb = container
            ip = False

            if ':' in string:
                parts = string.split(':')
                string = parts[0]
                priority = int(parts[1]) if len(parts) >= 2 else priority

                if len(parts) == 3:
                    lbString = parts[2]

                    if valid_ipv4(lbString):
                        ip = lbString
                    else:
                        lb = docker.containers.get(lbString)

            if ip == False:
                ip = next(iter(lb.attrs.get('NetworkSettings').get('Networks').values())).get('IPAddress')

            if ip:
                containers.append({
                    'ip': ip,
                    'priority': priority,
                    'hosts': string_to_array(string),
                    'createdAt': container.attrs.get('Created'),
                })

    return containers


def string_to_array(input_string):
    dd = [(rec.group().replace("{", "").replace("}", "").split(","), rec.span()) for rec in
          re.finditer("{[^}]*}", input_string)]

    texts = []
    if len(dd) != 0:
        for i in range(len(dd)):
            if i == 0:
                if dd[0][1][0] == 0:
                    texts.append("")
                else:
                    texts.append(input_string[0:dd[0][1][0]])
            else:
                texts.append(input_string[dd[i - 1][1][1]:dd[i][1][0]])
            if i == len(dd) - 1:
                texts.append(input_string[dd[-1][1][1]:])
    else:
        texts = [input_string]

    if len(dd) > 0:
        idxs = [0] * len(dd)
        summary = []

        while idxs[0] != len(dd[0][0]):
            summary_string = ""
            for i in range(len(idxs)):
                summary_string += texts[i] + dd[i][0][idxs[i]]
            summary_string += texts[-1]
            summary.append(summary_string)
            for j in range(len(idxs) - 1, -1, -1):
                if j == len(idxs) - 1:
                    idxs[j] += 1
                if j > 0 and idxs[j] == len(dd[j][0]):
                    idxs[j] = 0
                    idxs[j - 1] += 1
    else:
        summary = texts

    return summary


def update(items):
    f = open(HOSTS_PATH, 'r+')
    lines = []
    skip_lines = False
    for line in f.read().split('\n'):
        if line == MARKER:
            skip_lines = not skip_lines
            continue

        if not skip_lines:
            lines.append(line)

    if items:
        lines.append(MARKER)
        for ip, value in items.items():
            line = '{} {}'.format(ip, ' '.join(value))
            lines.append(line)
            print(line)
        lines.append(MARKER)

    summary = '\n'.join(lines)

    f.seek(0)
    f.truncate()
    f.write(summary)
    f.close()


def handle():
    print('Recompiling...')
    items = scan()

    map_dict = {}
    for item in items:
        for host in item.get('hosts'):
            if host in map_dict:
                priority_left = map_dict[host].get('priority')
                priority_right = item.get('priority')

                if priority_left > priority_right:
                    continue

                if priority_left == priority_right and map_dict[host].get('createdAt') < item.get('createdAt'):
                    continue

            map_dict[host] = item

    summary = {}
    for item in items:
        ip = item.get('ip')
        for host in item.get('hosts'):
            if map_dict[host].get('ip') == ip:
                if ip not in summary:
                    summary[ip] = []
                summary[ip].append(host)

    update(summary)


docker = docker.from_env()
handle()
listen()
