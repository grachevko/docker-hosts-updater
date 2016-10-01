<?php

namespace Grachev\DockerHostsUpdater\Model;

use Grachev\DockerHostsUpdater\DockerEvents;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class Event
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var string
     */
    private $event;

    /**
     * @param Container $container
     * @param string    $event
     */
    public function __construct(Container $container, string $event)
    {
        $this->container = $container;
        $this->event = $event;
    }

    /**
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @return bool
     */
    public function isStart(): bool
    {
        return DockerEvents::CONTAINER_START === $this->event;
    }
}
