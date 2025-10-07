<?php

namespace Shm\ShmUtils;

use Nette\PhpGenerator\ClassType;
use Shm\Shm;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\StructureType;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use Nette\PhpGenerator\Helpers;

class Doctor
{

    public static function cmdInit()
    {



        Cmd::command("doctor", function () {

            $menu = (new CliMenuBuilder)
                ->setTitle('Select an action')
                ->addItem('Update FieldClasses', function () {
                    self::fieldClasses();
                    exit;
                })

                ->addItem('Update ItemClasses', function () {
                    self::itemsClasses();
                    exit;
                })



                ->addItem('Update MongoDB Indexes', function () {
                    self::ensureSortWeightIndex();
                    exit;
                })

                ->addItem('Create Admin Symlinks', function () {
                    self::createSymlinks();
                    exit;
                })
                ->addItem('Create Config.php', function () {
                    self::makeConfig();
                    exit;
                })


                ->build();

            $menu->open();
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
                        // Хост MongoDB (обычно localhost или адрес сервера/кластера)
                        'host' => 'localhost',

                        // Порт MongoDB (по умолчанию 27017)
                        'port' => 27017,

                        // Имя пользователя для подключения
                        'username' => '',

                        // Пароль пользователя
                        'password' => '',

                        // База данных по умолчанию, к которой будет подключаться приложение
                        'database' => '',

                        // ====== Аутентификация ======
                        //'authSource' => 'admin',
                        // База, где хранится пользователь (часто «admin»).
                        // Если пользователь создан в другой БД — укажите её.

                        // ====== Сетевые таймауты и выбор сервера ======
                        //'connectTimeoutMS' => 10000,     
                        // Таймаут подключения (мс). Обычно 5–10 секунд.

                        //'serverSelectionTimeoutMS' => 10000, 
                        // Сколько ждать выбора доступного узла (primary/secondary).
                        // Помогает быстрее «падать» при недоступности кластера.

                        //'socketTimeoutMS' => 60000,      
                        // Таймаут ожидания ответа от сервера (мс).
                        // Обычно 30–60 секунд для веб-приложений.

                        // ====== Пул соединений ======
                        //'maxPoolSize' => 50,             
                        // Максимальное количество соединений в пуле (на процесс).
                        // Оптимально 20–100, начинать можно с 50.

                        //'minPoolSize' => 0,              
                        // Минимальное количество соединений в пуле.
                        // 0–5 достаточно, чтобы не держать лишние коннекты.

                        //'maxIdleTimeMS' => 60000,        
                        // Максимальное время простоя соединения в пуле (мс).
                        // После этого оно закрывается. Обычно 60 секунд.

                        // ====== Надёжность и политика чтения/записи ======
                        //'retryWrites' => true,           
                        // Повторять операции записи при временных сетевых сбоях.

                        //'retryReads'  => true,           
                        // Повторять операции чтения при временных сбоях.

                        //'readPreference' => 'primary',   
                        // Политика чтения. Для целостности — 'primary'.
                        // Для реплик может использоваться 'secondary' или 'primaryPreferred'.

                        //'w' => 'majority',               
                        // Уровень подтверждения записи. 'majority' = большинство узлов.
                        // Баланс между надёжностью и скоростью.

                        //'journal' => true,               
                        // Требовать журналирования записи. Повышает устойчивость к сбоям.

                        // ====== Безопасность и производительность ======
                        //'tls' => true,                   
                        // Включение TLS (шифрование). Для продакшна обязательно true.

                        //'ssl' => true,                   
                        // Параметр-синоним для совместимости со старыми драйверами.

                        //'compressors' => 'zstd,snappy',  
                        // Сжатие трафика. Zstd обычно эффективнее, затем Snappy.

                        
                        // ====== Топология кластера ======
                        // 'replicaSet' => 'rs0',
                        // Имя replica set (нужно для подключения к кластеру, если не используете SRV).

                        // ====== Дополнительно ======
                        // 'readConcernLevel' => 'local',
                        // Уровень консистентности чтения. Обычно 'local' достаточно.
                        // Для строгой консистентности — 'majority' или 'linearizable'.

                        // 'maxTimeMS' => 0,
                        // Максимальное время выполнения операции (мс).
                        // 0 = без ограничения.
                    ],
                'validKey' => true,


                //Время после которого RPC запрос считается медленным, записываются в коллекцию _rpc_slow_requests
                'slowRequestTime' => 1000, //в миллисекундах

                'smtp'=>[
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls', // или 'ssl'
                    'from_email' => 'noreply@example.com',
                    'from_name' => 'Example App',
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

        $isCli = (php_sapi_name() === 'cli');

        $structures = self::structures();




        foreach ($structures as $structure) {




            $indexes = $structure::structure()->createIndex();

            if (count($indexes) > 0) {


                $collection = mDB::_collection($structure->collection);


                foreach ($indexes as $indexKey => $type) {






                    $hasIndex = false;

                    foreach ($collection->listIndexes() as $index) {
                        if (isset($index['key'][$indexKey]) && $index['key'][$indexKey] === $type) {
                            $hasIndex = true;
                            break;
                        }
                    }

                    if (!$hasIndex) {
                        $collection->createIndex([$indexKey =>  $type]);
                        if ($isCli)
                            echo "Index created: {$indexKey} => {$type} in {$structure->collection}" . PHP_EOL;
                    }
                }
            }


            if ($structure->collection && $structure::structure()->manualSort) {


                if ($isCli)
                    echo "Creating index for _sortWeight in {$structure->collection}" . PHP_EOL;

                $collection = mDB::_collection($structure->collection);


                $indexes = $collection->listIndexes();

                $findIndex = false;

                foreach ($indexes as $index) {


                    if (isset($index['key']['_sortWeight'])) {
                        $findIndex = true;
                        break;
                    }
                }


                if (!$findIndex)
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

                //  $path = $dir . DIRECTORY_SEPARATOR . $item;

                //   if (is_file($path)) {
                //       unlink($path);
                //  }
            }
        }

        foreach ($structures as $structure) {

            self::generateFieldClass($structure::structure());
        }
    }

    private static function itemsClasses()
    {

        $structures = self::structures();

        $dir = ShmInit::$rootDir . '/app/ItemsClasses/';

        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                //  $path = $dir . DIRECTORY_SEPARATOR . $item;

                // if (is_file($path)) {
                //    unlink($path);
                //  }
            }
        }

        foreach ($structures as $structure) {

            self::generateItemClass($structure::structure());
        }
    }



    private static function toSnakeCase(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private static function addConstantByStructure(ClassType $class, StructureType $structure, $prefix = '')
    {


        foreach ($structure->items as $key => $item) {

            if ($key == '*') {
                continue;
            }

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
                    $enumConstName = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . $key . '_ENUM_' . self::toSnakeCase($enumKey)));
                    $enumConstSecondName = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . self::toSnakeCase($key) . '_ENUM_' . self::toSnakeCase($enumKey)));

                    $enumConstValue = $enumKey;

                    try {
                        $class->addConstant($enumConstName, $enumConstValue)
                            ->setVisibility('public')
                            ->setComment($enumConstName !== $enumConstSecondName ? "@deprecated Enum {$key}: {$enumValue}" : "Enum {$key}: {$enumValue}");

                        if ($enumConstName !== $enumConstSecondName)
                            $class->addConstant($enumConstSecondName, $enumConstValue)
                                ->setVisibility('public')
                                ->setComment("Enum {$key}: {$enumValue}");
                    } catch (\Throwable $error) {
                        // логировать по желанию
                    }
                }


                $enumConstName = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . $key . '_ENUM_ALL_KEYS'));
                $enumConstSecondName = strtoupper(str_replace('.', '__', self::toSnakeCase($prefix) . self::toSnakeCase($key) . '_ENUM_ALL_KEYS'));
                $enumConstValue = array_keys($values);

                try {
                    $class->addConstant($enumConstName, $enumConstValue)
                        ->setVisibility('public')
                        ->setComment($enumConstName !== $enumConstSecondName ? "@deprecated Enum {$key}" : "Enum {$key}");

                    if ($enumConstName !== $enumConstSecondName)
                        $class->addConstant($enumConstSecondName, $enumConstValue)
                            ->setVisibility('public')
                            ->setComment("Enum {$key}");
                } catch (\Throwable $error) {
                    // логировать по желанию
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


    private static function addVarByStructure(ClassType $class, StructureType $structure, string $prefix = ''): void
    {
        $keys = [];

        $shape = [];
        foreach ($structure->items as $rawKey => $item) {
            if ($rawKey === '*' || $rawKey === '_id') {
                continue;
            }

            // применим префикс (если надо)
            $name = $rawKey;



            $keys[] = $name;

            // свойство
            $prop = $class->addProperty($name, $item->default ?? null)
                ->setPublic()

                ->setComment((string) $item->title);

            // если знаем тип — укажем (пример, адаптируй маппинг)
            if (!empty($item->type)) {
                $mapped = self::mapType((string) $item->type); // например: int|string|array|float|bool
                if ($mapped !== null) {
                    $shape[] = "    $name?: $mapped";
                    $prop->setType($mapped)->setNullable(true); // теперь nullable имеет смысл
                } else {
                    $shape[] = "    $name?: mixed";
                }
            }

            // метод-сеттер
            $m = $class->addMethod($name) // либо 'set' . ucfirst($name)
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

    /**
     * Пример маппинга типов из структуры в PHP-типы
     */
    private static function mapType(string $t): ?string
    {
        return match (strtolower($t)) {
            'int', 'integer' => 'int',
            'string', 'text'         => 'string',
            'float', 'double', 'number' => 'float',
            'bool', 'boolean' => 'bool',
            'array', 'list', 'object' => 'array', // при отсутствии детальной схемы
            default => null, // если неизвестно — без типа
        };
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

    private static function generateItemClass(StructureType $structure)
    {



        $structure->updateKeys();
        $className = ucfirst(str_replace([' ', '-', '_'], '', $structure->collection));

        $dir =  ShmInit::$rootDir . '/app/ItemClasses/';


        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }


        $classFullName = $className . 'Item';

        $class = new \Nette\PhpGenerator\ClassType($classFullName);


        self::addVarByStructure($class, $structure);



        $filePath = $dir . $classFullName . '.php';


        $output = "<?php\n\nnamespace App\\ItemClasses;\n\n"; // заголовок файла
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
            ShmInit::$rootDir . '/public/static/main.js' => ShmInit::$shmDir . '/../assets/admin/static/main.js',
            ShmInit::$rootDir . '/public/static/main.css' => ShmInit::$shmDir . '/../assets/admin/static/main.css',
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
