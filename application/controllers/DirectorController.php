<?php

namespace Icinga\Module\Incentage\Controllers;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\ImportSource as DirectorImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Incentage\ProvidedHook\Director\ImportSource;
use ipl\Html\Html;

class DirectorController extends ControllerBase
{
    /** @var Db */
    protected $directorDb;

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
            $result = Html::tag('result')->setSeparator("\r\n");;
            $importSource = $this->requireImportSource();
            if (null === ($body = $this->getRequest()->getPost('body'))) {
                $body = $request->getRawBody();
            }
            $this->importXml($body);
            $sync = $this->eventuallyLoadSync($importSource);
            if ($importSource->runImport()) {
                $result->add([
                    Html::tag('success', 'true'),
                    Html::tag('importedChanges', 'true'),
                    Html::tag('message', 'Modified objects have been imported'),
                ]);
            } else {
                $result->add([
                    Html::tag('success', 'true'),
                    Html::tag('importedChanges', 'false'),
                    Html::tag('message', 'Nothing has been changed, imported data is still up to date'),
                ]);
            }

            if ($sync) {
                $result->add(Html::tag('syncRuleFound', 'true'));
                $mods = $this->getExpectedModificationCounts($sync);
                if ($sync->applyChanges()) {
                    $result->add(Html::tag('synchronizedChanges', 'true'));
                    foreach ($mods as $key => $value) {
                        $result->add(Html::tag('objects' . \ucfirst($key), $value));
                    }
                } else {
                    $result->add(Html::tag('synchronizedChanges', 'false'));
                }
            } else {
                $result->add(Html::tag('syncRuleFound', 'false'));
            }
            echo $result;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(400, $e->getMessage());
        }
    }

    protected function getExpectedModificationCounts(SyncRule $rule)
    {
        $modifications = $rule->getExpectedModifications();

        $create = 0;
        $modify = 0;
        $delete = 0;

        /** @var \Icinga\Module\Director\Objects\IcingaObject $object */
        foreach ($modifications as $object) {
            if ($object->hasBeenLoadedFromDb()) {
                if ($object->shouldBeRemoved()) {
                    $delete++;
                } else {
                    $modify++;
                }
            } else {
                $create++;
            }
        }

        return (object) [
            'created' => $create,
            'modified' => $modify,
            'deleted' => $delete,
        ];
    }

    /**
     * @param DirectorImportSource $importSource
     * @return SyncRule|null
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function eventuallyLoadSync(DirectorImportSource $importSource)
    {
        $ddb = $this->requireDirectorDb();
        $db = $ddb->getDbAdapter();
        $ids = $db->fetchCol(
            $db->select()->distinct()->from(
                ['sr' => 'sync_rule'],
                'sr.id'
            )->join(
                ['sp' => 'sync_property'],
                'sr.id = sp.rule_id',
                []
            )->where('sp.source_id = ?', $importSource->get('id'))
        );

        if (\count($ids) === 1) {
            $id = array_shift($ids);
            return SyncRule::loadWithAutoIncId($id, $ddb);
        } else {
            return null;
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

        return DirectorImportSource::loadByName(
            $importSourceName,
            $this->requireDirectorDb()
        );
    }

    protected function requireDirectorDb()
    {
        if ($this->directorDb === null) {
            $directorResourceName = Config::module('director')->get('db', 'resource');

            $this->directorDb = Db::fromResourceName($directorResourceName);
        }

        return $this->directorDb;
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
