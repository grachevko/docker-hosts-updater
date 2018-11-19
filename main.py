import random
import docker
import ast

MARKER = "#### DOCKER HOSTS UPDATER ####"


def string_to_array(string):
    if string.count('{') != string.count('}'):
        raise ValueError('Invalid host')

    array = []
    for index, char in enumerate(list(string)):
        if '{' == char:
            array.append([index + 1])
        elif '}' == char:
            array[-1].append(index)

    replaces = []
    for start, end in array:
        replaces.append(string[start:end].split(','))

    for index, placeholder in enumerate(replaces):
        string = string.replace(','.join(placeholder), str(index), 1)

    count = 0
    for item in replaces:
        count = count * len(item) if count != 0 else len(item)

    result = []
    while len(result) < count:
        element = []

        for item in replaces:
            element.append(random.choice(item))

        if element not in result:
            result.append(string.format(*element))

    return result


# client = docker.from_env()
# for event in client.events(decode=True):
#     if 'container' == event.get('Type') and event.get('Action') in ["start", "stop", "die"]:
#         for container in client.containers.list():
#             label = container.attrs.get('Config').get('Labels').get('ru.grachevko.dhu')
#             if label:
#                 print(label)
#                 ip = container.attrs.get('NetworkSettings').get('IPAddress')

# string = '{Админ,Либерал,Хохол} {ебаный,тупой,ебливый} {пидр,гондон,петух}'
# print(string)
# for line in string_to_array(string):
#     print(line)

import shlex

s = 'key1=1234 key2="string with space" key3="SrtingWithoutSpace"'

# print(dict(token.split('=') for token in s.split(' ')))
print(s.split(' '))