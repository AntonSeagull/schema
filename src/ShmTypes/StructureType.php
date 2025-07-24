<?php

namespace Shm\ShmTypes;

use Error;

use Sentry\Util\Str;
use Shm\ShmDB\mDB;


use Shm\CachedType\CachedInputObjectType;


use Shm\CachedType\CachedObjectType;

use Shm\Shm;
use Shm\ShmAdmin\Types\VisualGroupType;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\SupportTypes\StageType;
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

class StructureType extends BaseType
{
    public string $type = 'structure';


    public $collection = null;

    private $pipeline = [];

    private $insertValues = [];







    public bool $manualSort = false;


    public bool $canUpdate = false;
    public bool $canCreate = false;
    public bool $canDelete = false;


    public function manualSort(bool $manualSort = true): self
    {
        $this->manualSort = $manualSort;
        return $this;
    }




    public function update(array $update): self
    {


        foreach ($update as $key => $value) {
            $this->items[$key] = $value;
        }

        $this->rebuild($this->items);


        return $this;
    }


    public function hideFields(array $hideFields): self
    {
        foreach ($hideFields as $key) {
            if (isset($this->items[$key])) {
                $this->items[$key]->hide = true;
            }
        }

        return $this;
    }

    public function addField(string $key, BaseType $type): self
    {



        $this->items[$key] = $type;


        return $this;
    }


    public function canDelete(bool $canDelete = true): self
    {
        $this->canDelete = $canDelete;
        return $this;
    }

    public $canUpdateCond = null;




    public function canUpdate(JsonLogicBuilder | bool $canUpdate = true): self
    {

        if ($canUpdate instanceof JsonLogicBuilder) {
            $this->canUpdateCond = $canUpdate->build();
            $this->canUpdate = true;
            return $this;
        }


        $this->canUpdate = $canUpdate;
        return $this;
    }
    public function canCreate(bool $canCreate = true): self
    {
        $this->canCreate = $canCreate;
        return $this;
    }


    private null | StructureType $stages = null;

    public null | StructureType $buttonActions = null;



    public $publicStages = [];

    public function getStages(): ?StructureType
    {
        return $this->stages;
    }


    public function buttonActions(StructureType $buttonActions): self
    {
        foreach ($buttonActions->items as $key => $action) {
            if (!($action instanceof ComputedType)) {
                throw new \Exception("Button action '{$key}' must be an instance of ComputedType.");
            }
        }

        $this->buttonActions = $buttonActions;
        return $this;
    }

    public function stages(StructureType $stages): self
    {

        foreach ($stages->items as $key => $stage) {
            if (!($stage instanceof StageType)) {
                throw new \Exception("Stage '{$key}' must be an instance of StageType.");
            }

            $this->publicStages[$key] = $stage->title ?? $stage->key;
        }

        $this->stages = $stages;
        return $this;
    }

    public function findStage(string $key): ?StageType
    {
        if ($this->stages && isset($this->stages->items[$key])) {
            return $this->stages->items[$key];
        }

        return null;
    }


    public function findButtonAction(string $key): ?ComputedType
    {
        if ($this->buttonActions && isset($this->buttonActions->items[$key])) {
            return $this->buttonActions->items[$key];
        }

        return null;
    }

    public function insertValues(array $insertValues): self
    {
        $this->insertValues = $insertValues;
        return $this;
    }


    public function getInsertValues(): array
    {
        return $this->insertValues;
    }



    public function addPipeline(array $pipeline): self
    {

        if (count($pipeline) > 0) {
            mDB::validatePipeline($pipeline);
        }

        $this->pipeline = [...$this->pipeline, ...$pipeline];



        return $this;
    }

    public function pipeline(array $pipeline): self
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

    public function collection(string $collection): self
    {

        $this->collection = $collection;
        return $this;
    }


    public function rebuild(array $items): void
    {

        foreach ($items as $key => $field) {




            ShmUtils::isValidKey($key);

            if (!$field) {
                continue;
            }

            if (!$field instanceof BaseType) {

                throw new \Exception("Field '{$key}' must be an instance of BaseType." .
                    json_encode(array_keys($items)) . " - " . gettype($field) . " given.");
            }

            if ($field->type == 'visualGroup') {

                foreach ($field->items as $subKey => $subField) {


                    $subField->key($subKey)->group($field->title ?? "Default", $field->assets['icon'] ?? null);
                    $_items[$subKey] = $subField;
                }

                continue;
            }




            $field->key($key);

            $_items[$key] = $field;
        }


        $this->items =  $_items;
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

            if (!(is_array($value) || $value instanceof Traversable)) {
                //$value = [];
                return null;
            }

            foreach ($this->items as $name => $type) {
                if ($processId) {
                    ProcessLogs::addLog($processId, "Normalizing field '{$name}' in StructureType '{$this->key}'", 1);
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

    public function staticBaseTypeName(string $name): self
    {
        $this->staticBaseTypeName = ShmUtils::onlyLetters($name);
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


                throw new \Exception("baseTypeName -> Key is not set for StructureType." . print_r($this, true));
            }

            $keys = array_keys($this->items);
            $keys = array_map(fn($type) => $type === '*' ? 'mixed' : $type, $keys);
            $typeName = Inflect::singularize(ShmUtils::onlyLetters($this->key)) .  AutoPostfix::get($keys);
        }

        $baseTypePrefix = null;
        if (!$this->expanded) {
            $baseTypePrefix = 'Flat';
        }

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

    public function findItemByCollection(string $collection): ?StructureType
    {
        foreach ($this->items as $item) {


            if ($item instanceof IDType || $item instanceof IDsType) {

                if ($item->document && $item->document->collection === $collection) {
                    return $item->document->expand();
                }
            }


            if ($item instanceof StructureType) {
                if ($item->collection === $collection)
                    return $item->expand();



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


    public function findItemByKey(string $key): ?BaseType
    {

        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        return null;
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






    public function externalData($data)
    {


        $this->updateKeys();
        $this->updatePath();
        $paths = $this->getIDsPaths([]);

        $pathsByCollections = [];


        foreach ($paths as $pathItem) {

            if (!isset($pathsByCollections[$pathItem['document']->collection])) {
                $pathsByCollections[$pathItem['document']->collection] = [];
            }

            $pathsByCollections[$pathItem['document']->collection][] = $pathItem;
        }




        foreach ($pathsByCollections as $collection => $collectionPaths) {

            Response::startTraceTiming("externalData-" .  $collection);

            $allIds = [];
            foreach ($collectionPaths as $pathItem) {

                $val =  DeepAccess::getByPath($data, $pathItem['path']);
                if (!$val || !is_array($val) || count($val) == 0) {
                    continue;
                }

                $allIds = array_merge($allIds, $val);
            }

            if (count($allIds) == 0) {
                continue;
            }

            $pathItem = $collectionPaths[0];

            $mongoDocs =  mDB::collection($pathItem['document']->collection)->aggregate([

                ...$pathItem['document']->getPipeline(),
                [
                    '$match' => [
                        '_id' => ['$in' => $allIds]
                    ]
                ],

            ])->toArray();

            if (count($mongoDocs) == 0) {
                continue;
            }

            $mongoDocs = Shm::arrayOf($pathItem['document'])->removeOtherItems($mongoDocs);



            $documentsById = [];
            foreach ($mongoDocs as $doc) {


                $documentsById[(string) $doc['_id']] = $doc;
            }




            foreach ($collectionPaths as $pathItem) {

                $many = $pathItem['many'] ?? false;

                DeepAccess::applyRecursive($data, $pathItem['path'], function ($node) use ($many, $documentsById) {

                    if ($many) {

                        $result = [];

                        if (is_object($node) || is_array($node) || $node instanceof \Traversable) {

                            foreach ($node as $id) {
                                if (isset($documentsById[(string) $id])) {
                                    $result[] = $documentsById[(string) $id];
                                }
                            }
                        }

                        return $result;
                    } else {

                        if (isset($documentsById[(string) $node])) {
                            return $documentsById[(string) $node];
                        }
                    }

                    return null;
                });
            }

            Response::endTraceTiming("externalData-" .  $collection);
        }



        return $data;
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

            $value[] = $key .  $separate . $item->tsType()->getTsTypeName();
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


    private function  expand_dot_notation(array $flatArray): array
    {
        $result = [];

        foreach ($flatArray as $compositeKey => $value) {
            $keys = explode('.', $compositeKey);
            $ref = &$result;

            foreach ($keys as $key) {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }

            $ref = $value;
        }

        return $result;
    }





    public function updateOneWithEvents(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {

        $filter = mDB::replaceStringToObjectIds($filter);

        $activeEventLogic =  (!ShmInit::$disableUpdateEvents  && $this->haveUpdateEvent() && isset($update['$set']) && count($update['$set']) > 0);


        if ($this->haveBeforeUpdateEvent() && isset($update['$set'])) {
            ShmInit::$disableUpdateEvents = true;
            $update['$set'] = $this->callBeforeUpdateEvent($update['$set']);
            ShmInit::$disableUpdateEvents = false;
        }


        if ($activeEventLogic) {

            $setFields = $update['$set'] ?? [];

            // 1. Получаем все поля, которые будем сравнивать
            $fieldsToTrack = array_keys($setFields);

            // 2. Получаем старые документы
            $oldDoc = mDB::collection($this->collection)
                ->findOne($filter, ['projection' => array_fill_keys($fieldsToTrack, 1) + ['_id' => 1]]);
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
            $update['$unset'] = $update['$unset'];
        }



        $result =  mDB::collection($this->collection)->updateOne($filter, $update, [
            ...$options,
        ]);



        if ($activeEventLogic) {

            $newDoc = mDB::collection($this->collection)
                ->findOne($filter, ['projection' => array_fill_keys(array_keys($setFields), 1) + ['_id' => 1]]);


            $allNewDoc = mDB::collection($this->collection)
                ->findOne($filter);




            $ids = $this->distinct('_id', $filter);

            if (count($ids) > 0) {
                Response::startTraceTiming("callUpdateEvent-" . $this->collection);
                ShmInit::$disableUpdateEvents = true;
                $this->callUpdateEvent([[
                    '_id' => $newDoc['_id'],
                    '_value' => $newDoc
                ]], [[
                    '_id' => $oldDoc['_id'],
                    '_value' => $oldDoc
                ]], [$allNewDoc]);
                ShmInit::$disableUpdateEvents = false;
                Response::endTraceTiming("callUpdateEvent-" . $this->collection);
            }
        }



        return    $result;
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
            $update['$unset'] = $update['$unset'];
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



    public function updateManyWithEvents(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {



        $activeEventLogic =  (!ShmInit::$disableUpdateEvents  && $this->haveUpdateEvent() && isset($update['$set']) && count($update['$set']) > 0);


        $filter = mDB::replaceStringToObjectIds($filter);


        if ($this->haveBeforeUpdateEvent() && isset($update['$set'])) {
            ShmInit::$disableUpdateEvents = true;
            $update['$set'] = $this->callBeforeUpdateEvent($update['$set']);
            ShmInit::$disableUpdateEvents = false;
        }

        if ($activeEventLogic) {

            $setFields = $update['$set'] ?? [];

            // 1. Получаем все поля, которые будем сравнивать
            $fieldsToTrack = array_keys($setFields);

            // 2. Получаем старые документы
            $oldDocs = mDB::collection($this->collection)
                ->find($filter, ['projection' => array_fill_keys($fieldsToTrack, 1) + ['_id' => 1]])
                ->toArray();
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
            $update['$unset'] = $update['$unset'];
        }

        $result =  mDB::collection($this->collection)->updateMany($filter, $update, [
            ...$options,
        ]);


        if ($activeEventLogic) {


            $newDocs = mDB::collection($this->collection)
                ->find($filter, ['projection' => array_fill_keys(array_keys($setFields), 1) + ['_id' => 1]])
                ->toArray();


            $allNewDocs = mDB::collection($this->collection)
                ->find($filter)
                ->toArray();


            $ids = [];

            foreach ($newDocs as $doc) {
                $ids[] =  $doc['_id'];
            }


            if (count($ids) > 0) {
                Response::startTraceTiming("callUpdateEvent" . $this->collection);
                ShmInit::$disableUpdateEvents = true;
                $this->callUpdateEvent(
                    array_map(function ($e) {

                        return [
                            '_id' => $e['_id'],
                            '_value' => $e
                        ];
                    }, $newDocs),
                    array_map(function ($e) {

                        return [
                            '_id' => $e['_id'],
                            '_value' => $e
                        ];
                    }, $oldDocs),
                    $allNewDocs
                );
                ShmInit::$disableUpdateEvents = false;
                Response::endTraceTiming("callUpdateEvent" . $this->collection);
            }
        }


        return   $result;
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
            $update['$unset'] = $update['$unset'];
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


    public function insertOneWithEvents($document, array $options = []): \MongoDB\InsertOneResult
    {


        $activeEventLogic =  !ShmInit::$disableInsertEvents  && $this->haveInsertEvent();



        $document = [
            ...$document,
            ...$this->getInsertValues()
        ];


        $document = $this->normalize($document, true);


        if ($this->haveBeforeInsertEvent()) {
            ShmInit::$disableInsertEvents = true;
            $document = $this->callBeforeInsertEvent($document);
            ShmInit::$disableInsertEvents = false;

            $document = $this->normalize($document, true);
        }



        $result = mDB::collection($this->collection)->insertOne($document, $options);



        if ($activeEventLogic) {

            $newDoc = mDB::collection($this->collection)
                ->findOne([
                    "_id" => $result->getInsertedId()
                ]);



            Response::startTraceTiming("callInsertEvent-" . $this->collection);
            ShmInit::$disableInsertEvents = true;
            $this->callInsertEvent([[
                '_id' => $newDoc['_id'],
                '_value' => $newDoc
            ]], [$newDoc]);
            ShmInit::$disableInsertEvents = false;
            Response::endTraceTiming("callInsertEvent-" . $this->collection);
        }





        return  $result;
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


    public function insertManyWithEvents(array $documents, array $options = []): \MongoDB\InsertManyResult
    {
        $activeEventLogic =  !ShmInit::$disableInsertEvents  && $this->haveInsertEvent();

        foreach ($documents as &$document) {
            $document = [
                ...$document,
                ...$this->getInsertValues()
            ];
        }

        $documents = Shm::arrayOf($this)->normalize($documents, true);



        if ($this->haveBeforeInsertEvent()) {
            ShmInit::$disableInsertEvents = true;

            foreach ($documents as &$document) {
                $document = $this->callBeforeInsertEvent($document);
            }

            ShmInit::$disableInsertEvents = false;

            $documents = Shm::arrayOf($this)->normalize($documents, true);
        }

        $result = mDB::collection($this->collection)->insertMany($documents, $options);



        if ($activeEventLogic) {

            $newDocs = mDB::collection($this->collection)
                ->find([
                    "_id" => ['$in' => $result->getInsertedIds()]
                ])->toArray();


            Response::startTraceTiming("callInsertEvent-" . $this->collection);
            ShmInit::$disableInsertEvents = true;
            $this->callInsertEvent(array_map(function ($e) {

                return [
                    '_id' => $e['_id'],
                    '_value' => $e
                ];
            }, $newDocs), $newDocs);
            ShmInit::$disableInsertEvents = false;
            Response::endTraceTiming("callInsertEvent-" . $this->collection);
        }




        return $result;
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



    public function updatePath(array | null $path = null): void
    {

        if ($this->key === null) {
            throw new \LogicException('Key must be set before updating path.');
        }



        if ($this->collection) {
            $this->path = [...($path ?? [])];
        } else {
            $this->path = [...($path ?? []), $this->key];
        }

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $item->updatePath([...$this->path]);
            }
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



    public function hideNotInTable(): self
    {

        if ($this->type == "structure") {


            foreach ($this->items as $key => $item) {

                $item->hideNotInTable();
            }
        } else {
            if (! $this->inTable) {
                $this->hide = true;
            }
        }
        return $this;
    }
}
