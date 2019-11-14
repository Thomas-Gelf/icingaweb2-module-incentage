<?php

namespace Icinga\Module\Incentage\ProvidedHook\Director;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Hook\ImportSourceHook;
use RuntimeException;

class ImportSource extends ImportSourceHook
{
    public static $pushedData;

    protected $db;

    public function getName()
    {
        return 'Incentage On-Demand Import';
    }

    /**
     * @return object[]
     * @throws ConfigurationError
     * @throws IcingaException
     */
    public function fetchData()
    {
        if (static::$pushedData === null) {
            throw new RuntimeException('This Import Source can only be triggered via POST');
        }

        return static::$pushedData;
    }

    /**
     * @return array
     * @throws ConfigurationError
     * @throws IcingaException
     */
    public function listColumns()
    {
        return ['Name', 'Path'];
    }
}
