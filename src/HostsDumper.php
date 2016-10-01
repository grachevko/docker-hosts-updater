<?php

namespace Grachev\DockerHostsUpdater;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HostsDumper
{
    /**
     * @param HostCollection $collection
     * @param string         $path
     */
    public function dump(HostCollection $collection, string $path)
    {
        $array = [];
        foreach ($collection->getHosts() as $host) {
            $ip = $host->getIp();
            $domain = $host->getDomain();

            if (!array_key_exists($ip, $array)) {
                $array[$ip] = [];
            }

            if (!in_array($domain, $array[$ip], true)) {
                $array[$ip][] = $domain;
            }
        }

        $content = '';
        foreach ($array as $ip => $domains) {
            $content .= $ip.' '.implode(' ', $domains).PHP_EOL;
        }

        file_put_contents($path, $content);
    }
}
