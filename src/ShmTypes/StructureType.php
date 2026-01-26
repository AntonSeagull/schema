<?php

namespace Shm\ShmTypes;

use DateTime;
use Error;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sentry\Util\Arr;
use Sentry\Util\Str;
use Shm\ShmDB\mDB;


use Shm\CachedType\CachedInputObjectType;


use Shm\CachedType\CachedObjectType;

use Shm\Shm;

use Shm\ShmAdmin\Types\VisualGroupType;
use Shm\ShmDB\mDBRedis;

use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\CompositeTypes\ActionType;

use Shm\ShmTypes\Utils\JsonLogicBuilder;

use Shm\ShmUtils\AutoPostfix;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\ProcessLogs;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmUtils;
use stdClass;
use Traversable;

/**
 * Structure type for schema definitions
 * 
 * This class represents a structure type that can contain multiple fields
 * and provides complex validation and normalization capabilities.
 */
class StructureType extends BaseType
{
    public string $type = 'structure';
    public ?string $collection = null;
    private array $pipeline = [];
    private array $insertValues = [];




    public bool $apikey = false;






    public bool $manualSort = false;


    public bool $canUpdate = false;
    public bool $canCreate = false;
    public bool $canDelete = false;


    /**
     * Set API key flag
     * 
     * @param bool $apikey Whether this structure is an API key
     * @return static
     */
    public function apikey(bool $apikey = true): static
    {
        $this->apikey = $apikey;
        return $this;
    }

    /**
     * Set manual sort flag
     * 
     * @param bool $manualSort Whether manual sorting is enabled
     * @return static
     */
    public function manualSort(bool $manualSort = true): static
    {
        $this->manualSort = $manualSort;
        return $this;
    }


    public function translitKeys(): static
    {
        $newItems = [];

        foreach ($this->items as $key => $item) {
            $translitKey = ShmUtils::translitIfCyrillic($key);
            $item->key($translitKey);
            $newItems[$translitKey] = $item;
        }

        $this->items = $newItems;
        return $this;
    }



    public function update(array $update): static
    {


        foreach ($update as $key => $value) {
            $key = ShmUtils::cleanKey($key);
            $this->items[$key] = $value;
        }

        $this->rebuild($this->items);


        return $this;
    }


    public function hideFields(array $hideFields): static
    {
        foreach ($hideFields as $key) {
            if (isset($this->items[$key])) {
                $this->items[$key]->hide = true;
            }
        }

        return $this;
    }

    public function removeField(string $key): static
    {
        if (isset($this->items[$key])) {
            unset($this->items[$key]);
        }
        return $this;
    }

    public function addFieldIfNotExists(string $key, BaseType $type): static
    {

        $key = ShmUtils::cleanKey($key);

        if (!isset($this->items[$key])) {
            $this->items[$key] = $type->key($key);
        } else {
        }

        return $this;
    }

    public function addField(string $key, BaseType $type): static
    {

        $key = ShmUtils::cleanKey($key);

        $this->items[$key] = $type->key($key);


        return $this;
    }


    public function canDelete(bool $canDelete = true): static
    {
        $this->canDelete = $canDelete;
        return $this;
    }

    public $canUpdateCond = null;




    public function canUpdate(JsonLogicBuilder | bool $canUpdate = true): static
    {

        if ($canUpdate instanceof JsonLogicBuilder) {
            $this->canUpdateCond = $canUpdate->build();
            $this->canUpdate = true;
            return $this;
        }


        $this->canUpdate = $canUpdate;
        return $this;
    }
    public function canCreate(bool $canCreate = true): static
    {
        $this->canCreate = $canCreate;
        return $this;
    }

    public array $filterPresets = [];

    public function addFilterPreset(string $key, string $title, array $filter): static
    {
        $this->filterPresets[$key] = [
            'key' => $key,
            'title' => $title,
            'filter' => $filter,
        ];
        return $this;
    }












    public function insertValues(array $insertValues): static
    {
        $this->insertValues = $insertValues;
        return $this;
    }


    public function getInsertValues(): array
    {
        return $this->insertValues;
    }



    public function addPipeline(array $pipeline): static
    {

        if (count($pipeline) > 0) {
            mDB::validatePipeline($pipeline);
        }

        $this->pipeline = [...$this->pipeline, ...$pipeline];



        return $this;
    }

    public function pipeline(array $pipeline): static
    {

        if (count($pipeline) > 0) {
            mDB::validatePipeline($pipeline);
        }

        $this->pipeline = $pipeline;

        return $this;
    }

    public function getPipeline(): array
    {
        mDB::validatePipeline($this->pipeline);


        return $this->pipeline;
    }

    public function collection(string $collection): static
    {

        $this->collection = $collection;
        return $this;
    }


    public function displayValues($values): array | string | null
    {



        $displayValues = [];
        foreach ($this->items as $key => $item) {

            if (isset($values[$key]) && $item->display) {



                $val = $item->displayValues($values[$key]);



                if ($val) {

                    if (is_array($val)) {
                        $displayValues = [
                            ...$displayValues,
                            ...$val
                        ];
                    } else {
                        $displayValues[] = $val;
                    }
                }
            }
        }

        return $displayValues;
    }


    public function fallbackDisplayValues($values): array | string | null
    {



        $displayValues = [];
        foreach ($this->items as $key => $item) {



            if (isset($values[$key])) {


                $val = $item->fallbackDisplayValues($values[$key]);


                if ($val) {

                    if (is_array($val)) {
                        $displayValues = [
                            ...$displayValues,
                            ...$val
                        ];
                    } else {
                        $displayValues[] = $val;
                    }
                }
            }
        }

        return $displayValues;
    }



    private function formatHeatmap(array $mongoResult): array
    {
        $dayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

        $data = [];
        $xAxisSet = [];

        foreach ($mongoResult as $dayEntry) {
            $dayOfWeek = (int) $dayEntry['_id']; // 1–7
            $dayIndex = $dayOfWeek - 1; // 0–6

            foreach ($dayEntry['hours'] as $hourEntry) {
                $hour = (int) $hourEntry['hour'];
                $avgCount = (float) $hourEntry['avgCount'];
                $avgCount = ceil($avgCount); // округляем вверх

                $xAxisSet[$hour] = true;

                // временно сохраняем как есть — заменим потом, когда сформируем индексы
                $data[] = ['hour' => $hour, 'day' => $dayIndex, 'value' => $avgCount];
            }
        }

        // Собираем и форматируем xAxis
        $xAxis = array_keys($xAxisSet);
        sort($xAxis);
        $xAxisFormatted = array_map(fn($h) => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00', $xAxis);
        $xIndexMap = array_flip($xAxisFormatted); // '09:00' => 0

        // Переформатируем data с использованием индексов
        $finalData = [];
        foreach ($data as $item) {
            $hourStr = str_pad((string)$item['hour'], 2, '0', STR_PAD_LEFT) . ':00';
            $xIndex = $xIndexMap[$hourStr] ?? null;
            if ($xIndex !== null) {
                $finalData[] = [$xIndex, $item['day'], $item['value']];
            }
        }

        return [
            'xAxis' => $xAxisFormatted,
            'yAxis' => $dayLabels,
            'data' => $finalData,
        ];
    }









    public function rebuild(array $items): void
    {

        $_items = [];
        foreach ($items as $key => $field) {


            $key = ShmUtils::cleanKey($key);




            ShmUtils::isValidKey($key);

            if (!$field) {
                continue;
            }

            /* if ($key == '_id' && !($field instanceof IDType)) {
                throw new \Exception("Field '_id' must be an instance of IDType.");
            }*/


            if (!$field instanceof BaseType) {

                throw new \Exception("Field '{$key}' must be an instance of BaseType." .
                    json_encode(array_keys($items)) . " - " . gettype($field) . " given.");
            }

            if ($field->type == 'visualGroup') {

                foreach ($field->items as $subKey => $subField) {


                    $subField->key($subKey)->group($field->title ?? "Default", $field->assets['icon'] ?? null);


                    $subField->setParent($this);

                    $_items[$subKey] = $subField;
                }

                continue;
            }




            $field->key($key);

            $field->setParent($this);

            $_items[$key] = $field;
        }




        $this->items =  $_items;
    }


    public function setVisualGroup(string $title = "", $icon = null, array $keys = []): static
    {

        if (!$title || !$keys || count($keys) == 0) {
            return $this;
        }

        foreach ($this->items as $key => $field) {

            if (in_array($key, $keys)) {

                $field->group($title, $icon ?? null);
            }
        }
        return $this;
    }

    /**
     * @param array<string, BaseType> $items
     */
    public function __construct(array $items)
    {

        $this->rebuild($items);
    }





    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {





        if ($addDefaultValues) {


            /* if (!(is_array($value) || $value instanceof Traversable)) {
                //$value = [];
                return null;
            }*/

            foreach ($this->items as $name => $type) {
                if ($processId) {
                    ProcessLogs::addLog($processId, "Normalizing field '{$name}' in StructureType '{$this->key}'");
                }

                if ($type instanceof ActionType) {
                    continue;
                }

                if ($type instanceof IDType) {

                    if (!$type->defaultIsSet && (!isset($value[$name]) || $value[$name] === null)) {

                        continue;
                    }
                }

                $value[$name] =  $type->normalize($value[$name] ?? null, $addDefaultValues, $processId);
            }
        } else {




            if (!(is_array($value) || $value instanceof Traversable)) {
                return null;
            }



            foreach ($this->items as $key => $type) {


                if ($type instanceof ActionType) {
                    continue;
                }

                if (isset($value[$key]) || $type instanceof UUIDType) {

                    $value[$key] = $this->items[$key]->normalize($value[$key] ?? null, $addDefaultValues, $processId);
                }


                if ($type->notNull && !isset($value[$key])) {
                    unset($value[$key]);
                    continue;
                }
            }
        }




        return $value;
    }




    public function removeOtherItems(mixed $value): mixed
    {




        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }


        if (isset($this->items['*'])) {
            return $value;
        }

        $newValue = [];



        foreach ($value as $key => $val) {
            if (isset($this->items[$key]) && !$this->items[$key]->hide) {
                $newValue[$key] = $this->items[$key]->removeOtherItems($val);
            }
        }

        return $newValue;
    }



    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be an object/structure (associative array).");
        }
        foreach ($this->items as $name => $type) {
            try {
                $type->validate($value[$name] ?? null);
            } catch (\Exception $e) {
                $field = $this->title ?? $name;
                throw new \Exception("{$field}.{$name}: " . $e->getMessage());
            }
        }
    }

    private $staticBaseTypeName = null;

    public function staticBaseTypeName(string $name): static
    {
        $this->staticBaseTypeName = ShmUtils::translitIfCyrillic(ShmUtils::onlyLetters($name));
        return $this;
    }



    public function baseTypeName()
    {

        if ($this->staticBaseTypeName) {
            return $this->staticBaseTypeName;
        }




        $typeName = '';

        if ($this->collection) {
            $typeName = Inflect::singularize(ShmUtils::onlyLetters($this->collection));
        } else {

            if (!$this->key) {


                throw new \Exception("baseTypeName -> Key is not set for StructureType. " . implode(', ', array_keys($this->items)));
            }

            $keys = array_keys($this->items);
            $keys = array_map(fn($type) => $type === '*' ? 'mixed' : $type, $keys);
            $typeName = Inflect::singularize(ShmUtils::onlyLetters($this->key)) .  AutoPostfix::get($keys);
        }

        $baseTypePrefix = null;

        return $baseTypePrefix ? $baseTypePrefix . $typeName : $typeName;
    }





    public function typeName()
    {
        return $this->baseTypeName() . 'Type';
    }

    public function inputTypeName()
    {
        return $this->baseTypeName() . 'Input';
    }

    public function filterTypeName()
    {
        return $this->baseTypeName() . 'FilterInput';
    }


    public function deepFindItemByKey(string $key): ?StructureType
    {


        foreach ($this->items as $item) {


            if ($item->key === $key) {
                return $item;
            }


            if ($item instanceof StructureType) {

                if ($item instanceof StructureType) {
                    $val =  $item->deepFindItemByKey($key);
                    if ($val) {
                        return $val;
                    }
                }
            }
        }



        return null;
    }




    public function findItemByCollection(string $collection): ?StructureType
    {

        $collections = $this->getAllCollections();

        if (isset($collections[$collection])) {
            return $collections[$collection];
        }



        foreach ($this->items as $item) {


            if ($item instanceof IDType || $item instanceof IDsType) {

                $document = $item->getDocument();

                if ($document && $document->collection === $collection) {
                    return $document;
                }
            }


            if ($item instanceof StructureType) {
                if ($item->collection === $collection)
                    return $item;



                if ($item instanceof StructureType) {
                    $val =  $item->findItemByCollection($collection);
                    if ($val) {
                        return $val;
                    }
                }
            }
        }



        return null;
    }

    public function haveItemByKey(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function findItemByKey(string $key): ?BaseType
    {

        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        return null;
    }

    public function findItemsByKey(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->findItemByKey($key);
        }

        if (count($result) === 0) {

            throw new \Exception("findItemsByKey -> Keys not found: " . implode(', ', $keys));
        }
        return $result;
    }


    public function findItemByType(string | BaseType $type): ?BaseType
    {

        if ($type instanceof BaseType) {
            $type = $type->type;
        }

        foreach ($this->items as $item) {
            if ($item->type === $type) {
                return $item;
            }
        }


        return null;
    }





    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;

        foreach ($this->items as $key => $field) {

            $field->fullCleanDefault();
        }

        return $this;
    }











    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {



        if (!is_array($filter) && !is_object($filter)) {
            return null;
        }


        if ($absolutePath !== null) {
            $absolutePath = [...$absolutePath, $this->key];
        } else {
            $absolutePath = [];
        }
        $pipeline = [];

        foreach ($filter as $key => $value) {

            if ($value === null) {
                continue;
            }
            if (!isset($this->items[$key])) {
                continue;
            }




            $type = $this->items[$key];


            $pipelineItem = $type->filterToPipeline($value,  $absolutePath);

            $pipeline = [...$pipeline, ...($pipelineItem ?? [])];
        }


        return  $pipeline;
    }


    public ?BaseType $filterType = null;

    public function filterType($safeMode = false): ?BaseType
    {


        if ($this->type == "structure" && $this->filterType) {
            return $this->filterType;
        }

        $fields = [];

        foreach ($this->items as $key => $field) {

            $input = $field->filterType($safeMode);
            if ($input) {
                $fields[$key] = $input;
            }
        }
        if (count($fields) == 0) {
            return null;
        }
        $itemTypeFilter = Shm::structure($fields)->editable()->title($this->title)->staticBaseTypeName($this->key . 'FilterArgs');

        if ($this->type == "structure") {
            $this->filterType =  $itemTypeFilter;
        }

        return  $itemTypeFilter;
    }





    public function tsType(): TSType
    {
        $TSType = new TSType();




        $value = [];

        foreach ($this->items as $key => $item) {
            $separate = $item->nullable ? '?: ' : ': ';

            if ($item->hide) {
                continue;
            }

            if ($item instanceof ActionType) {
                continue;
            }



            if ($key == "*") {
                $key = '[key: string]';
                $separate = ': ';
            }

            if ($item instanceof StaticType) {

                $value[] = $key .  $separate . $item->getStaticValueTS();
                continue;
            }


            if ($item instanceof SelfRefType) {

                $_type = $item->resolveType();

                if ($_type instanceof StructureType) {

                    $value[] = $key .  $separate . $_type->typeName();
                } else {
                    throw new \Exception("SelfRefType must resolve to a StructureType, got " . get_class($_type));
                }
                continue;
            }

            if ($item->deprecated) {

                $prefix = "/**\n * @deprecated " . $item->deprecated . "\n */\n";
            } else {
                $prefix = "";
            }

            $value[] = $prefix . $key .  $separate . $item->tsType()->getTsTypeName();
        }

        $TSType = new TSType($this->typeName(), '{\n' . implode(',\n', $value) . '\n}');




        return $TSType;
    }


    public function tsInputType(): TSType
    {
        $TSType = new TSType();



        $value = [];

        foreach ($this->items as $key => $item) {
            if (!$item->editable) {
                continue;
            }

            if ($item instanceof StaticType) {
                continue;
            }


            $separate = $item->nullable ? '?: ' : ': ';

            if ($key == "*") {
                $key = '[key: string]';
                $separate = ': ';
            }


            $value[] = $key .  $separate . $item->tsInputType()->getTsTypeName();
        }

        $TSType = new TSType($this->inputTypeName(), '{\n' . implode(',\n', $value) . '\n}');




        return $TSType;
    }









    public function updateOne(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {



        $filter = mDB::replaceStringToObjectIds($filter);

        $arrayFields = [];

        foreach (['$pull', '$addToSet', '$push'] as $op) {
            if (isset($update[$op]) && is_array($update[$op])) {
                foreach ($update[$op] as $field => $value) {
                    $arrayFields[$field] = true;
                }
            }
        }


        if (count($arrayFields) > 0) {
            $orConditions = [];

            foreach (array_keys($arrayFields) as $field) {
                $orConditions[] = [$field => ['$exists' => false]];
                $orConditions[] = [$field => ['$not' => ['$type' => 'array']]];
            }

            if (count($orConditions) > 0) {
                // Найти документ и привести нужные поля к массивам
                $setData = [];
                foreach (array_keys($arrayFields) as $field) {
                    $setData[$field] = [];
                }

                $finalFilter = $filter;

                if (isset($finalFilter['$or'])) {
                    $finalFilter = [
                        '$and' => [
                            $filter,
                            ['$or' => $orConditions]
                        ]
                    ];
                } else {
                    $finalFilter['$or'] = $orConditions;
                }




                mDB::_collection($this->collection)->updateOne(

                    $finalFilter,
                    [
                        '$set' => $setData
                    ]
                );
            }
        }


        if (isset($update['$set'])) {

            if (array_key_exists("_id", (array) $update['$set'])) {
                unset($update['$set']['_id']);
            }
            $update['$set']["_needRecalculateSearch"] = true;
            $update['$set'] = $this->normalize($update['$set']);
        }

        if (isset($update['$unset'])) {
            if (array_key_exists("_id", (array) $update['$unset'])) {
                unset($update['$unset']['_id']);
            }
            $update['$set']["_needRecalculateSearch"] = true;
            // $update['$unset'] is already set above
        }

        $result =  mDB::collection($this->collection)->updateOne($filter, $update, [
            ...$options,
        ]);




        return    $result;
    }

    public function _updateOne(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {


        $update =  mDB::collection($this->collection)->updateOne($filter, $update, [
            ...$options,
        ]);





        return $update;
    }




    public function updateMany(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {

        $filter = mDB::replaceStringToObjectIds($filter);


        $arrayFields = [];

        foreach (['$pull', '$addToSet', '$push'] as $op) {
            if (isset($update[$op]) && is_array($update[$op])) {
                foreach ($update[$op] as $field => $value) {
                    $arrayFields[$field] = true;
                }
            }
        }

        if (count($arrayFields) > 0) {
            $orConditions = [];

            foreach (array_keys($arrayFields) as $field) {
                $orConditions[] = [$field => ['$exists' => false]];
                $orConditions[] = [$field => ['$not' => ['$type' => 'array']]];
            }

            if (count($orConditions) > 0) {
                // Найти документ и привести нужные поля к массивам
                $setData = [];
                foreach (array_keys($arrayFields) as $field) {
                    $setData[$field] = [];
                }

                $finalFilter = $filter;

                if (isset($finalFilter['$or'])) {
                    $finalFilter = [
                        '$and' => [
                            $filter,
                            ['$or' => $orConditions]
                        ]
                    ];
                } else {
                    $finalFilter['$or'] = $orConditions;
                }


                mDB::_collection($this->collection)->updateMany(

                    $finalFilter,
                    [
                        '$set' => $setData
                    ]
                );
            }
        }


        if (isset($update['$set'])) {

            if (array_key_exists("_id", (array) $update['$set'])) {
                unset($update['$set']['_id']);
            }
            $update['$set']["_needRecalculateSearch"] = true;
            $update['$set'] = $this->normalize($update['$set']);
        }

        if (isset($update['$unset'])) {
            if (array_key_exists("_id", (array) $update['$unset'])) {
                unset($update['$unset']['_id']);
            }
            $update['$set']["_needRecalculateSearch"] = true;
            // $update['$unset'] is already set above
        }

        $result =  mDB::collection($this->collection)->updateMany($filter, $update, [
            ...$options,
        ]);






        return   $result;
    }

    public function deleteOne(array $filter = [], array $options = [])
    {
        $filter = mDB::replaceStringToObjectIds($filter);

        return   mDB::collection($this->collection)->deleteOne($filter, $options);
    }

    public function deleteMany(array $filter = [], array $options = [])
    {
        $filter = mDB::replaceStringToObjectIds($filter);

        return   mDB::collection($this->collection)->deleteMany($filter, $options);
    }







    public function find(array $filter = [], array $options = [])
    {
        $filter = mDB::replaceStringToObjectIds($filter);
        $pipeline = [];

        if (count($filter) > 0) {

            $pipeline = [
                [
                    '$match' => $filter
                ]
            ];
        }

        $pipeline = [
            ...$pipeline,
            ...$this->getPipeline(),

        ];

        if (isset($options['sort']) && is_array($options['sort'])) {
            $pipeline[] = [
                '$sort' => $options['sort']
            ];
        } else {

            if ($this->manualSort) {

                $pipeline[] = [
                    '$sort' => ["_sortWeight" => -1]
                ];
            }
        }
        if (isset($options['limit']) && is_int($options['limit'])) {
            $pipeline[] = [
                '$limit' => $options['limit']
            ];
        }
        if (isset($options['skip']) && is_int($options['skip'])) {
            $pipeline[] = [
                '$skip' => $options['skip']
            ];
        }
        if (isset($options['projection']) && is_array($options['projection'])) {
            $pipeline[] = [
                '$project' => $options['projection']
            ];
        }

        return   mDB::collection($this->collection)->aggregate($pipeline)->toArray();
    }

    public function distinct(string $field, array $filter = [])
    {

        return  mDB::collection($this->collection)->distinct($field, $filter);
    }

    public function count(array $filter = [], array $options = []): int
    {
        return mDB::collection($this->collection)->countDocuments($filter, $options);
    }


    public function findOne(array $filter = [], array $options = [])
    {

        $filter = mDB::replaceStringToObjectIds($filter);

        $pipeline = [];

        if (count($filter) > 0) {
            $pipeline[] = [
                '$match' => $filter
            ];
        }


        $pipeline = [
            ...$pipeline,
            ...$this->getPipeline(),
            [
                '$limit' => 1
            ]
        ];

        return   mDB::collection($this->collection)->aggregate($pipeline)->toArray()[0] ?? null;
    }




    public function insertOne($document, array $options = []): \MongoDB\InsertOneResult
    {


        $document = [
            ...$document,
            ...$this->getInsertValues()
        ];


        $document = $this->normalize($document, true);





        return mDB::collection($this->collection)->insertOne($document, $options);
    }





    public function insertMany(array $documents, array $options = []): \MongoDB\InsertManyResult
    {

        foreach ($documents as &$document) {
            $document = [
                ...$document,
                ...$this->getInsertValues()
            ];
        }

        $documents = Shm::arrayOf($this)->normalize($documents, true);

        return mDB::collection($this->collection)->insertMany($documents, $options);
    }


    public function moveRow($_id, $aboveId = null, $belowId = null)
    {

        $currentId = $_id;
        $aboveWeight = null;
        $belowWeight = null;

        if ($aboveId !== null) {
            $aboveWeight = $this->findOne([
                "_id" => mDB::id($aboveId)
            ])->_sortWeight ?? null;
        }

        if ($belowId !== null) {
            $belowWeight = $this->findOne([
                "_id" => mDB::id($belowId)
            ])->_sortWeight ?? null;
        }
        $newWeight = null;

        if ($aboveWeight &&  $belowWeight) {
            $newWeight = ($aboveWeight + $belowWeight) / 2;
        } else if ($aboveWeight) {
            $newWeight = $aboveWeight / 2;
        } else if ($belowWeight) {
            $newWeight = $belowWeight + 1;
        }

        //Округляем в вверх
        $newWeight = ceil($newWeight);

        if ($newWeight) {
            $this->updateOne([
                "_id" => mDB::id($currentId)
            ], [
                '$set' => [
                    '_sortWeight' => $newWeight
                ]
            ]);
            return true;
        } else {
            return false;
        }
    }








    public  function aggregate(array $pipeline = [])
    {


        $pipeline = array_merge([
            [
                '$match' => [
                    "deleted_at" => ['$exists' => false],
                ]
            ],
        ], $pipeline);




        return mDB::collection($this->collection)->aggregate($pipeline);
    }



    public function hideNotInTable(): static
    {

        if ($this->type == "structure") {


            foreach ($this->items as $key => $item) {

                $item->hideNotInTable();
            }
        } else {
            if (!$this->inTable) {
                $this->hide = true;
            }
        }
        return $this;
    }







    // Именованные константы для автодополнения и переиспользования
    public const EVENT_AFTER_REGISTER = 'after_register';
    public const EVENT_AFTER_LOGIN    = 'after_login';


    /** Белый список событий, доступных в системе */
    private const ALLOWED_EVENTS = [
        self::EVENT_AFTER_REGISTER,
        self::EVENT_AFTER_LOGIN,
    ];

    /** @var array<string, list<callable(string):void>> */
    private array $eventHandlers = [];

    private function normalizeEvent(string $event): string
    {
        $event = trim($event);
        if ($event === '') {
            throw new \InvalidArgumentException('Event name cannot be empty');
        }

        if (!in_array($event, self::ALLOWED_EVENTS, true)) {
            throw new \InvalidArgumentException(
                'Unknown event: ' . $event . '. Allowed: ' . implode(', ', self::ALLOWED_EVENTS)
            );
        }
        return $event;
    }


    /**
     * Зарегистрировать обработчик события.
     * @param  $event
     * @param callable(string $_id): void $handler
     */
    public function addEvent(string $event, callable $handler): static
    {
        $event = $this->normalizeEvent($event);
        $this->eventHandlers[$event] ??= [];
        $this->eventHandlers[$event][] = $handler;
        return $this;
    }

    /**
     * Вызвать обработчики события.
     * @param  $event
     */
    public function callEvent(string $event, string $_id): void
    {
        $event = $this->normalizeEvent($event);
        $handlers = $this->eventHandlers[$event] ?? [];
        if (!$handlers) return;

        foreach (array_values($handlers) as $handler) {
            try {
                $handler($_id);
            } catch (\Throwable $e) {
                ShmInit::sendOnError($e);
            }
        }
    }

    /** Получить список разрешённых событий*/
    public static function allowedEvents(): array
    {
        return self::ALLOWED_EVENTS;
    }
}
