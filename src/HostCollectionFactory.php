<?php

namespace Grachev\DockerHostsUpdater;

use Grachev\DockerHostsUpdater\Model\Host;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HostCollectionFactory
{
    /**
     * @param                     $path
     * @param HostCollection|null $collection
     *
     * @return HostCollection
     */
    public function fromFile($path, HostCollection $collection = null): HostCollection
    {
        if (!$collection) {
            $collection = new HostCollection();
        }

        $content = file_get_contents($path);
        if (!$content) {
            return $collection;
        }

        foreach (explode(PHP_EOL, $content) as $line) {
            if ('' === trim($line)) {
                continue;
            }

            $peaces = explode(' ', $line);
            $ip = array_shift($peaces);

            foreach ((array) $peaces as $peace) {
                $collection->addHost(new Host($ip, $peace));
            }
        }

        return $collection;
    }
}
