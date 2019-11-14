<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Incentage\IdoHelper;
use ipl\Html\Html;

class ControllerBase extends CompatController
{
    protected $requiresAuthentication = false;

    /** @var IdoHelper */
    protected $ido;

    public function init()
    {
        if (! $this->isSsl()) {
            $this->deny('SSL is required');
        }
        if (! $this->isValidSslCertificate()) {
            $cn = $this->getSslCn();
            if ($cn === null) {
                $this->deny('Got no SSL CN');
            } else {
                $this->deny("SSL CN '$cn' is not allowed to access this resource");
            }
        }
    }

    protected function requireObjectParameter()
    {
        $object = $this->params->get('object');
        if ($object === null) {
            $this->fail(400, "Parameter 'object' is missing");
        }

        return \str_replace('+', ' ', $object);
    }

    /**
     * @return IdoHelper
     * @throws ConfigurationError
     */
    protected function ido()
    {
        if ($this->ido === null) {
            $this->ido = IdoHelper::fromMonitoringModule();
        }

        return $this->ido;
    }


    protected function deny($message)
    {
        $this->fail(403, $message);
    }

    protected function fail($code, $message)
    {
        $this->showError($message);
        try {
            $this->getResponse()->setHttpResponseCode($code);
        } catch (\Zend_Controller_Response_Exception $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $this->finish();
    }

    protected function finish()
    {
        $this->getResponse()->sendResponse();
        exit;
    }

    protected function showError($message)
    {
        echo Html::tag('error', $message);
    }

    protected function isSsl()
    {
        return $this->getRequest()->getServer('HTTPS') === 'on';
    }

    protected function getSslCn()
    {
        return $this->getRequest()->getServer('SSL_CLIENT_S_DN_CN');
    }

    protected function hasSslCn()
    {
        return $this->getSslCn() !== null;
    }

    protected function isValidSslCertificate()
    {
        // TODO: getServerVar('SSL_CLIENT_VERIFY') === 'SUCCESS'? Depends on config
        $allowed = \preg_split(
            '/\s*,\s*/',
            $this->Config('api')->get('ssl', 'allow_cn', ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return \in_array($this->getSslCn(), $allowed, true);
    }
}
