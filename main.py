import docker
import re

LABEL = 'ru.grachevko.dhu'
MARKER = '#### DOCKER HOSTS UPDATER ####'
# HOSTS_PATH = '/opt/hosts'
HOSTS_PATH = 'hosts'


def listen():
    for event in docker.events(decode=True):
        if 'container' == event.get('Type') and event.get('Action') in ["start", "stop", "die"]:
            handle()


def scan():
    containers = {}
    for container in docker.containers.list():
        label = container.attrs.get('Config').get('Labels').get(LABEL)
        if label:
            ip = next(iter(container.attrs.get('NetworkSettings').get('Networks').values())).get('IPAddress')
            if ip:
                for string in label.split(';'):
                    if ':' in string:
                        string, priority = string.split(':')
                    else:
                        priority = 0
                    priority = int(priority)

                    if ip not in containers.keys() or containers[ip].get('priority') < priority:
                        containers[ip] = {
                            'priority': priority,
                            'hosts': string_to_array(string),
                            'createdAt': container.attrs.get('Created')
                        }

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
        for ip, value in items:
            lines.append('{} {}'.format(ip, ' '.join(value.get('hosts'))))
        lines.append(MARKER)

    f.seek(0)
    f.truncate()
    f.write('\n'.join(lines))
    f.close()


def handle():
    items = scan().items()
    update(items)


docker = docker.from_env()
handle()
listen()
