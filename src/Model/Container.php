<?php

namespace Grachev\DockerHostsUpdater\Model;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class Container
{
    /**
     * @var string
     */
    private $cid;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $ip;

    /**
     * @param string $host
     * @param string $ip
     */
    public function __construct(string $cid, string $host, string $ip)
    {
        $this->host = $host;
        $this->ip = $ip;
        $this->cid = $cid;
    }

    /**
     * @return string
     */
    public function getCid(): string
    {
        return $this->cid;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }
}
