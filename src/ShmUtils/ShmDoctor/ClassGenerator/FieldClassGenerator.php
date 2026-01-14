<?php

namespace Shm\ShmUtils\ShmDoctor\ClassGenerator;

use Nette\PhpGenerator\ClassType;
use Shm\ShmUtils\ShmInit;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmDoctor\Utils\CodeGenerator;

class FieldClassGenerator
{
    /**
     * Generate field classes for all structures
     */
    public static function generateAll(): void
    {
        $structures = \Shm\ShmUtils\ShmDoctor\Utils\StructureHelper::getStructures();
        $dir = ShmInit::$rootDir . '/app/FieldClasses/';

        foreach ($structures as $structure) {
            echo $structure::class . PHP_EOL;
            self::generate($structure::structure());
        }
    }

    /**
     * Generate field class for a structure
     */
    public static function generate(StructureType $structure): void
    {
        $structure->updateKeys();
        $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));
        $dir = ShmInit::$rootDir . '/app/FieldClasses/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $classFullName = $className . 'Field';
        $class = new ClassType($classFullName);

        self::addConstantByStructure($class, $structure);

        $filePath = $dir . $classFullName . '.php';
        $output = "<?php\n\nnamespace App\\FieldClasses;\n\n";
        $output .= "use Shm\ShmUtils\DeepAccess;\n\n";
        $output .= $class . "\n\n";

        file_put_contents($filePath, $output);
    }

    /**
     * Add constants to class based on structure
     */
    private static function addConstantByStructure(ClassType $class, StructureType $structure, string $prefix = ''): void
    {
        foreach ($structure->items as $key => $item) {
            if ($key == '*') {
                continue;
            }

            $comment = (string) ($item->title ?? $key);

            if ($item instanceof IDsType) {
                $comment .= " (ObjectId[])";
            } elseif ($item instanceof IDType) {
                $comment .= " (ObjectId)";
            }

            if ($item instanceof StructureType) {
                $comment .= " (Structure)";
            }
            if ($item instanceof ArrayOfType) {
                $comment .= " (Array)";
            }
            if ($item instanceof EnumType) {
                $comment .= " (Enum)";
            }

            $constName = strtoupper(str_replace('.', '__', CodeGenerator::toSnakeCase($prefix) . CodeGenerator::toSnakeCase($key)));

            try {
                $class->addConstant($constName, $prefix . $item->key)
                    ->setVisibility('public')
                    ->addComment($comment . ' полный пусть ключа');
            } catch (\Throwable $error) {
            }

            $method = $class->addMethod($constName . '_GET');
            $method->setStatic();
            $method->setVisibility('public');
            $method->addParameter('data');
            $method->addParameter('defaultValue')->setDefaultValue(null);
            $method->setBody('return DeepAccess::get($data, self::' . $constName . ', $defaultValue);');

            if ($prefix) {
                $constNameOneKey = strtoupper(str_replace('.', '__', CodeGenerator::toSnakeCase($prefix) . CodeGenerator::toSnakeCase($key) . '_KEY'));

                try {
                    $class->addConstant($constNameOneKey, $item->key)
                        ->setVisibility('public')
                        ->addComment($comment . ' ключ');
                } catch (\Throwable $error) {
                }
            }

            // Обработка EnumType: создание констант для каждого значения
            if ($item instanceof EnumType) {
                $values = $item->values ?? [];

                foreach ($values as $enumKey => $enumValue) {
                    $enumConstSecondName = strtoupper(str_replace('.', '__', CodeGenerator::toSnakeCase($prefix) . CodeGenerator::toSnakeCase($key) . '_ENUM_' . CodeGenerator::toSnakeCase($enumKey)));
                    $enumConstValue = $enumKey;

                    try {
                        $class->addConstant($enumConstSecondName, $enumConstValue)
                            ->setVisibility('public')
                            ->setComment("Enum {$key}: {$enumValue}");
                    } catch (\Throwable $error) {
                    }
                }

                $enumConstSecondName = strtoupper(str_replace('.', '__', CodeGenerator::toSnakeCase($prefix) . CodeGenerator::toSnakeCase($key) . '_ENUM_ALL_KEYS'));
                $enumConstValue = array_keys($values);

                try {
                    $class->addConstant($enumConstSecondName, $enumConstValue)
                        ->setVisibility('public')
                        ->setComment("Enum {$key}");
                } catch (\Throwable $error) {
                }
            }

            if ($item instanceof StructureType) {
                self::addConstantByStructure($class, $item, $prefix . $key . '.');
            }

            if ($item instanceof ArrayOfType && $item->itemType instanceof StructureType) {
                self::addConstantByStructure($class, $item->itemType, $prefix . $key . '.');
            }
        }
    }
}
