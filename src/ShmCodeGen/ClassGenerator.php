<?php

namespace Shm\ShmCodeGen;

use Nette\PhpGenerator\Method;
use Shm\Shm;
use Shm\ShmCmd\Cmd;
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


    public static function generate(StructureType $strucutre)
    {



        $strucutre->updateKeys("");

        $className = ucfirst(str_replace([' ', '-', '_'], '', $strucutre->collection));


        $dir =  ShmInit::$rootDir . '/app/DataClasses/' . $className . 'Data/';


        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }






        $data = self::getClassByStructure($className, $strucutre, true);



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

        /*
            foreach ($collections as $collection) {

            $className = ucfirst(str_replace([' ', '-', '_'], '', $collection->collection));

            $dir = Core::$ROOT_PATH . 'app/FieldPathClasses/' . $className . 'FieldPath/';


            if (!is_dir($dir)) {
                mkdir($dir, 0777, true); // создать все вложенные папки
            }

            $data = self::getFieldPathClassByStructure('', $className, $collection->expect());

            foreach ($data as $classFullName => $classText) {

                $filePath = $dir . $classFullName . '.php';


                $output = "<?php\n\nnamespace App\\FieldPathClasses\\" . $className . "FieldPath;\n\n"; // заголовок файла


                $output .= $classText . "\n\n"; // собираем все классы
                // Теперь просто сохраняем всё в файл
                file_put_contents($filePath, $output);

                echo "Файл создан: $filePath\n";
            }
        }*/


        //FieldPath

    }
}
