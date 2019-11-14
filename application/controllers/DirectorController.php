<?php

namespace Icinga\Module\Incentage\Controllers;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\ImportSource as DirectorImportSource;
use Icinga\Module\Incentage\ProvidedHook\Director\ImportSource;
use ipl\Html\Html;
use SimpleXMLElement;

class DirectorController extends ControllerBase
{
    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function init()
    {
        parent::init();
        if (! Icinga::app()->getModuleManager()->hasLoaded('director')) {
            $this->fail(
                404,
                'This URL is not available without the director being enabled'
            );
        }
    }

    public function importAction()
    {
        $request = $this->getRequest();
        if (! $request->isPost()) {
            $this->fail(400, 'Only POST is supported, got ' . $request->getMethod());
        }

        try {
            $result = Html::tag('result');
            $importSource = $this->requireImportSource();
            $this->importXml($request->getRawBody());
            if ($importSource->runImport()) {
                $result->add([
                    Html::tag('success', 'true'),
                    Html::tag('changes', 'true'),
                    Html::tag('message', 'Modified objects have been imported'),
                ]);
            } else {
                $result->add([
                    Html::tag('success', 'true'),
                    Html::tag('changes', 'false'),
                    Html::tag('message', 'Nothing has been changed, imported data is still up to date'),
                ]);
            }
            echo $result;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(400, $e->getMessage());
        }
    }

    /**
     * @return DirectorImportSource
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireImportSource()
    {
        $importSourceName = $this->Config()->get('director', 'importsource');
        if ($importSourceName === null) {
            $this->fail(401, 'No access to a Director Import Source has been granted');
        }
        $directorResourceName = Config::module('director')->get('db', 'resource');

        return DirectorImportSource::loadByName(
            $importSourceName,
            Db::fromResourceName($directorResourceName)
        );
    }

    protected function importXml($string)
    {
        \libxml_disable_entity_loader(true);
        $xml = \simplexml_load_string($string);
        $lines = [];
        foreach ($xml as $entry) {
            $line = null;
            $lines[] = $this->normalizeSimpleXML($entry);
        }
        ImportSource::$pushedData = $lines;
    }

    protected function normalizeSimpleXML($object)
    {
        $data = $object;
        if (\is_object($data)) {
            $data = (object) \get_object_vars($data);
        }

        if (\is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = $this->normalizeSimpleXml($value);
            }
        }

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalizeSimpleXml($value);
            }
        }

        return $data;
    }
}
