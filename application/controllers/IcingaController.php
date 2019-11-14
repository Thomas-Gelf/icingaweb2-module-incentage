<?php

namespace Icinga\Module\Eventtracker\Controllers;

use ipl\Html\Html;

class IcingaController extends ControllerBase
{
    public function statusAction()
    {
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
}
