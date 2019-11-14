<?php

namespace Icinga\Module\Eventtracker\Scom;

use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\ObjectClassInventory;

class IncentageEventFactory
{
    protected $senderId;

    protected $classInventory;

    public function __construct($senderId, ObjectClassInventory $classInventory)
    {
        $this->senderId = $senderId;
        $this->classInventory = $classInventory;
    }

    public function create($host, $object, $message, $path = null)
    {
        $event = new Event();
        $event->setProperties([
            'host_name'       => $host,
            'object_name'     => $this->objectName($object),
            // 'object_class'    => $this->classInventory->requireClass(substr($obj->entity_base_type, 0, 128)),
            'severity'        => 'alert',
            'priority'        => 'high',
            'message'         => $message,
            'sender_id'       => $this->senderId,
        ]);

        if ($path !== null) {
            $event->set('path', $path);
        }

        return $event;
    }

    protected function objectName($name)
    {
        if ($name === null) {
            return $name;
        } else {
            return \substr($name, 0, 128);
        }
    }
}
