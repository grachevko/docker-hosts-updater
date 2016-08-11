<?php

namespace Grachev\DockerHostsUpdater;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @author Konstantin Grachev <ko@grachev.io>
 */
final class Command extends BaseCommand
{
    const COMMAND = 'docker events';

    const FILTER_FORMAT = ' --filter \'%s=%s\'';

    const FILTERS = [
        'type' => 'container',
        'event' => ['start', 'stop'],
    ];

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var string
     */
    private $filePath = '/var/hosts';

    /**
     * @var array
     */
    private $containers = [];

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
        $this->io = new SymfonyStyle($input, $output);

        if (!@mkdir($this->filePath) && !is_dir($this->filePath)) {
            throw new ProcessFailedException(sprintf('Can\'t create folder: "%s"', $this->filePath));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->actualization();

        $this->listen();
    }

    private function actualization()
    {
        $process = new Process('docker ps -q');
        $process->mustRun();

        $cids = explode(PHP_EOL, $process->getOutput());

        while ($cid = array_shift($cids)) {
            $this->process($cid);
        }
    }

    private function listen()
    {
        $command = self::COMMAND;
        foreach (self::FILTERS as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $command .= sprintf(self::FILTER_FORMAT, $key, $item);
                }
            } else {
                $command .= sprintf(self::FILTER_FORMAT, $key, $value);
            }
        }

        $process = new Process($command);
        $process->start();

        $stored = '';
        foreach ($process as $type => $event) {
            $this->debugMessage(sprintf('EVENT: "%s"', $event));

            if ($process::OUT === $type) {
                if ($stored) {
                    $stored .= $event;

                    if ($this->eventHasEnd($event)) {
                        $event = $stored;
                        $stored = '';

                        $this->veryVerboseMessage(sprintf('Event received fully'));
                    } else {
                        continue;
                    }
                } elseif (!$this->eventHasStart($event) || !$this->eventHasEnd($event)) {
                    $stored .= $event;
                    $this->veryVerboseMessage(sprintf('Catch only part of event: "%s"', $event));

                    continue;
                }

                $cid = explode(' ', $event, 5)[3];
                $this->process($cid);
            } else {
                $this->error($event);
            }
        }
    }

    /**
     * @param $cid
     */
    private function process(string $cid)
    {
        $this->debugMessage('Process ' . $cid);

        $config = $this->getConfig($cid);
        $domainname = $config['Config']['Domainname'];
        $hostname = $config['Config']['Hostname'];

        if (!$domainname) {
            if (false === strpos($hostname, '.')) {
                $this->debugMessage(sprintf('Hostname for %s not defined', $config['Name']));

                return;
            }

            $host = $hostname;
        } else {
            $host = sprintf('%s.%s', $hostname, $domainname);
        }

        $ip = array_shift($config['NetworkSettings']['Networks'])['IPAddress'];

        $this->containers[$cid]['host'] = $host;

        $this->writeFile($host, $ip);
    }

    /**
     * @param string $cid
     *
     * @return array
     */
    private function getConfig(string $cid)
    {
        $process = new Process('docker inspect ' . $cid);
        $process->mustRun();

        return json_decode($process->getOutput(), true)[0];
    }

    /**
     * @param string $host
     * @param string $ip
     */
    private function writeFile(string $host, string $ip = '')
    {
        $path = $this->filePath . '/' . $host;
        $isExists = file_exists($path);

        $this->debugMessage(sprintf('writeFile: host "%s" ip "%s" exist "%s"', $host, $ip, $isExists));

        if (!$ip) {
            if (!$isExists) {
                return;
            }

            if (unlink($path)) {
                $this->success(sprintf('Removed: "%s"', $host));
            } else {
                $this->error(sprintf('Fail on remove - "%s"', $path));
            }

            return;
        }

        $line = sprintf('%s %s', $ip, $host);

        if ($isExists) {
            $oldIp = explode(' ', file_get_contents($path), 2)[0];
            if ($oldIp === $ip) {
                $this->success(sprintf('IP for "%s" not changed', $host));

                return;
            }

            $message = sprintf('Updated: "%s" %s -> %s', $host, $oldIp, $ip);
        } else {
            $message = sprintf('Added: "%s - %s"', $host, $ip);
        }

        file_put_contents($path, $line);
        $this->success($message);
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
     * @param $message
     * @param string $type
     */
    private function veryVerboseMessage(string $message, string $type = 'comment')
    {
        if ($this->io->isVeryVerbose()) {
            $this->io->{$type}($this->formatLine($message));
        }
    }

    /**
     * @param $message
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
