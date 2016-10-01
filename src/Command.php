<?php

namespace Grachev\DockerHostsUpdater;

use Grachev\DockerHostsUpdater\Model\Event;
use Grachev\DockerHostsUpdater\Model\Host;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class Command extends BaseCommand
{
    const COMMAND = 'docker events';

    const FILTER_FORMAT = ' --filter \'%s=%s\'';

    const FILTERS = [
        'type' => 'container',
        'event' => [DockerEvents::CONTAINER_START, DockerEvents::CONTAINER_STOP],
    ];

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var string
     */
    private $filePath = '/opt/etc/hosts';

    /**
     * @var HostCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ContainerFactory
     */
    private $containerFactory;

    /**
     * @var HostValidator
     */
    private $hostValidator;

    /**
     * @var HostsDumper
     */
    private $dumper;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('command')
            ->setDescription('');
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->filePath) && !is_writable($this->filePath)) {
            throw new ProcessFailedException(sprintf('Can\'t create folder: "%s"', $this->filePath));
        }

        $this->io = new SymfonyStyle($input, $output);
        $this->collectionFactory = new HostCollectionFactory();
        $this->containerFactory = new ContainerFactory();
        $this->hostValidator = new HostValidator();
        $this->dumper = new HostsDumper();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->collectionFactory->fromFile($this->filePath);

        $this->actualizeContainers($collection);

        $this->dumper->dump($collection, $this->filePath);

        foreach ($this->listen() as $event) {
            $this->handleEvent($collection, $event, true);
        }
    }

    /**
     * @param HostCollection $collection
     * @param Event          $event
     */
    private function handleEvent(HostCollection $collection, Event $event, $write = false)
    {
        $container = $event->getContainer();
        $host = new Host($container->getIp(), $container->getHost());

        if (!$this->hostValidator->validate($host)) {
            return;
        }

        if ($event->isStart()) {
            $collection->addHost($host);
        } else {
            $collection->removeHost($host);
        }

        if ($write) {
            $this->dumper->dump($collection, $this->filePath);
            $this->success(sprintf('%s %s %s', $event->getEvent(), $container->getHost(), $container->getIp()));
        }
    }

    /**
     * @param HostCollection $collection
     */
    private function actualizeContainers(HostCollection $collection)
    {
        $process = new Process('docker ps -q');
        $process->mustRun();

        $cids = explode(PHP_EOL, $process->getOutput());

        while ($cid = array_shift($cids)) {
            try {
                $container = $this->containerFactory->fromCid($cid);
                $this->handleEvent($collection, new Event($container, DockerEvents::CONTAINER_START));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @return \Generator|Event[]
     */
    private function listen()
    {
        $command = self::COMMAND;
        foreach (self::FILTERS as $key => $value) {
            if (is_array($value)) {
                foreach ((array) $value as $item) {
                    $command .= sprintf(self::FILTER_FORMAT, $key, $item);
                }
            } else {
                $command .= sprintf(self::FILTER_FORMAT, $key, $value);
            }
        }

        $process = new Process($command);
        $process->start();

        $stored = '';
        foreach ($process as $type => $message) {
            $this->debugMessage(sprintf('EVENT: "%s"', $message));

            if ($process::OUT === $type) {
                if ($stored) {
                    $stored .= $message;

                    if ($this->eventHasEnd($message)) {
                        $message = $stored;
                        $stored = '';

                        $this->veryVerboseMessage(sprintf('Event received fully'));
                    } else {
                        continue;
                    }
                } elseif (!$this->eventHasStart($message) || !$this->eventHasEnd($message)) {
                    $stored .= $message;
                    $this->veryVerboseMessage(sprintf('Catch only part of event: "%s"', $message));

                    continue;
                }

                list($time, $subject, $event, $cid, $info) = explode(' ', $message, 5);

                try {
                    $container = $this->containerFactory->fromCid($cid);

                    yield new Event($container, $event);
                } catch (\Exception $e) {
                }
            } else {
                $this->error($message);
            }
        }
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function formatLine(string $string)
    {
        return sprintf('[%s] %s', date(DATE_ATOM), $string);
    }

    /**
     * @param $event
     *
     * @return bool
     */
    private function eventHasEnd(string $event)
    {
        return ')' === substr(rtrim($event), -1);
    }

    /**
     * @param $event
     *
     * @return bool
     */
    private function eventHasStart(string $event)
    {
        return 0 === strpos(ltrim($event), date('Y'));
    }

    /**
     * @param        $message
     * @param string $type
     */
    private function veryVerboseMessage(string $message, string $type = 'comment')
    {
        if ($this->io->isVeryVerbose()) {
            $this->io->{$type}($this->formatLine($message));
        }
    }

    /**
     * @param        $message
     * @param string $type
     */
    private function debugMessage(string $message, string $type = 'note')
    {
        if ($this->io->isDebug()) {
            $this->io->{$type}($this->formatLine($message));
        }
    }

    /**
     * @param $message
     */
    private function success($message)
    {
        $this->io->success($this->formatLine($message));
    }

    /**
     * @param $message
     */
    private function error($message)
    {
        $this->io->error($this->formatLine($message));
    }
}
