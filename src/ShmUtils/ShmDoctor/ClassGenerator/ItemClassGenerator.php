<?php

namespace Shm\ShmUtils\ShmDoctor\ClassGenerator;

use Nette\PhpGenerator\ClassType;
use Shm\ShmUtils\ShmInit;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmDoctor\Utils\CodeGenerator;

class ItemClassGenerator
{
    /**
     * Generate item classes for all structures
     */
    public static function generateAll(): void
    {
        $structures = \Shm\ShmUtils\ShmDoctor\Utils\StructureHelper::getStructures();

        foreach ($structures as $structure) {
            self::generate($structure::structure());
        }
    }

    /**
     * Generate item class for a structure
     */
    public static function generate(StructureType $structure): void
    {
        $structure->updateKeys();
        $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));
        $dir = ShmInit::$rootDir . '/app/ItemClasses/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $classFullName = $className . 'Item';
        $class = new ClassType($classFullName);

        self::addVarByStructure($class, $structure);

        $filePath = $dir . $classFullName . '.php';
        $output = "<?php\n\nnamespace App\\ItemClasses;\n\n";
        $output .= "use Shm\ShmUtils\DeepAccess;\n\n";
        $output .= $class . "\n\n";

        file_put_contents($filePath, $output);
    }

    /**
     * Add variables and methods to class based on structure
     */
    private static function addVarByStructure(ClassType $class, StructureType $structure, string $prefix = ''): void
    {
        $keys = [];
        $shape = [];

        foreach ($structure->items as $rawKey => $item) {
            if ($rawKey === '*' || $rawKey === '_id') {
                continue;
            }

            $name = $rawKey;
            $keys[] = $name;

            // свойство
            $prop = $class->addProperty($name, $item->getDefault() ?? null)
                ->setPublic()
                ->setComment((string) $item->title);

            // если знаем тип — укажем
            if (!empty($item->type)) {
                $mapped = CodeGenerator::mapType((string) $item->type);
                if ($mapped !== null) {
                    $shape[] = "    $name?: $mapped";
                    $prop->setType($mapped)->setNullable(true);
                } else {
                    $shape[] = "    $name?: mixed";
                }
            }

            // метод-сеттер
            $m = $class->addMethod($name)
                ->setPublic()
                ->setReturnType('self')
                ->setComment((string) $item->title);

            $param = $m->addParameter('value');

            if (!empty($item->type) && isset($mapped) && $mapped !== null) {
                $param->setType($mapped);
            }

            $m->setBody('$this->' . $name . ' = $value;' . "\n" . 'return $this;');
        }

        // добавим data() только если его ещё нет
        if (!$class->hasMethod('data')) {
            $method = $class->addMethod('data')
                ->setPublic()
                ->setReturnType('array');

            $bodyLines = [];
            foreach ($keys as $name) {
                $bodyLines[] = "'" . $name . "' => \$this->" . $name . ",";
            }
            $method->setBody("return [\n" . implode("\n", $bodyLines) . "\n];");
        }

        // генерируем конструктор
        $ctor = $class->addMethod('__construct')
            ->setPublic();

        $ctor->addParameter('values', null)
            ->setType('array')
            ->setNullable(true);

        $body = "if (\$values) {\n";
        foreach ($keys as $name) {
            $body .= "    if (array_key_exists('$name', \$values)) {\n";
            $body .= "        \$this->$name = \$values['$name'];\n";
            $body .= "    }\n";
        }
        $body .= "}";
        $ctor->setBody($body);
        $doc = "/**\n * @param array{\n" . implode(",\n", $shape) . "\n * }|null \$values\n */";

        $ctor->setComment($doc);
    }
}
