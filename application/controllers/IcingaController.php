<?php

namespace Icinga\Module\Incentage\Controllers;

use Icinga\Module\Incentage\IcingaCommandPipe;
use Icinga\Module\Incentage\IdoHelper;
use ipl\Html\Html;

class IcingaController extends ControllerBase
{
    public function statusAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->postStatus();
            return;
        }
        $object = $this->requireObjectParameter();
        try {
            $result = $this->ido()->getObjectState($object);
            if ($result === false) {
                $this->fail(404, "No such object: $object");
            } else {
                $tag = Html::tag('result')->setSeparator("\r\n");
                foreach ((array) $result as $key => $value) {
                    $tag->add(Html::tag($key, $value));
                }
                echo $tag;
                $this->finish();
            }
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }

    protected function postStatus()
    {
        $request = $this->getRequest();
        $object = $request->getPost('Object');
        $state = $request->getPost('State');
        $message = $request->getPost('Message');
        list($host, $service) = IdoHelper::splitHostService($object);
        $path = $request->getPost('Path');
        if ($object === null) {
            $this->fail(400, "Parameter 'Object' is missing");
        }
        if ($state === null) {
            $this->fail(400, "Parameter 'State' is missing");
        }
        if ($message === null) {
            $this->fail(400, "Parameter 'Message' is missing");
        }
        try {
            $result = $this->ido()->hasObject($object);
            $tag = Html::tag('result')->setSeparator("\r\n");
            $cmd = new IcingaCommandPipe();
            $cmd->setStatus($state, $message, $host, $service);
            if ($result === true) {
                $tag->add(Html::tag('success', 'true'));
            } else {
                $tag->add(Html::tag('success', 'false'));
                $tag->add(Html::tag('reason', 'Object not found'));
            }
            echo $tag;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }
}
