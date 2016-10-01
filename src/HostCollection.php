<?php

namespace Grachev\DockerHostsUpdater;

use Grachev\DockerHostsUpdater\Model\Host;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HostCollection
{
    /**
     * @var Host[]
     */
    private $hosts = [];

    public function addHost(Host $host)
    {
        $this->hosts[] = $host;
    }

    /**
     * @param Host $Host
     */
    public function removeHost(Host $host)
    {
        foreach ($this->hosts as $key => $item) {
            if ($item->getDomain() === $host->getDomain()) {
                unset($this->hosts[$key]);

                break;
            }
        }
    }

    /**
     * @return Host[]
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }
}
