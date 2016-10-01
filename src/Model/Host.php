<?php

namespace Grachev\DockerHostsUpdater\Model;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class Host
{
    /**
     * @var string
     */
    private $ip;

    /**
     * @var array
     */
    private $domain;

    /**
     * @param string $ip
     * @param string $domain
     */
    public function __construct(string $ip, string $domain)
    {
        $this->ip = $ip;
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }
}
