<?php

namespace Shm\ShmUtils\ShmDoctor;

use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmUtils\ShmDoctor\Utils\StructureHelper;

class IndexManager
{
    /**
     * Ensure MongoDB indexes are created for all structures
     */
    public static function ensureIndexes(): void
    {
        $isCli = Cmd::cli();
        $structures = StructureHelper::getStructures();

        foreach ($structures as $structure) {
            $indexes = $structure::structure()->createIndex();

            if (count($indexes) > 0) {
                $collection = mDB::_collection($structure->collection);

                foreach ($indexes as $indexKey => $type) {
                    $hasIndex = false;

                    foreach ($collection->listIndexes() as $index) {
                        if (isset($index['key'][$indexKey]) && $index['key'][$indexKey] === $type) {
                            $hasIndex = true;
                            break;
                        }
                    }

                    if (!$hasIndex) {
                        $collection->createIndex([$indexKey => $type]);
                        if ($isCli) {
                            echo "Index created: {$indexKey} => {$type} in {$structure->collection}" . PHP_EOL;
                        }
                    }
                }
            }

            // Create _sortWeight index if manualSort is enabled
            if ($structure->collection && $structure::structure()->manualSort) {
                if ($isCli) {
                    echo "Creating index for _sortWeight in {$structure->collection}" . PHP_EOL;
                }

                $collection = mDB::_collection($structure->collection);
                $indexes = $collection->listIndexes();

                $findIndex = false;
                foreach ($indexes as $index) {
                    if (isset($index['key']['_sortWeight'])) {
                        $findIndex = true;
                        break;
                    }
                }

                if (!$findIndex) {
                    $collection->createIndex(['_sortWeight' => 1]);
                }
            }
        }
    }
}
