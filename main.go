package main

import (
	"time"
	"strings"
	"log"
	"errors"
	"sync"
	"os"
	"os/exec"
	"bufio"
	"github.com/fsouza/go-dockerclient"
)

const marker = "#### DOCKER HOSTS UPDATER ####"

var (
	path = "/opt/hosts"
	client *docker.Client
	ips map[string]string
	mutex sync.Mutex
)

func init() {
	dockerClient, err := docker.NewClient("unix:///var/run/docker.sock")
	if err != nil {
		panic(err)
	}

	client = dockerClient
	ips = make(map[string]string)
}

func main() {
	containers, err := client.ListContainers(docker.ListContainersOptions{})
	if err != nil {
		panic(err)
	}

	added := false
	for _, container := range containers {
		container, err := client.InspectContainer(container.ID)
		if err != nil {
			log.Fatal(err)

			continue
		}

		add(container)
		added = true
	}

	if added {
		updateFile()
	}

	listen()
}

func listen() {
	listener := make(chan *docker.APIEvents)
	err := client.AddEventListener(listener)
	if err != nil {
		log.Fatal(err)
	}

	defer func() {
		err = client.RemoveEventListener(listener)
		if err != nil {
			log.Fatal(err)
		}
	}()

	timeout := time.After(1 * time.Second)

	for {
		select {
		case event := <-listener:
			if "container" != event.Type {
				continue
			}

			container, _ := client.InspectContainer(event.Actor.ID);

			if "start" == event.Action {
				add(container)
				updateFile()
			} else if "stop" == event.Action || "kill" == event.Action || "destroy" == event.Action {
				if remove(container) {
					updateFile()
				}
			}
		case <-timeout:
			continue
		}
	}
}

func add(container *docker.Container) {
	hosts, err := getHosts(container)
	if err != nil {
		return
	}

	ip, err := getIp(container)
	if err != nil {
		return
	}

	ips[ip] = hosts
}

func remove(container *docker.Container) (bool) {
	ip, err := getIp(container)
	if err != nil {
		return false
	}

	for _, value := range ips {
		if value == ip {
			delete(ips, ip)

			return true
		}
	}

	return false
}

func getIp(container *docker.Container) (string, error) {
	ip := container.NetworkSettings.IPAddress;
	if (0 < len(ip)) {
		return ip, nil
	}

	for _, network := range container.NetworkSettings.Networks {
		return network.IPAddress, nil
	}

	return "", errors.New("IP not found")
}

func getHosts(container *docker.Container) (string, error) {
	var hosts string

	domainname := container.Config.Domainname
	if (0 == len(domainname)) {
		return "", errors.New("Unsupported container")
	}

	hostname := container.Config.Hostname + "." + domainname

	hosts = hostname

	subdomains := container.Config.Labels["subdomains"]
	if 0 < len(subdomains) {
		for _, host := range strings.Split(subdomains, " ") {
			hosts += " " + host + "." + hostname
		}
	}

	return hosts, nil
}

func updateFile() {
	mutex.Lock()
	defer mutex.Unlock()

	file, err := os.Open(path)
	if err != nil {
		panic(err)
	}
	defer file.Close()

	lines := make([]string, 0)

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		text := scanner.Text()
		if strings.Contains(text, marker) {
			break
		}

		lines = append(lines, text)
	}

	if err := scanner.Err(); err != nil {
	     log.Fatal("Scan file", err)
	}

	lines = append(lines, marker)

	for ip, host := range ips {
		lines = append(lines, ip + " " + host)
	}

	err = exec.Command("sh", "-c", "echo -e \"" + strings.Join(lines, "\\n") + "\" > " + path).Run()
	if err != nil {
		log.Fatal("Write file: ", err)
	}
}
