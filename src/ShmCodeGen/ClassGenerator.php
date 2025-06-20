<?php

namespace Shm\ShmCodeGen;

use Error;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Shm\Shm;
use Shm\ShmCmd\Cmd;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmInit;

class ClassGenerator
{



    public static function cmdInit()
    {



        Cmd::command("generate-classes",  function () {



            $classes = [];

            if (is_dir(ShmInit::$rootDir . '/app/Collections')) {




                $files = scandir(ShmInit::$rootDir . '/app/Collections');
                foreach ($files as $file) {
                    if (!in_array($file, ['.', '..']) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                        $className = str_replace('.php', '', $file);
                        $fullClassName = 'App\\Collections\\' . $className;


                        $class = new $fullClassName();

                        $classes[] = $class;
                    }
                }


                //TODO: Mare remove all classes from /app/DataClasses/ before generate new classes
                foreach ($classes as $class) {

                    //   if ($class instanceof StructureType) {
                    self::generate($class::structure());
                    // }
                }

                echo "Classes generated successfully.\n";
            }
        });
    }


    private static function getClassName(string $className)
    {
        return ucfirst(str_replace([' ', '-', '_'], '', $className));
    }





    private static function getClassByStructure(string $className, StructureType $structure, $rootClass = false)
    {


        $result = [];

        $class = new \Nette\PhpGenerator\ClassType($className . 'Data');

        $method = $class->addMethod('__construct');
        $method->addParameter('data', null);
        $method->addBody('$this->data = $data;');

        $class->addProperty('data')
            ->setVisibility('private'); // делаем приватным

        if ($rootClass) {
            $method = $class->addMethod('create');
            $method->setStatic();
            $method->setReturnType('self');

            // Добавляем параметр $data с дефолтным значением null
            $method->addParameter('data')->setDefaultValue(null);

            // Тело метода
            $method->setBody('return new static($data);');


            // 2. Метод setData
            $setDataMethod = $class->addMethod('setData');
            $setDataMethod->setReturnType('static');

            // Аргумент метода
            $setDataMethod->addParameter('data');

            // Тело метода
            $setDataMethod->addBody('$this->data = $data;');
            $setDataMethod->addBody('return $this;');
        }



        $method = $class->addMethod("get")
            ->addComment('Получить данные');


        $method->addComment('@return array | object | null');
        $method->addBody('return $this->data;');





        foreach ($structure->items as $key => $item) {



            if ($item instanceof StructureType) {


                $insideClassName = $className . ucfirst($key);

                //Проверка нет ли в начале уже 'Inner'. если не то добавляем
                if (strpos($insideClassName, 'Inner') !== 0) {
                    $insideClassName = 'Inner' . $insideClassName;
                }

                $newInsideClass = self::getClassByStructure($insideClassName, $item);

                $result =  array_merge($result, $newInsideClass);


                $class->addMethod($key)
                    ->addComment('Получить ' . $key)
                    ->addBody('return new ' . $insideClassName . 'Data(DeepAccess::safeGet("' . $key . '", $this->data));')

                    ->setReturnType('?' . $insideClassName . 'Data');



                continue;
            }


            $class->addMember($item->phpGetter());
        }


        $result[$className . 'Data'] = $class;
        return $result;
    }

    private static function toSnakeCase(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    public static function addConstantByStructure(ClassType $class, StructureType $structure, $prefix = '')
    {


        foreach ($structure->items as $key => $item) {

            $constName = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . self::toSnakeCase($key)));

            try {
                $class->addConstant($constName,  $prefix . $item->key)
                    ->setVisibility('public');
            } catch (\Throwable $error) {
            }

            $method = $class->addMethod($constName . '_GET');
            $method->setStatic();
            $method->setVisibility('public');
            $method->addParameter('data');
            $method->addParameter('defaultValue')->setDefaultValue(null);
            $method->setBody('return DeepAccess::get($data, self::' . $constName . ', $defaultValue);');

            if ($prefix) {
                $constNameOneKey = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . self::toSnakeCase($key) . '_KEY'));


                try {
                    $class->addConstant($constNameOneKey,  $item->key)
                        ->setVisibility('public');
                } catch (\Throwable $error) {
                }
            }


            // Обработка EnumType: создание констант для каждого значения
            if ($item instanceof EnumType) {
                $values = $item->values ?? [];

                foreach ($values as $enumKey => $enumValue) {
                    $enumConstName = strtoupper(str_replace('.', '__', $prefix . $key . '_ENUM_' . self::toSnakeCase($enumKey)));
                    $enumConstValue = $enumKey;

                    try {
                        $class->addConstant($enumConstName, $enumConstValue)
                            ->setVisibility('public')
                            ->setComment("Enum {$key}: {$enumValue}");
                    } catch (\Throwable $error) {
                        // логировать по желанию
                    }
                }
            }


            if ($item instanceof StructureType) {
                self::addConstantByStructure($class, $item, $prefix . $key . '.');
            }

            if ($item instanceof ArrayOfType && $item->itemType instanceof StructureType) {
                self::addConstantByStructure($class, $item->itemType,  $prefix . $key . '.');
            }
        }
    }


    public static function generate(StructureType $structure)
    {



        $structure->updateKeys();
        $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));
        /*   $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));


        $dir =  ShmInit::$rootDir . '/app/DataClasses/' . $className . 'Data/';


        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }






        $data = self::getClassByStructure($className, $structure, true);



        foreach ($data as $classFullName => $classText) {

            $filePath = $dir . $classFullName . '.php';


            $output = "<?php\n\nnamespace App\\DataClasses\\" . $className . "Data;\n\n"; // заголовок файла

            $output .= "use App\Collections\\$className;\n\n";

            $output .= "use Shm\ShmUtils\DeepAccess;\n\n"; // импортируем класс StructureType

            $output .= $classText . "\n\n"; // собираем все классы
            // Теперь просто сохраняем всё в файл
            file_put_contents($filePath, $output);

            echo "Файл создан: $filePath\n";
        }


*/
        $dir =  ShmInit::$rootDir . '/app/FieldClasses/';


        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }


        $classFullName = $className . 'Field';

        $class = new \Nette\PhpGenerator\ClassType($classFullName);


        self::addConstantByStructure($class, $structure);



        $filePath = $dir . $classFullName . '.php';


        $output = "<?php\n\nnamespace App\\FieldClasses;\n\n"; // заголовок файла
        $output .= "use Shm\ShmUtils\DeepAccess;\n\n"; // импортируем класс StructureType
        $output .= $class . "\n\n"; // собираем все классы
        // Теперь просто сохраняем всё в файл
        file_put_contents($filePath, $output);

        echo "Файл создан: $filePath\n";
    }
}
