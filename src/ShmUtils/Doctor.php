<?php

namespace Shm\ShmUtils;

use Nette\PhpGenerator\ClassType;
use Shm\Shm;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\StructureType;

class Doctor
{

    public static function cmdInit()
    {



        Cmd::command("doctor", function () {
            echo "Что выполнить? (index / fields / links /makeConfig: ";
            $input = trim(readline()); // читаем ввод из консоли

            switch ($input) {

                case 'index':
                    self::ensureSortWeightIndex();
                    break;
                case 'fields':
                    self::fieldClasses();
                    break;
                case 'links':
                    self::createSymlinks();
                    break;
                case "makeConfig":
                    self::makeConfig();
                    break;
                default:
                    echo "Неизвестная команда: $input\n";
                    break;
            }
        });
    }


    private static function makeConfig()
    {


        $dir =  ShmInit::$rootDir . '/config';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/config.php';
        if (file_exists($file)) {
            //  echo "Config file already exists: $file\n";
            return;
        }
        $content = "<?php
            return [
                'mongodb' => [
                  'host' => 'localhost',
                    'port' => 27017,
                    'username' => '',
                    'password' => '',
                    'database' => '',
                    'authSource' => 'admin',
                    'poolSize' => 1000,
                    'ssl' => false,
                    'connectTimeoutMS' => 360000,
                    'socketTimeoutMS' => 360000,
                ],
                
                'redis' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'password' => null,
                ],
                'sentry' => [
                    'dsn' => '',
                    'environment' => 'production',
                ],
                'socket' => [
                    'domain' => '',
                    'prefix' => 'test'
                ],
                's3' => [
                    'bucket' => '',
                    'version' => 'latest',
                    'region' =>  '',
                    'endpoint' => '',
                    'credentials' => [
                        'key' => '',
                        'secret' => '',
                    ],
                 ]
                
            ];
            ";
        file_put_contents($file, $content);
        echo "Config file created: $file\n";
        echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    }


    private static function  structures()
    {

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


            return $classes;
        }
    }

    private static function ensureSortWeightIndex()
    {

        $structures = self::structures();



        foreach ($structures as $structure) {


            if ($structure->collection && $structure::structure()->manualSort) {

                $collection = mDB::_collection($structure->collection);


                // Получаем все индексы коллекции
                $indexes = $collection->listIndexes();

                // Ищем индекс по полю "_sortWeight"
                foreach ($indexes as $index) {
                    if (!empty($index['key']['_sortWeight'])) {
                        return;
                    }
                }

                // Если не найден — создаём индекс
                $collection->createIndex(['_sortWeight' => 1]);
            }
        }
    }

    private static function fieldClasses()
    {

        $structures = self::structures();

        $dir = ShmInit::$rootDir . '/app/FieldClasses/';

        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $item;

                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        foreach ($structures as $structure) {

            self::generateFieldClass($structure::structure());
        }
    }



    private static function toSnakeCase(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private static function addConstantByStructure(ClassType $class, StructureType $structure, $prefix = '')
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


    private static function generateFieldClass(StructureType $structure)
    {



        $structure->updateKeys();
        $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));

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

        file_put_contents($filePath, $output);
    }


    public static function doctor()
    {

        self::ensureSortWeightIndex();
        self::fieldClasses();
        self::createSymlinks();
    }

    public static function createSymlinks()
    {

        $links = [
            ShmInit::$rootDir . '/public/static/js/main.js' => ShmInit::$shmDir . '/../assets/admin/static/js/main.js',
            ShmInit::$rootDir . '/public/static/css/main.css' => ShmInit::$shmDir . '/../assets/admin/static/css/main.css',
        ];

        $isCli = (php_sapi_name() === 'cli');

        foreach ($links as $target => $source) {
            $targetDir = dirname($target);

            // Проверяем, существует ли исходный файл
            if (!file_exists($source)) {
                if (is_link($target) || file_exists($target)) {
                    unlink($target);
                    if ($isCli) {
                        echo "Удалён старый файл/ссылка, исходный файл отсутствует: $target\n";
                    }
                }
                if ($isCli) {
                    echo "❌ Исходный файл не найден: $source\n";
                }
                continue; // пропускаем создание ссылки
            }

            // Создаём директорию, если её нет
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
                if ($isCli) {
                    echo "Создана директория: $targetDir\n";
                }
            }

            // Удаляем старый файл/ссылку, если есть
            if (is_link($target) || file_exists($target)) {
                unlink($target);
                if ($isCli) {
                    echo "Удалён старый файл/ссылка: $target\n";
                }
            }

            // Создаём новую символическую ссылку
            if (!symlink($source, $target)) {
                if ($isCli) {
                    echo "❌ Не удалось создать ссылку: $target -> $source\n";
                }
            } else {
                if ($isCli) {
                    echo "✅ Создана ссылка: $target -> $source\n";
                }
            }
        }
    }
}
