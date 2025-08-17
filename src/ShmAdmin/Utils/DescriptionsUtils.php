<?php

namespace Shm\ShmAdmin\Utils;

use Shm\ShmDB\mDB;

class DescriptionsUtils
{


    public static function fields($collectionName, $modelName)
    {

        $collectionDescription =   mDB::collection("_collectionDescriptions")->findOne([
            "key" => $collectionName
        ]);
        if (!$collectionDescription) return [];

        $fields = $collectionDescription['fields'][$modelName] ?? [];

        foreach (($collectionDescription['fields'] ?? []) as $_model => $_fields) {

            if ($_model != $modelName) {
                $fields = [
                    ...(array) $_fields,
                    ...(array) $fields
                ];
            }
        }
        return $fields;
    }

    /**
     * Retrieves groups associated with a specific model in a collection.
     * If no description is found for the collection, returns an empty array.
     * Merges groups from all models in the collection if they differ from the specified model.
     *
     * @param string $collectionName The name of the collection to search in
     * @param string $modelName The name of the model to get groups for
     * @return array The groups associated with the model, or empty array if none found
     */
    public static function groups($collectionName, $modelName)
    {

        $collectionDescription =   mDB::collection("_collectionDescriptions")->findOne([
            "key" => $collectionName
        ]);
        if (!$collectionDescription) return [];

        $groups =  $collectionDescription['groups'][$modelName] ?? [];

        foreach (($collectionDescription['groups'] ?? []) as $_model => $_groups) {

            if ($_model != $modelName) {
                $groups = [
                    ...(array) $_groups,
                    ...(array) $groups
                ];
            }
        }
        return $groups;
    }

    public static function tabs($collectionName, $modelName)
    {

        $collectionDescription =   mDB::collection("_collectionDescriptions")->findOne([
            "key" => $collectionName
        ]);
        if (!$collectionDescription) return [];

        $tabs = $collectionDescription['tabs'][$modelName] ?? [];

        foreach (($collectionDescription['tabs'] ?? []) as $_model => $_tabs) {

            if ($_model != $modelName) {
                $tabs = [
                    ...(array) $_tabs,
                    ...(array) $tabs
                ];
            }
        }
        return $tabs;
    }

    public static function menu($collectionName, $modelName)
    {

        $collectionDescription =   mDB::collection("_collectionDescriptions")->findOne([
            "key" => $collectionName
        ]);
        if (!$collectionDescription) return [];

        $menu = $collectionDescription['menu'][$modelName] ?? [];

        foreach (($collectionDescription['menu'] ?? []) as $_model => $_menu) {

            if ($_model != $modelName) {
                $menu = [
                    ...(array) $_menu,
                    ...(array) $menu
                ];
            }
        }
        return $menu;
    }
}