<?php

namespace Shm\ShmBlueprints;

use InvalidArgumentException;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;

class ShmBlueprintMutation
{


    private  StructureType $structure;


    public function __construct(StructureType $structure)
    {
        $this->structure = $structure;
    }

    public $oneRow = false;

    public function oneRow(bool $oneRow): static
    {
        $this->oneRow = $oneRow;
        return $this;
    }


    public $delete = true;




    public function delete(bool $delete): static
    {
        $this->delete = $delete;
        return $this;
    }


    private $pipelineFunction = null;

    public function pipeline($pipeline = null): static
    {
        if ($pipeline === null) {
            return $this;
        }

        if (is_array($pipeline)) {
            // Если передан массив, создаем функцию, которая его возвращает
            $this->pipelineFunction = function () use ($pipeline) {
                return $pipeline;
            };
        } elseif (is_callable($pipeline)) {
            // Если передана функция, сохраняем её
            $this->pipelineFunction = $pipeline;
        } else {
            throw new InvalidArgumentException('Pipeline должен быть массивом или функцией');
        }

        return $this;
    }

    public function getPipeline(): array
    {
        if ($this->pipelineFunction === null) {
            return [];
        }

        $pipeline = call_user_func($this->pipelineFunction);

        if (!$pipeline) {
            return [];
        }

        // Валидируем полученный pipeline
        mDB::validatePipeline($pipeline);

        return $pipeline;
    }



    public function flattenObject($data, $parentKey = '', &$result = [])
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $newKey = $parentKey === '' ? $key : "{$parentKey}.{$key}";

                if (is_array($value) || is_object($value) && !($value instanceof \MongoDB\BSON\ObjectId)) {
                    if (empty((array)$value)) {
                        // Если массив или объект пустой, добавляем его как есть
                        $result[$newKey] = $value;
                    } else {

                        $firstZero = $value[0] ?? null;

                        if ($firstZero && $firstZero instanceof \MongoDB\BSON\ObjectId) {
                            $result[$newKey] = $value;
                        } else {


                            // Рекурсивный вызов для непустого массива или объекта
                            $this->flattenObject($value, $newKey, $result);
                        }
                    }
                } else {
                    // Примитивные значения добавляем как плоский ключ
                    $result[$newKey] = $value;
                }
            }
        }
        return $result;
    }


    public function make()
    {


        $args = [];
        if (!$this->oneRow) {
            $args = [
                "_id" => Shm::ID(),
            ];
        }



        if ($this->delete  && !$this->oneRow) {
            $args['delete'] = Shm::boolean();
        }

        $args['fields'] = $this->structure->editable();

        $editableKeys = array_values(array_filter(array_keys($this->structure->items), function ($key) {
            return isset($this->structure->items[$key]->editable) && $this->structure->items[$key]->editable;
        }));


        if (count($editableKeys) > 0) {
            $args['unset'] = Shm::enum($editableKeys);
        }

        $fields = [];


        foreach ($this->structure->items as $key => $value) {

            $editable = $value->editable ?? false;
            if (!$editable) {
                continue;
            }

            $type = $value->type ?? null;

            if ($type == "IDs") {
                $fields[$key] = Shm::arrayOf(Shm::ID());
            }
        }

        if (count($fields) > 0) {
            $args['addToSet']  = Shm::structure($fields)->editable();
            $args['pull']  = Shm::structure($fields)->editable();
        }


        if ($this->structure->manualSort && !$this->oneRow) {

            $args['move'] = Shm::structure([
                'aboveId' => Shm::ID(),
                'belowId' => Shm::ID()
            ]);
        }




        $_this = $this;

        $argsStructure = Shm::structure($args);

        $structure = $this->structure;



        return [
            "type" => $this->structure,
            "args" => $argsStructure,
            'resolve' => function ($root, $args) use ($_this, $structure, $argsStructure) {

                $pipeline = $_this->getPipeline();

                if ($_this->oneRow) {

                    if (!$pipeline || count($pipeline) == 0) {
                        Response::validation('Ошибка доступа');
                    }



                    $find = $structure->aggregate([
                        ...$pipeline,
                        ['$limit' => 1]
                    ])->toArray();

                    if (!$find) {
                        Response::validation('Ошибка доступа');
                    }


                    $args['_id'] =  $find[0]->_id ?? null;

                    if (!$args['_id']) {
                        Response::validation('Ошибка доступа');
                    }
                }




                $pipeline = [
                    ...$pipeline,
                    ...$structure->getPipeline()
                ];


                $originalArgs = $args;
                $args = $argsStructure->normalize($args, true);




                $_id = $args['_id'] ?? null;
                if (isset($_id)) {



                    $findItem = $structure->aggregate([
                        ...$pipeline,
                        [
                            '$match' => [
                                "_id" => mDB::id($_id),
                            ],
                        ],
                        [
                            '$limit' => 1
                        ],
                    ])->toArray()[0] ?? null;

                    if (!$findItem) {
                        Response::validation('Ошибка доступа, у вас нет доступа для редактирования этой записи');
                    }



                    if (!$_this->oneRow && $args['delete'] == true) {
                        $structure->deleteOne([
                            "_id" => mDB::id($_id)
                        ]);

                        return null;
                    } else {





                        if ($originalArgs['fields']) {


                            $setValue = $argsStructure->items['fields']->normalize($originalArgs['fields']);

                            $structure->updateOne(
                                [
                                    '_id' => mDB::id($_id)
                                ],
                                [
                                    '$set' =>  $setValue
                                ]
                            );
                        }

                        if (isset($args['addToSet'])) {

                            $addToSet = [];

                            foreach ($args['addToSet'] as $key => $value) {

                                foreach ($value as &$val) {
                                    $val = mDB::id($val);
                                }

                                $addToSet[$key] = ['$each' => $value];
                            }

                            if (count($addToSet) > 0)
                                $structure->updateOne(['_id' => mDB::id($_id)], ['$addToSet' => $addToSet]);
                        }



                        if (isset($args['pull'])) {

                            $pull = [];

                            foreach ($args['pull'] as $key => $value) {
                                foreach ($value as &$val) {
                                    $val = mDB::id($val);
                                }
                                $pull[$key] = ['$in' => $value];
                            }

                            if (count($pull) > 0)
                                $structure->updateOne(['_id' => mDB::id($_id)], ['$pull' => $pull]);
                        }


                        if (isset($args['unset'])) {

                            $nullSet = [];

                            if (count($nullSet) > 0)
                                $structure->updateOne(['_id' => mDB::id($_id)], ['$set' => $nullSet]);
                        }

                        if (isset($args['move'])) {
                            $currentId = mDB::id($_id);
                            $aboveId = $args['move']['aboveId'] ?? null;
                            $belowId = $args['move']['belowId'] ?? null;
                            $structure->moveRow($currentId, $aboveId, $belowId);
                        }




                        return  $structure->findOne([
                            "_id" => mDB::id($_id)
                        ]);
                    }
                } else {

                    if ($_this->oneRow) {
                        Shm::error('Ошибка доступа');
                    }

                    if (isset($args['fields'])) {

                        $inserValue = $argsStructure->items['fields']->normalize($originalArgs['fields']);


                        $insert =  $structure->insertOne($inserValue);


                        return $structure->findOne([
                            "_id" => $insert->getInsertedId()
                        ]);
                    }
                }
            },
        ];
    }
}
