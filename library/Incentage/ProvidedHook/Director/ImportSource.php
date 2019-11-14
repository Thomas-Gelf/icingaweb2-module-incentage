<?php

namespace Icinga\Module\Incentage\ProvidedHook\Director;

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
     */
    public function listColumns()
    {
        return ['Name', 'Path'];
    }
}
