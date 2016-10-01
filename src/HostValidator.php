<?php

namespace Grachev\DockerHostsUpdater;

use Grachev\DockerHostsUpdater\Model\Host;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HostValidator
{
    /**
     * @param Host $host
     *
     * @return bool
     */
    public function validate(Host $host): bool
    {
        return strpos($host->getDomain(), '.local');
    }
}
