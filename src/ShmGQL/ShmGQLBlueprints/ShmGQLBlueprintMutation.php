<?php

namespace Shm\ShmGQL\ShmGQLBlueprints;

use Shm\Shm;
use Shm\Types\StructureType;

class ShmGQLBlueprintMutation
{


    private  StructureType $structure;


    public function __construct(StructureType $structure)
    {
        $this->structure = $structure;
    }

    /**
     * @var callable|null
     */
    public $beforeMutation = null;


    public $delete = true;



    /**
     * @var callable|null
     */
    public $afterMutation = null;

    public function delete(bool $delete): self
    {
        $this->delete = $delete;
        return $this;
    }


    public function before(callable $beforeMutation): self
    {
        $this->beforeMutation = $beforeMutation;
        return $this;
    }

    public function after(callable $afterMutation): self
    {
        $this->afterMutation = $afterMutation;
        return $this;
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



        $args = [
            "_id" => Shm::ID(),
        ];


        if ($this->delete) {
            $args['delete'] = Shm::boolean();
        }

        $args['fields'] = $this->structure;

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
            $args['addToSet']  = Shm::structure($fields)->fullEditable();
            $args['pull']  = Shm::structure($fields)->fullEditable();
        }


        /*        if ($collection->sortWeight) {

            $args['move'] = GQL::objectInputType([
                "name" => "MoveInputType",
                "fields" => [
                    'aboveId' => DefinitionType::string(),
                    'belowId' => DefinitionType::string()

                ],
            ]);
        }*/

        $_this = $this;

        $argsStructure = Shm::structure($args);

        return [
            "type" => $this->structure,
            "args" => $argsStructure,
            'resolve' => function ($root, $args) use ($_this, $argsStructure) {

                $args = $argsStructure->normalize($args, true);


                if ($args['_id']) {
                }

                /*


                $args = [...$args];

             
                $argsId = $_this->_id ?? $args['_id'] ?? null;

                if (isset($argsId)) {

                    if (isset($args['delete']) && $args['delete'] == true) {
                        $collection::deleteOne([
                            "_id" => mDB::id($argsId)
                        ]);

                        if (is_callable($_this->afterMutation)) {
                            call_user_func($_this->afterMutation, mDB::id($argsId));
                        }



                        return null;
                    } else {

                        if (isset($args['fields'])) {

                            $setFields = $collection::toExpectData($args['fields']);

                            $setFields = $_this->flattenObject($setFields);


                            $collection::updateOne(['_id' => mDB::id($argsId)], ['$set' => $setFields], [], false);
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
                                $collection::updateOne(['_id' => mDB::id($argsId)], ['$addToSet' => $addToSet]);
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
                                $collection::updateOne(['_id' => mDB::id($argsId)], ['$pull' => $pull]);
                        }


                        if (isset($args['unset'])) {

                            $nullSet = [];
                            foreach ($args['unset'] as $key) {
                                if ($key == "_id") continue;
                                $nullSet[$key] = null;
                            }
                            if (count($nullSet) > 0)
                                $collection::updateOne(['_id' => mDB::id($argsId)], ['$set' => $nullSet]);
                        }

                        if (isset($args['move'])) {
                            $currentId = mDB::id($argsId);
                            $aboveId = $args['move']['aboveId'] ?? null;
                            $belowId = $args['move']['belowId'] ?? null;
                            $collection::moveRow($currentId, $aboveId, $belowId);
                        }


                        if (is_callable($_this->afterMutation)) {
                            call_user_func($_this->afterMutation, mDB::id($argsId));
                        }


                        return $collection::richFindOne([
                            "_id" => mDB::id($argsId)
                        ]);
                    }
                } else {
                    if (isset($args['fields'])) {
                        $insert = $collection::insertOne($args['fields']);


                        if (is_callable($_this->afterMutation)) {
                            call_user_func($_this->afterMutation, $insert->getInsertedId());
                        }




                        return $collection::richFindOne([
                            "_id" => $insert->getInsertedId()
                        ]);
                    }
                }*/
            },
        ];
    }
}