<?php

namespace Icinga\Module\Incentage\Controllers;

use Icinga\Application\Icinga;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Issues;
use Icinga\Module\Eventtracker\ObjectClassInventory;
use Icinga\Module\Eventtracker\Scom\IncentageEventFactory;
use Icinga\Module\Incentage\IdoHelper;
use ipl\Html\Html;

class EventtrackerController extends ControllerBase
{
    /** @var Issues */
    protected $issues;

    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function init()
    {
        parent::init();
        if (! Icinga::app()->getModuleManager()->hasLoaded('eventtracker')) {
            $this->fail(
                404,
                'This URL is not available without the eventtracker being enabled'
            );
        }
    }

    public function issuesAction()
    {
        $object = $this->requireObjectParameter();
        list($host, $service) = IdoHelper::splitHostService($object);
        try {
            $isIcingaObject = $this->ido()->hasObject($object);
            // TODO: host, service separated.
            $result = Html::tag('result')->setSeparator("\r\n");
            $issuesHtml = Html::tag('issues')->setSeparator("\r\n");
            $tags = [
                'status',
                'severity',
                'host_name',
                'object_name',
                'object_class',
                'message',
                'ticket_ref',
            ];
            foreach ($this->issues()->fetchFor($host, $service) as $issue) {
                $issueHtml = Html::tag('issue')->setSeparator("\r\n");
                $issueHtml->add(Html::tag('uuid', $issue->getNiceUuid()));
                foreach ($tags as $tag) {
                    $issueHtml->add(Html::tag($tag, $issue->get($tag)));
                }
                $issuesHtml->add($issueHtml);
            }
            $result->add($issuesHtml);
            if ($isIcingaObject) {
                $icingaState = $this->ido()->getObjectState($object);
                if ($result === false) {
                    //
                } else {
                    $icinga = Html::tag('icinga')->setSeparator("\r\n");
                    foreach ((array) $icingaState as $key => $value) {
                        $icinga->add(Html::tag($key, $value));
                    }
                    $result->add($icinga);
                }
            }
            $result->add(Html::tag('isIcingaObject', $isIcingaObject ? 'true' : 'false'));

            echo $result;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }

    public function issueAction()
    {
        $request = $this->getRequest();
        if (! $request->isPost()) {
            $this->fail(400, 'Only POST is supported, got ' . $request->getMethod());
        }
        $object = $request->getPost('Object');
        $state = $request->getPost('State');
        $message = $request->getPost('Message');
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
            // -> new Event.
            $tag = Html::tag('result')->setSeparator("\r\n");
            if ($this->ido()->hasObject($object)) {
                // not yet
            }

            $db = DbFactory::db();
            $factory = new IncentageEventFactory(
                $this->Config()->get('eventtracker', 'sender_name', 'Incentage'),
                new ObjectClassInventory($db)
            );
            list($host, $service) = IdoHelper::splitHostService($object);
            $event = $factory->create($host, $service, $message, $path);
            $receiver = new EventReceiver($db);
            $issue = $receiver->processEvent($event);
            $tag->add(Html::tag('success', 'true'));
            if ($issue) {
                $tag->add(Html::tag('isIssue', 'true'));
                $tag->add(Html::tag('isNew', $issue->isNew() ? 'true' : 'false'));
            } else {
                $tag->add(Html::tag('isIssue', 'false'));
            }

            echo $tag;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }

    /**
     * @return Issues
     */
    protected function issues()
    {
        if ($this->issues === null) {
            $this->issues = new Issues(DbFactory::db());
        }

        return $this->issues;
    }
}
