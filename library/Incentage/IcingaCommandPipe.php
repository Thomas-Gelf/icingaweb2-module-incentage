<?php

namespace Icinga\Module\Incentage;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Monitoring\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Monitoring\Command\Object\ProcessCheckResultCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class IcingaCommandPipe
{
    public function setStatus($status, $output, $host, $service = null)
    {
        $object = $this->getObject($host, $service);
        $status = $this->mapStatus($status, $service === null);

        $cmd = new ProcessCheckResultCommand();
        $cmd->setObject($object)
            ->setOutput($output)
            ->setStatus($status)
        ;

        $transport = new CommandTransport();
        $transport->send($cmd);

        return true;
    }

    public function acknowledge($author, $message, $host, $service = null)
    {
        $object = $this->getObject($host, $service);
        if ($object->acknowledged) {
            return false;
        }

        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObject($object)
            ->setAuthor($author)
            ->setComment($message)
            ->setPersistent(false)
            ->setSticky(false)
            ->setNotify(false)
            ;

        $transport = new CommandTransport();
        $transport->send($cmd);

        return true;
    }

    protected function getObject($hostname, $service)
    {
        if ($service === null) {
            return $this->getHostObject($hostname);
        } else {
            return $this->getServiceObject($hostname, $service);
        }
    }

    protected function getHostObject($hostname)
    {
        $host = new Host(Backend::instance(), $hostname);

        if ($host->fetch() === false) {
            throw new NotFoundError('No such host found: %s', $hostname);
        }

        return $host;
    }

    protected function getServiceObject($hostname, $service)
    {
        $service = new Service(Backend::instance(), $hostname, $service);

        if ($service->fetch() === false) {
            throw new NotFoundError(
                'No service "%s" found on host "%s"',
                $service,
                $hostname
            );
        }

        return $service;
    }

    protected function mapStatus($status, $isHost)
    {
        if ($isHost) {
            return $status === 'OK'
                ? ProcessCheckResultCommand::HOST_UP
                : ProcessCheckResultCommand::HOST_DOWN;
        } else {
            switch ($status) {
                case 'OK':
                    return ProcessCheckResultCommand::SERVICE_OK;
                case 'WARN':
                    return ProcessCheckResultCommand::SERVICE_WARNING;
                default:
                    return ProcessCheckResultCommand::SERVICE_CRITICAL;
            }
        }
    }
}
