<?php

namespace Shm\ShmTypes;

use Shm\ShmDB\mDB;


use Shm\CachedType\CachedInputObjectType;


use Shm\CachedType\CachedObjectType;

use Shm\Shm;
use Shm\ShmAdmin\Types\VisualGroupType;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\SupportTypes\StageType;
use Shm\ShmUtils\AutoPostfix;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\ProcessLogs;
use Shm\ShmUtils\ShmUtils;
use stdClass;
use Traversable;

class StructureType extends BaseType
{
    public string $type = 'structure';


    public $collection = null;

    private $pipeline = [];


    public bool $canUpdate = false;
    public bool $canCreate = false;
    public bool $canDelete = false;



    public function addField(string $key, BaseType $type): self
    {


        if (!isset(
            $this->items[$key]
        ))
            $this->items[$key] = $type;


        return $this;
    }


    public function canDelete(bool $canDelete = true): self
    {
        $this->canDelete = $canDelete;
        return $this;
    }


    public function canUpdate(bool $canUpdate = true): self
    {
        $this->canUpdate = $canUpdate;
        return $this;
    }
    public function canCreate(bool $canCreate = true): self
    {
        $this->canCreate = $canCreate;
        return $this;
    }


    private null | StructureType $stages = null;


    public $publicStages = [];

    public function getStages(): ?StructureType
    {
        return $this->stages;
    }

    public function stages(StructureType $stages): self
    {

        foreach ($stages->items as $key => $stage) {
            if (!($stage instanceof StageType)) {
                throw new \InvalidArgumentException("Stage '{$key}' must be an instance of StageType.");
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


    public function pipeline(array $pipeline): self
    {
        $this->pipeline = $pipeline;
        return $this;
    }

    public function getPipeline(): array
    {
        return $this->pipeline;
    }

    public function collection(string $collection): self
    {

        $this->collection = $collection;
        return $this;
    }

    /**
     * @param array<string, BaseType> $items
     */
    public function __construct(array $items)
    {



        foreach ($items as $key => $field) {




            ShmUtils::isValidKey($key);

            if (!$field) {
                continue;
            }

            if (!$field instanceof BaseType) {
                throw new \InvalidArgumentException("Field '{$key}' must be an instance of BaseType.");
            }

            if ($field->type == 'visualGroup') {

                foreach ($field->items as $subKey => $subField) {


                    $subField->key($subKey)->group($field->title ?? "Default", $field->svgIcon);
                    $_items[$subKey] = $subField;
                }

                continue;
            }




            $field->key($key);

            $_items[$key] = $field;
        }


        $this->items =  $_items;
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


                if ($type->notNull && !$value[$key]) {
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
            throw new \InvalidArgumentException("{$field} must be an object/structure (associative array).");
        }
        foreach ($this->items as $name => $type) {
            try {
                $type->validate($value[$name] ?? null);
            } catch (\InvalidArgumentException $e) {
                $field = $this->title ?? $name;
                throw new \InvalidArgumentException("{$field}.{$name}: " . $e->getMessage());
            }
        }
    }

    private $staticBaseTypeName = null;

    public function staticBaseTypeName(string $name): self
    {
        $this->staticBaseTypeName = ShmUtils::onlyLetters($name);
        return $this;
    }

    private  $baseTypePrefix = null;


    public  function baseTypePrefix(string $prefix): self
    {
        $this->baseTypePrefix = ShmUtils::onlyLetters($prefix);

        return $this;
    }

    public function baseTypeName()
    {

        if ($this->staticBaseTypeName) {
            return $this->staticBaseTypeName;
        }


        if (!$this->key) {
            throw new \InvalidArgumentException("baseTypeName -> Key is not set for StructureType." . print_r($this, true));
        }

        $typeName = '';

        if ($this->collection) {
            $typeName = Inflect::singularize(ShmUtils::onlyLetters($this->collection));
        } else {

            $keys = array_keys($this->items);
            $keys = array_map(fn($type) => $type === '*' ? 'mixed' : $type, $keys);
            $typeName = Inflect::singularize(ShmUtils::onlyLetters($this->key)) .  AutoPostfix::get($keys);
        }

        return $this->baseTypePrefix ? $this->baseTypePrefix . $typeName : $typeName;
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

    public function fullEditable(bool $editable = true): static
    {

        $this->editable = $editable;

        foreach ($this->items as $key => $field) {

            if ($key == "_id") {
                continue;
            }
            $field->fullEditable($editable);
        }

        return $this;
    }

    public function safeFullEditable(bool $editable = true): static
    {
        $this->editable = $editable;


        if ($this->collection) return $this;


        foreach ($this->items as $key => $field) {

            $field->fullEditable($editable);
        }

        return $this;
    }



    public function externalData($data)
    {

        $this->updateKeys();
        $this->updatePath();
        $paths = $this->getIDsPaths([]);





        foreach ($paths as $pathItem) {


            $val =  DeepAccess::getByPath($data, $pathItem['path']);


            if (!$val || !is_array($val) || count($val) == 0) {
                continue;
            }

            $many = $pathItem['many'] ?? false;


            $mongoDocs =  mDB::collection($pathItem['document']->collection)->aggregate([

                ...$pathItem['document']->getPipeline(),
                [
                    '$match' => [
                        '_id' => ['$in' => $val]
                    ]
                ],

            ])->toArray();




            $mongoDocs = Shm::arrayOf($pathItem['document'])->removeOtherItems($mongoDocs);





            $documentsById = [];
            foreach ($mongoDocs as $doc) {


                $documentsById[(string) $doc['_id']] = $doc;
            }




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
        $itemTypeFilter = Shm::structure($fields)->fullEditable()->fullInAdmin()->title($this->title)->staticBaseTypeName($this->key . 'FilterArgs');

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


            if ($item instanceof SelfRefType) {

                $value[] = $key .  $separate . $item->resolveType()->typeName();
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

            $separate = $item->nullable ? '?: ' : ': ';

            $value[] = $key .  $separate . $item->tsInputType()->getTsTypeName();
        }

        $TSType = new TSType($this->inputTypeName(), '{\n' . implode(',\n', $value) . '\n}');




        return $TSType;
    }


    public function updateOne(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {

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

        $update =  mDB::collection($this->collection)->updateOne($filter, $update, [
            ...$options,
        ]);

        return $update;
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

        $update =  mDB::collection($this->collection)->updateMany($filter, $update, [
            ...$options,
        ]);

        return $update;
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

        return   mDB::collection($this->collection)->find($filter, $options);
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

        return   mDB::collection($this->collection)->findOne($filter, $options);
    }



    public function insertOne($document, array $options = []): \MongoDB\InsertOneResult
    {

        $document = $this->normalize($document, true);

        return mDB::collection($this->collection)->insertOne($document, $options);
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





    public function columns(array | null $path = null): array
    {



        $this->columns = [];

        $columns = [];



        foreach ($this->items as $key => $item) {

            if ($item->inAdmin) {
                if (!$this->collection) {
                    $columns = [...$columns, ...$item->columns([$this->key])];
                } else {
                    $columns = [...$columns, ...$item->columns()];
                }
            } else {
                $item->columns();
            }
        }

        if ($this->collection) {
            $this->columns = $columns;
        }


        return $columns;
    }
}