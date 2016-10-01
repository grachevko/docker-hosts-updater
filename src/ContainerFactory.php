<?php

namespace Grachev\DockerHostsUpdater;

use Grachev\DockerHostsUpdater\Model\Container;
use Symfony\Component\Process\Process;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class ContainerFactory
{
    /**
     * @param string $cid
     *
     * @return Container
     */
    public function fromCid(string $cid) : Container
    {
        $config = $this->getConfig($cid);
        $domainname = $config['Config']['Domainname'];
        $hostname = $config['Config']['Hostname'];

        if (!$domainname) {
            $host = $hostname;
        } else {
            $host = sprintf('%s.%s', $hostname, $domainname);
        }

        $ip = array_shift($config['NetworkSettings']['Networks'])['IPAddress'];

        return new Container($cid, $host, $ip);
    }

    /**
     * @param string $cid
     *
     * @return array
     */
    private function getConfig(string $cid)
    {
        $process = new Process('docker inspect '.$cid);
        $process->mustRun();

        return json_decode($process->getOutput(), true)[0];
    }
}
