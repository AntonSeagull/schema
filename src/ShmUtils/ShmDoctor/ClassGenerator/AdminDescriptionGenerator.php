<?php

namespace Shm\ShmUtils\ShmDoctor\ClassGenerator;

use Nette\PhpGenerator\ClassType;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmDoctor\Utils\CodeGenerator;
use Shm\ShmUtils\ShmInit;

class AdminDescriptionGenerator
{
    /**
     * Generate admin lang classes for all structures
     */
    public static function generateAll(): void
    {
        $structures = \Shm\ShmUtils\ShmDoctor\Utils\StructureHelper::getStructures();

        foreach ($structures as $structure) {
            echo $structure::class . PHP_EOL;
            self::generate($structure::structure());
        }
    }

    /**
     * Generate admin lang class for a structure
     */
    public static function generate(StructureType $structure): void
    {
        $structure->updateKeys();
        $dir = ShmInit::$rootDir . '/config/admin_descriptions/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }


        $fileName = $structure->collection . '.json';


        if (file_exists($dir . $fileName)) {
            $existData = json_decode(file_get_contents($dir . $fileName), true);
        } else {
            $existData = [];
        }


        $fields = self::generateFields($structure, $existData);




        file_put_contents($dir . $fileName, json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Add constants to class based on structure for admin descriptions
     */
    private static function generateFields(StructureType $structure, array $existData): array
    {

        $result = [];

        foreach ($structure->items as $key => $item) {
            if ($key == '*') {
                continue;
            }

            if (!$item->inAdmin) {
                continue;
            }

            $result[$key] = [
                'descriptionEN' =>  $existData[$key]['descriptionEN'] ?? "",
                'descriptionRU' => $existData[$key]['descriptionRU'] ?? "",
                'titleEN' => $existData[$key]['titleEN'] ?? "",
                'titleRU' => $item->title ?? "",
            ];



            if ($item instanceof StructureType) {

                $result[$key]['items'] = self::generateFields($item, $existData[$key]['items'] ?? []);
            }

            if ($item instanceof ArrayOfType && $item->itemType instanceof StructureType) {
                $result[$key]['items'] = self::generateFields($item->itemType, $existData[$key]['items'] ?? []);
            }
        }

        return $result;
    }
}
