<?php

namespace Shm\ShmUtils;

use Shm\Shm;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;

class DisplayValuePrepare
{

    public static function prepareById(StructureType $structure, $_id)
    {

        $item = mDB::collection($structure->collection)->findOne(['_id' => mDB::id($_id)]);

        if (!$item) {
            return null;
        }

        return self::prepare($structure, [$item])[0] ?? null;
    }

    public static function prepare(StructureType $structure, array $data = [])
    {







        $bulkUpdate = [];

        $result = [];

        foreach ($data as $item) {

            $_displayValue = $item['_displayValue'] ?? null;
            $_displayValueUpdated_at = $item['_displayValueUpdated_at'] ?? null;


            $OneDay = 60 * 60 * 24;

            if ($_displayValue  && $_displayValueUpdated_at && $_displayValueUpdated_at > time() - $OneDay) {

                $result[] = [
                    '_id' => (string)$item['_id'],
                    'displayValue' => $item['_displayValue'],
                ];
                continue;
            }

            $displayValues = $structure->displayValues($item);



            $value = null;
            if (is_array($displayValues) && count($displayValues) > 0) {
                $value = implode(', ', $displayValues);
            } else {


                $result[] = [
                    '_id' => (string)$item['_id'],
                    'displayValue' => (string)$item['_id'],
                ];

                continue;



                $displayValues = $structure->fallbackDisplayValues($item);


                if (is_array($displayValues) && count($displayValues) > 0) {
                    $value =  implode(', ', $displayValues);
                }
            }

            if ($value) {
                $bulkUpdate[] = [
                    'updateOne' => [
                        [
                            '_id' => $item['_id'],
                        ],
                        [
                            '$set' => [
                                '_displayValue' => $value,
                                '_displayValueUpdated_at' => time(),
                            ],
                        ],
                    ],
                ];
            }

            $result[] = [
                '_id' => (string)$item['_id'],
                'displayValue' => $value,
            ];
        }


        if (count($bulkUpdate) > 0) {
            mDB::_collection($structure->collection)->bulkWrite($bulkUpdate);
        }

        return $result;
    }
}
