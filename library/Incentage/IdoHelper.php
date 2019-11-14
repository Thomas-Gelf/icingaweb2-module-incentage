<?php

namespace Icinga\Module\Incentage;

use Icinga\Data\ResourceFactory;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Zend_Db_Adapter_Abstract as DbAdapter;

/**
 * Class IdoDb
 *
 * Small IDO abstraction layer
 */
class IdoHelper
{
    /** @var DbAdapter */
    protected $db;

    /**
     * IdoDb constructor.
     * @param DbAdapter $db
     */
    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return DbAdapter
     */
    public function getDb()
    {
        return $this->db;
    }

    public function hasObject($name)
    {
        if (\strpos($name, '!') === false) {
            return $this->hasHost($name);
        } else {
            list($host, $service) = \preg_split('/!/', $name, 2);
            return $this->hasService($host, $service);
        }
    }

    public static function splitHostService($object)
    {
        if (\strpos($object, '!') === false) {
            return [$object, null];
        } else {
            list($host, $service) = \preg_split('/!/', $object, 2);

            return [$host, $service];
        }
    }

    public function hasHost($host)
    {
        $db = $this->db;
        $select = $db->select()
            ->from(['o' => 'icinga_objects'], 'o.name1')
            ->where('o.name1 = ?', $host)
            ->where('o.objecttype_id = 1')
            ->where('o.is_active = 1');

        $result = $db->fetchOne($select);

        return $result === $host;
    }

    public function hasService($host, $service)
    {
        $db = $this->db;
        $select = $db->select()
            ->from(['o' => 'icinga_objects'], 'o.name2')
            ->where('o.name1 = ?', $host)
            ->where('o.name2 = ?', $service)
            ->where('o.objecttype_id = 2')
            ->where('o.is_active = 1');

        $result = $db->fetchOne($select);

        return $result === $service;
    }

    public function getObjectState($name)
    {
        if (\strpos($name, '!') === false) {
            return $this->getHostState($name);
        } else {
            list($host, $service) = \preg_split('/!/', $name, 2);
            return $this->getServiceState($host, $service);
        }
    }

    public function getHostState($host)
    {
        $db = $this->db;
        $select = $db->select()->from(['o' => 'icinga_objects'], [
            'host'         => 'o.name1',
            'state'        => "(CASE hs.current_state WHEN 0 THEN 'up'"
                . " WHEN 1 THEN 'down' WHEN 2 THEN 'unreachable' ELSE 'pending' END)",
            'in_downtime'  => "(CASE WHEN hs.scheduled_downtime_depth > 0 THEN 'yes' ELSE 'no' END)",
            'acknowledged' => "(CASE WHEN hs.problem_has_been_acknowledged = 0 THEN 'no' ELSE 'yes' END)",
            'output'       => 'hs.output',
        ])->join(
            ['hs' => 'icinga_hoststatus'],
            'o.object_id = hs.host_object_id AND o.is_active = 1',
            []
        )->where('o.name1 = ?', $host);

        $result = $db->fetchRow($select);
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    public function getServiceState($host, $service)
    {
        $db = $this->db;
        $select = $db->select()->from(['o' => 'icinga_objects'], [
            'host'         => 'o.name1',
            'service'      => 'o.name2',
            'state'        => "(CASE ss.current_state WHEN 0 THEN 'ok' WHEN 1 THEN 'warning'"
                . " WHEN 2 THEN 'critical' WHEN 3 THEN 'unknown' ELSE 'pending' END)",
            'in_downtime'  => "(CASE WHEN ss.scheduled_downtime_depth > 0 THEN 'yes' ELSE 'no' END)",
            'acknowledged' => "(CASE WHEN ss.problem_has_been_acknowledged = 0 THEN 'no' ELSE 'yes' END)",
            'output'       => 'ss.output',
        ])->join(
            ['ss' => 'icinga_servicestatus'],
            'o.object_id = ss.service_object_id AND o.is_active = 1',
            []
        )->where('o.name1 = ?', $host)->where('o.name2 = ?', $service);

        $result = $db->fetchRow($select);
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Instantiate with a given Icinga Web 2 resource name
     *
     * @param $name
     * @return static
     */
    public static function fromResourceName($name)
    {
        return new static(
            ResourceFactory::create($name)->getDbAdapter()
        );
    }

    /**
     * Borrow the database connection from the monitoring module
     *
     * @return static
     * @throws \Icinga\Exception\ConfigurationError
     */
    public static function fromMonitoringModule()
    {
        return new static(
            MonitoringBackend::instance()->getResource()->getDbAdapter()
        );
    }
}
