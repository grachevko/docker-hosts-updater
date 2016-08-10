<?php

namespace Grachev\DockerHostsUpdater;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * @author Konstantin Grachev <ko@grachev.io>
 */
final class Command extends BaseCommand
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var string
     */
    private $filePath = '/opt/etc/hosts';

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
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $process = new Process('docker events --filter \'type=container\' --filter \'event=start\' --filter \'event=stop\'');
        $process->start();

        foreach ($process as $type => $event) {
            if ($this->io->isDebug()) {
                $this->io->note($event);
            }

            if ($process::OUT === $type) {
                list($time, $type, $action, $cid, $labels) = explode(' ', $event, 5);

                if ('stop' === $action) {
                    if (array_key_exists($cid, $this->containers)) {
                        $this->writeFile($this->containers[$cid]['host']);

                        unset($this->containers[$cid]);
                    }

                    continue;
                }

                if ('start' !== $action) {
                    if ($this->io->isVeryVerbose()) {
                        $this->io->warning(sprintf('Undefined action in: "%s"', $event));
                    }

                    continue;
                }

                $config = $this->getConfig($cid);
                if (!$domain = $config['Config']['Domainname']) {
                    continue;
                }

                $host = sprintf('%s.%s', $config['Config']['Hostname'], $domain);
                $ip = array_shift($config['NetworkSettings']['Networks'])['IPAddress'];

                $this->containers[$cid]['host'] = $host;

                $this->writeFile($host, $ip);
            } else {
                $this->io->error($this->formatLine($event));
            }
        }
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

    /**
     * @param string $host
     * @param string $ip
     */
    private function writeFile(string $host, $ip = null)
    {
        $lines = explode(PHP_EOL, file_get_contents($this->filePath));

        $result = [];
        $replaced = false;
        $message = null;
        foreach ($lines as $line) {
            if (false === strpos($line, $host)) {
                if ('' !== trim($line)) {
                    $result[] = $line;
                }

                continue;
            }

            if (!$ip) {
                $message = sprintf('Removed: "%s"', $host);

                continue;
            }

            list($oldIp, $hosts) = explode(' ', $line, 2);

            $result[] = sprintf('%s %s', $ip, $hosts);
            $replaced = true;

            $message = sprintf('Updated: "%s" %s -> %s', $host, $oldIp, $ip);
        }

        if ($ip && !$replaced) {
            $result[] = sprintf('%s %s', $ip, $host);

            $message = sprintf('Added: "%s - %s"', $host, $ip);
        }

        file_put_contents($this->filePath, implode(PHP_EOL, $result).PHP_EOL);

        if ($message) {
            $this->io->success($this->formatLine($message));
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
}
