<?php

namespace Icinga\Module\Director\Import\PurgeStrategy;

use dipl\Html\Util;
use Icinga\Application\Benchmark;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\Objects\ImportSource;

class ImportRunBasedPurgeStrategy extends PurgeStrategy
{
    public function listObjectsToPurge()
    {
        $remove = array();

        foreach ($this->getSyncRule()->fetchInvolvedImportSources() as $source) {
            $remove += $this->checkImportSource($source);
        }

        return $remove;
    }

    protected function getLastSync()
    {
        return strtotime($this->getSyncRule()->getLastSyncTimestamp());
    }

    // TODO: NAMING!
    protected function checkImportSource(ImportSource $source)
    {
        if (null === ($lastSync = $this->getLastSync())) {
            // No last sync, nothing to purge
            return array();
        }

        $runA = $source->fetchLastRunBefore($lastSync);
        if ($runA === null) {
            // Nothing to purge for this source
            return array();
        }

        $runB = $source->fetchLastRun();
        if ($runA->rowset_checksum === $runB->rowset_checksum) {
            // Same source data, nothing to purge
            return array();
        }

        return $this->listKeysRemovedBetween($runA, $runB);
    }

    public function listKeysRemovedBetween(ImportRun $runA, ImportRun $runB)
    {
        Benchmark::measure('Purge: Begin finding keys');

        $rule = $this->getSyncRule();
        $db = $rule->getDb();

        $selectA = $runA->prepareImportedObjectQuery();
        $selectB = $runB->prepareImportedObjectQuery();

        $query = $db->select()->from(
            array('a' => $selectA),
            'a.object_name'
        )->joinLeft(
            array('b' => $selectB),
            'a.object_name = b.object_name',
            array()
        )->where('b.object_name IS NULL');



        echo $selectA;
        $a = $db->fetchAll($selectA);
        Benchmark::measure('Query A: ' . count($a));

        echo $selectB;
        $b = $db->fetchAll($selectB);
        Benchmark::measure('Query B: '. count($b));

        echo $query;
        $all = $db->fetchAll($query);
        Benchmark::measure('Query Sub-Select: ' . count($all));

        echo Benchmark::dump();

        echo Util::wantHtml($db->fetchAll('EXPLAIN ' . $query))->render();

        exit;

        Benchmark::measure('Purge: Done prepare finding keys');

        $result = $db->fetchCol($query);

        Benchmark::measure('Purge: Done fetch finding keys');

        if (empty($result)) {
            return array();
        }

        if ($rule->hasCombinedKey()) {
            $pattern = $rule->getSourceKeyPattern();
            $columns = SyncUtils::getRootVariables(
                SyncUtils::extractVariableNames($pattern)
            );

            $rows = $runA->fetchRows($columns, null, $result);
            $result = array();
            foreach ($rows as $row) {
                $result[] = SyncUtils::fillVariables($pattern, $row);
            }
        }

        if (empty($result)) {
            return array();
        }

        return array_combine($result, $result);
    }
}
