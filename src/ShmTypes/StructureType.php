<?php

namespace Shm\ShmTypes;

use DateTime;
use Error;
use Sentry\Util\Arr;
use Sentry\Util\Str;
use Shm\ShmDB\mDB;


use Shm\CachedType\CachedInputObjectType;


use Shm\CachedType\CachedObjectType;

use Shm\Shm;
use Shm\ShmAdmin\SchemaCollections\ManualTags;
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





    public $tagMode = false;

    public function tagMode(bool $tagMode = true): static
    {
        $this->tagMode = $tagMode;

        return $this;
    }


    public bool $manualSort = false;


    public bool $canUpdate = false;
    public bool $canCreate = false;
    public bool $canDelete = false;


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

    public function addFieldIfNotExists(string $key, BaseType $type): static
    {

        $key = ShmUtils::cleanKey($key);

        if (!isset($this->items[$key])) {
            $this->items[$key] = $type;
        } else {
        }

        return $this;
    }

    public function addField(string $key, BaseType $type): static
    {

        $key = ShmUtils::cleanKey($key);

        $this->items[$key] = $type;


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


    private null | StructureType $stages = null;

    public null | StructureType $buttonActions = null;



    public $publicStages = [];

    public function getStages(): ?StructureType
    {
        return $this->stages;
    }


    public function buttonActions(StructureType $buttonActions): static
    {
        foreach ($buttonActions->items as $key => $action) {
            if (!($action instanceof ComputedType)) {
                throw new \Exception("Button action '{$key}' must be an instance of ComputedType.");
            }
        }

        $this->buttonActions = $buttonActions;
        return $this;
    }

    public function stages(StructureType $stages): static
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


    private function reportCollection(StructureType | null $root = null, $path = [], $pipeline = [])
    {
        $maxAtDay = time() - 60 * 60 * 24 * 30; // последние 30 дней

        $date = new DateTime('first day of -2 months');
        if ($date->format('N') != 1) {
            $date->modify('next monday');
        }
        $maxAtWeek = $date->getTimestamp();

        $date = new DateTime('first day of -6 months');
        $maxAtMonth = $date->getTimestamp();

        $total = $root->aggregate([
            ...$pipeline,
            [
                '$count' => 'total'
            ]
        ])->toArray()[0]['total'] ?? 0;

        // === По дням ===
        $basePipelineDay = [
            ['$match' => ['created_at' => ['$gte' => $maxAtDay]]],
            ['$project' => [
                'label' => [
                    '$dateToString' => [
                        'format' => '%d.%m.%Y',
                        'date' => ['$toDate' => ['$multiply' => ['$created_at', 1000]]],
                        'timezone' => date_default_timezone_get()
                    ]
                ],
                'created_at' => 1,
            ]],
            ['$group' => [
                '_id' => '$label',
                'value' => ['$sum' => 1],
                'name' => ['$first' => '$created_at'],
            ]],
            ['$sort' => ['name' => 1]]
        ];

        $basePipelineWeek = [
            ['$match' => ['created_at' => ['$gte' => $maxAtWeek]]],
            ['$project' => [
                'created_at' => 1,
                'startOfWeek' => [
                    '$dateTrunc' => [
                        'date' => ['$toDate' => ['$multiply' => ['$created_at', 1000]]],
                        'unit' => 'week',
                        'timezone' => date_default_timezone_get(),
                        'binSize' => 1
                    ]
                ]
            ]],
            ['$group' => [
                '_id' => '$startOfWeek',
                'value' => ['$sum' => 1],
            ]],
            ['$sort' => ['_id' => 1]]
        ];

        $basePipelineMonth = [
            ['$match' => ['created_at' => ['$gte' => $maxAtMonth]]],
            ['$project' => [
                'label' => [
                    '$dateToString' => [
                        'format' => '%m.%Y',
                        'date' => ['$toDate' => ['$multiply' => ['$created_at', 1000]]],
                        'timezone' => date_default_timezone_get()
                    ]
                ],
                'created_at' => 1,
            ]],
            ['$group' => [
                '_id' => '$label',
                'value' => ['$sum' => 1],
                'name' => ['$first' => '$created_at'],
            ]],
            ['$sort' => ['name' => 1]]
        ];

        // === Выполнение запросов ===
        $resultDay = $root->aggregate([...$pipeline, ...$basePipelineDay])->toArray();
        $resultWeek = $root->aggregate([...$pipeline, ...$basePipelineWeek])->toArray();
        $resultMonth = $root->aggregate([...$pipeline, ...$basePipelineMonth])->toArray();

        // Приводим name к дате
        foreach ([$resultDay, $resultMonth] as &$resultGroup) {
            foreach ($resultGroup as &$item) {
                $item['name'] = date('d.m.Y', $item['name']);
            }
        }

        foreach ($resultWeek as &$item) {
            $start = new DateTime();
            $start->setTimestamp($item['_id']->toDateTime()->getTimestamp()); // from MongoDB UTCDateTime
            $end = clone $start;
            $end->modify('+6 days');

            $item['name'] = $start->format('d.m.Y') . ' – ' . $end->format('d.m.Y');
        }


        $mainSecond = [];

        if (count($resultDay) > 0) {
            $mainSecond[] = [
                'view' => 'bar',
                'title' => "По дням",
                'result' => $resultDay,
            ];
        }
        if (count($resultWeek) > 0) {
            $mainSecond[] = [
                'view' => 'bar',
                'title' => "По неделям",
                'result' => $resultWeek,
            ];
        }
        if (count($resultMonth) > 0) {
            $mainSecond[] = [
                'view' => 'bar',
                'title' => "По месяцам",
                'result' => $resultMonth,
            ];
        }


        $heatmap = $root->aggregate([
            ...$pipeline,
            [
                '$addFields' => [
                    'createdDate' => [
                        '$toDate' => ['$multiply' => ['$created_at', 1000]]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'dayOfWeek' => ['$isoDayOfWeek' => '$createdDate'], // 1 = Monday, 7 = Sunday
                    'hour' => ['$hour' => '$createdDate']
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'dayOfWeek' => '$dayOfWeek',
                        'hour' => '$hour',
                        'date' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$createdDate']]
                    ],
                    'count' => ['$sum' => 1]
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'dayOfWeek' => '$_id.dayOfWeek',
                        'hour' => '$_id.hour'
                    ],
                    'avgCount' => ['$avg' => '$count']
                ]
            ],
            [
                '$group' => [
                    '_id' => '$_id.dayOfWeek',
                    'hours' => [
                        '$push' => [
                            'hour' => '$_id.hour',
                            'avgCount' => ['$round' => ['$avgCount', 2]]
                        ]
                    ]
                ]
            ],
            [
                '$sort' => ['_id' => 1]
            ]

        ])->toArray();



        $resultData = [
            [
                'type' => $this->type,
                'title' => $this->title,
                'main' => [
                    [
                        'view' => 'cards',

                        'result' => [
                            [
                                'value' => $total,
                                'name' => "Всего",

                            ]
                        ]
                    ]
                ]
            ],

        ];

        if (count($mainSecond) > 0) {
            $resultData[] =
                [
                    'type' => $this->type,
                    'title' => "Создано \"{$this->title}\"",
                    'main' => $mainSecond,

                ];
        }






        $resultData[] = [
            'type' => $this->type,
            'title' => "Создано \"{$this->title}\" среднее кол-во по дням недели и часам",
            'main' => [
                [
                    'view' => 'heatmap',
                    'title' => "Создано \"{$this->title}\"",
                    'heatmap' =>  $this->formatHeatmap($heatmap)
                ]
            ]
        ];

        return $resultData;
    }


    public function computedReport(StructureType | null $root = null, $path = [], $pipeline = [])
    {


        if (!$this->report) {
            return null;
        }


        $report = [];


        $pipeline = [...$pipeline, ...$this->getPipeline()];




        if (!$this->collection) {
            $path = [...$path];
        } else {

            $root = $this;

            $reportItem = $this->reportCollection($root, $path, $pipeline);

            if ($reportItem) {
                $report = [
                    ...$report,
                    ...$reportItem
                ];
            }
        }
        foreach ($this->items as $key => $item) {

            if ($item->hide || !$item->inAdmin) {
                continue;
            }

            Response::startTraceTiming("computedReport-{$item->key}");
            $reportItem = $item->computedReport($root, [...$path, $key], $pipeline);

            if ($reportItem)

                if (isset($reportItem['type'])) {
                    Response::endTraceTiming("computedReport-{$item->key}");
                    $report = [
                        ...$report,
                        $reportItem
                    ];
                } else {
                    $report = [
                        ...$report,
                        ...$reportItem
                    ];
                }
        }



        return  $report;
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
        if (!$this->expanded && $this->haveID()) {


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

        $collections = $this->getAllCollections();

        if (isset($collections[$collection])) {
            return $collections[$collection];
        }



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
            $mongoDocs = Shm::arrayOf($pathItem['document'])->toOutput($mongoDocs);



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

            if ($item->hide) {
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
}
