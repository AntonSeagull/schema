<?php


namespace Shm\ShmDB;

use InvalidArgumentException;
use \MongoDB\Client;
use \MongoDB\Collection;
use \MongoDB\Driver\WriteConcern;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\RedisStorage;
use Traversable;

class CollectionEvents
{

    /**
     * @var \MongoDB\Collection
     */
    private $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    /**
     * Найти документы в коллекции
     *
     * @param array $filter Фильтр для поиска
     * @param array $options Опции для поиска
     * @return \MongoDB\Driver\Cursor
     */
    public function find(array $filter = [], array $options = [])
    {




        $filter['deleted_at'] = ['$exists' => false];

        $find = $this->collection->find($filter, $options);


        //Если в $options нет того что убирает поля например projection, то тогда кешируем
        if ($find && !isset($options['projection'])) {

            foreach ($find as $doc) {
                mDBRedis::save($this->collection->getCollectionName(), (string)$doc['_id'], $doc);
            }
        }



        return $find;
    }

    /**
     * Найти один документ в коллекции
     *
     * @param array $filter Фильтр для поиска
     * @param array $options Опции для поиска
     * @return array|object|null
     */
    public function findOne(array $filter = [], array $options = [])
    {




        $filter['deleted_at'] = ['$exists' => false];


        $findOne = $this->collection->findOne($filter, $options);

        //Если в $options нет того что убирает поля например projection, то тогда кешируем
        if ($findOne && !isset($options['projection'])) {

            mDBRedis::save($this->collection->getCollectionName(), (string)$findOne['_id'], $findOne);
        }




        return  $findOne;
    }

    /**
     * Perform multiple write operations of different types in one command
     *
     * @param array $operations Array of the operations to perform
     * @param array $options Options for the bulk write operation
     * @return \MongoDB\BulkWriteResult
     */
    public function bulkWrite(array $operations, array $options = []): \MongoDB\BulkWriteResult
    {


        foreach ($operations as &$operation) {
            $type = key($operation);
            $data = $operation[$type];


            if ($type === 'insert') {

                if (!isset($data['created_at'])) {
                    $data['created_at'] = time();
                }

                if (!isset($data['created_by'])) {
                    $data['created_by'] = [
                        'owner' => Auth::getAuthOwner(),
                        'collection' => Auth::getAuthCollection(),
                        'subAccount' => Auth::getSubAccountID(),
                        'apikeyOwner' => Auth::getApiKeyOwner(),
                    ];
                }
            }

            // Ensure timestamps are set for inserts and updates
            if ($type === 'updateOne' || $type === 'updateMany') {
                if (!isset($data['updated_at'])) {
                    $data['updated_at'] = time();
                }
            }
        }

        $result = $this->collection->bulkWrite($operations, $options);

        return $result;
    }



    /**
     * Подсчитать количество документов в коллекции
     *
     * @param array $filter Фильтр для поиска
     * @param array $options Опции для подсчета
     * @return int
     */
    public function count(array $filter = [], array $options = []): int
    {


        return $this->collection->countDocuments($filter, $options);
    }

    /**
     * Подсчитать количество документов в коллекции
     *
     * @param array $filter Фильтр для поиска
     * @param array $options Опции для подсчета
     * @return int
     */
    public function countDocuments(array $filter = [], array $options = []): int
    {



        return $this->collection->countDocuments($filter, $options);
    }

    /**
     * Агрегация документов в коллекции
     *
     * @param array $pipeline Команды агрегации
     * @param array $options Опции для агрегации
     * @return \MongoDB\Driver\Cursor
     */
    public function aggregate(array $pipeline = [], array $options = [])
    {

        mDB::validatePipeline($pipeline);

        $pipeline = array_merge([

            [
                '$match' => [
                    'deleted_at' => ['$exists' => false],
                ]
            ],

        ], $pipeline);


        return $this->collection->aggregate($this->preparePipeline($pipeline), $options);
    }

    private function preparePipeline($stages)
    {


        $priorityStages = [
            '$geoNear',
            '$collStats',
            '$currentOp',
            '$indexStats',
            '$listLocalSessions',
            '$listSessions',
        ];

        $orderedStages = [];

        // Перемещаем приоритетные stages в начало
        foreach ($priorityStages as $priorityStage) {
            foreach ($stages as $key => $stage) {
                if (isset($stage[$priorityStage])) {
                    $orderedStages[] = $stage;
                    unset($stages[$key]);
                }
            }
        }


        // Добавляем оставшиеся stages
        return array_merge($orderedStages, $stages);
    }

    /**
     * Получить уникальные значения поля
     *
     * @param string $field Поле для которого ищем уникальные значения
     * @param array $filter Фильтр для поиска
     * @param array $options Опции для поиска
     * @return array
     */
    public function distinct(string $field, array $filter = [], array $options = []): array
    {



        return $this->collection->distinct($field, $filter, $options);
    }

    /**
     * Обновить документы в коллекции
     *
     * @param array $filter Фильтр для обновления
     * @param array $update Данные для обновления
     * @param array $options Опции для обновления
     * @return \MongoDB\UpdateResult
     */
    public function updateMany(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {


        $update['$set']['updated_at'] = time();


        $result = $this->collection->updateMany($filter, $update, $options);

        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), $filter);

        return $result;
    }

    /**
     * Обновить один документ в коллекции
     *
     * @param array $filter Фильтр для обновления
     * @param array $update Данные для обновления
     * @param array $options Опции для обновления
     * @return \MongoDB\UpdateResult
     */
    public function updateOne(array $filter = [], array $update = [], array $options = []): \MongoDB\UpdateResult
    {



        $update['$set']['updated_at'] = time();

        $result = $this->collection->updateOne($filter, $update, $options);

        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), $filter);

        return $result;
    }

    /**
     * Удалить один документ из коллекции
     *
     * @param array $filter Фильтр для удаления
     * @param array $options Опции для удаления
     * @return \MongoDB\DeleteResult
     */
    public function deleteOne(array $filter = [], array $options = [])
    {

        $updateOne = $this->updateOne($filter, ['$set' => ['deleted_at' => time()]], $options);

        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), $filter);

        return $updateOne;
    }

    /**
     * Удалить множество документов из коллекции
     *
     * @param array $filter Фильтр для удаления
     * @param array $options Опции для удаления
     * @return \MongoDB\DeleteResult
     */
    public function deleteMany(array $filter = [], array $options = [])
    {


        // Устанавливаем поле deleted_at для всех документов, соответствующих фильтру



        $updateMany =  $this->updateMany($filter, ['$set' => ['deleted_at' => time()]], $options);

        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), $filter);

        return $updateMany;
    }

    /**
     * Асинхронная вставка одного документа (без ожидания результата)
     *
     * @param array|object $document Документ для вставки
     * @param array $options Опции для вставки
     * @return void
     */
    public function insertOneAsync($document, array $options = []): void
    {

        if (!isset($document['created_at']) || !$document['created_at']) {
            $document['created_at'] = time();
        }

        if (!isset($document['created_by'])) {
            $document['created_by'] = [
                'owner' => Auth::getAuthOwner(),
                'collection' => Auth::getAuthCollection(),
                'subAccount' => Auth::getSubAccountID(),
                'apikeyOwner' => Auth::getApiKeyOwner(),
            ];
        }

        if (!isset($document['updated_at']) || !$document['updated_at']) {
            $document['updated_at'] = time();
        }

        if (!isset($document['_sortWeight']) || !$document['_sortWeight']) {
            $document['_sortWeight'] = time();
        }

        // Используем writeConcern с уровнем подтверждения 0
        $options['writeConcern'] = new WriteConcern(0);

        $this->collection->insertOne($document, $options);
    }

    /**
     * Асинхронная вставка нескольких документов (без ожидания результата)
     *
     * @param array $documents Документы для вставки
     * @param array $options Опции для вставки
     * @return void
     */
    public function insertManyAsync(array $documents, array $options = []): void
    {

        foreach ($documents as &$document) {
            if (!isset($document['created_at']) || !$document['created_at']) {
                $document['created_at'] = time();


                if (!isset($document['created_by'])) {
                    $document['created_by'] = [
                        'owner' => Auth::getAuthOwner(),
                        'collection' => Auth::getAuthCollection(),
                        'subAccount' => Auth::getSubAccountID(),
                        'apikeyOwner' => Auth::getApiKeyOwner(),
                    ];
                }
            }

            if (!isset($document['updated_at']) || !$document['updated_at']) {
                $document['updated_at'] = time();
            }

            if (!isset($document['_sortWeight'])) {
                $document['_sortWeight'] = round(microtime(true) * 1000);
            }
        }

        // Используем writeConcern с уровнем подтверждения 0
        $options['writeConcern'] = new WriteConcern(0);

        $this->collection->insertMany($documents, $options);
    }



    /**
     * Вставить один документ в коллекцию
     *
     * @param array|object $document Документ для вставки
     * @param array $options Опции для вставки
     * @return \MongoDB\InsertOneResult
     */
    public function insertOne($document, array $options = []): \MongoDB\InsertOneResult
    {



        if (!isset($document['created_at']) || !$document['created_at']) {
            $document['created_at'] = time();
        }

        if (!isset($document['created_by']) || !$document['created_by']) {
            $document['created_by'] = [
                'owner' => Auth::getAuthOwner(),
                'collection' => Auth::getAuthCollection(),
                'subAccount' => Auth::getSubAccountID(),
                'apikeyOwner' => Auth::getApiKeyOwner(),
            ];
        }

        if (!isset($document['updated_at']) || !$document['updated_at']) {
            $document['updated_at'] = time();
        }

        if (!isset($document['_sortWeight']) || !$document['_sortWeight']) {
            $document['_sortWeight'] = time();
        }

        $result = $this->collection->insertOne($document, $options);


        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), ['_id' => $result->getInsertedId()]);

        return $result;
    }

    /**
     * Вставить несколько документов в коллекцию
     *
     * @param array $documents Документы для вставки
     * @param array $options Опции для вставки
     * @return \MongoDB\InsertManyResult
     */
    public function insertMany(array $documents, array $options = []): \MongoDB\InsertManyResult
    {





        foreach ($documents as &$document) {
            if (!isset($document['created_at']) || !$document['created_at']) {
                $document['created_at'] = time();
            }

            if (!isset($document['created_by']) || !$document['created_by']) {
                $document['created_by'] = [
                    'owner' => Auth::getAuthOwner(),
                    'collection' => Auth::getAuthCollection(),
                    'subAccount' => Auth::getSubAccountID(),
                    'apikeyOwner' => Auth::getApiKeyOwner(),
                ];
            }

            if (!isset($document['updated_at']) || !$document['updated_at']) {
                $document['updated_at'] = time();
            }

            if (!isset($document['_sortWeight'])) {
                $document['_sortWeight'] = time();
            }
        }
        $result = $this->collection->insertMany($documents, $options);

        mDBRedis::updateCacheAfterChange($this->collection->getCollectionName(), ['_id' => ['$in' => $result->getInsertedIds()]]);


        return $result;
    }
}

class mDB
{



    public static  function validatePipeline(array $pipeline): void
    {
        if (count($pipeline) === 0) {
            return; // пустой pipeline допустим
        }

        foreach ($pipeline as $i => $stage) {
            if (!is_array($stage)) {
                throw new InvalidArgumentException("Pipeline stage at index $i must be an array. " . json_encode($stage) . json_encode($pipeline));
            }

            if (count($stage) !== 1) {
                throw new InvalidArgumentException("Pipeline stage at index $i must have exactly one operator." . " Found " . count($stage) . " operators." . json_encode($stage) . json_encode($pipeline));
            }

            $operator = array_key_first($stage);
            if (strpos($operator, '$') !== 0) {
                throw new InvalidArgumentException("Pipeline stage at index $i must start with \$ (got '$operator'). " . json_encode($stage) . json_encode($pipeline));
            }
        }
    }




    /**
     * Создает индекс в указанной коллекции.
     *
     * @param string $collectionName Имя коллекции MongoDB.
     * @param array $keys Поля, по которым будет создан индекс.
     * @param array $options Опции для создания индекса.
     * @return string Имя созданного индекса.
     */
    public static function createIndex(string $collectionName, array $keys, array $options = [])
    {
        if (!self::$database) {
            self::connect();
        }

        $collection = self::$database->$collectionName;
        return $collection->createIndex($keys, $options);
    }


    /**
     * Получить все индексы коллекции
     *
     * @param string $collectionName Имя коллекции MongoDB.
     * @return \MongoDB\Driver\Cursor
     */

    public static function getIndexes(string $collectionName)
    {
        if (!self::$database) {
            self::connect();
        }

        $collection = self::$database->$collectionName;
        return $collection->listIndexes();
    }





    protected static $client;
    public static $database;

    public static $queryCount = 0;

    public static function isMongoId($id)
    {
        if (is_string($id) && strlen($id) === 24) {
            return ctype_xdigit($id);
        }
        return false;
    }

    public static function connect()
    {


        $params = [
            // ====== Аутентификация ======
            // Имя пользователя MongoDB
            'username' => Config::get('mongodb.username'),

            // Пароль пользователя MongoDB
            'password' => Config::get('mongodb.password'),

            // База аутентификации, где хранится пользователь (часто "admin")
            'authSource' => Config::get('mongodb.authSource', 'admin'),

            // ====== Сетевые таймауты и выбор сервера ======
            // Таймаут установления TCP-соединения (мс). 5–10 сек достаточно для продакшна.
            'connectTimeoutMS' => Config::get('mongodb.connectTimeoutMS', 10000),

            // Таймаут выбора сервера кластера (мс). Сколько ждать доступного узла (primary/secondary).
            // Помогает быстрее «падать» при недоступности кластера.
            'serverSelectionTimeoutMS' => Config::get('mongodb.serverSelectionTimeoutMS', 10000),

            // Таймаут бездействия запроса по сокету (мс). 30–60 сек — типичный диапазон для веб-бэкенда.
            'socketTimeoutMS' => Config::get('mongodb.socketTimeoutMS', 60000),

            // ====== Пул соединений ======
            // Максимальный размер пула соединений на один процесс/воркер приложения.
            // Начните с 50 (или 20–100) и корректируйте по метрикам.
            'maxPoolSize' => Config::get('mongodb.maxPoolSize', 50),

            // Минимальный размер пула — помогает прогреть коннекты, но без нужды держать 0–5.
            'minPoolSize' => Config::get('mongodb.minPoolSize', 0),

            // Максимальное время простоя соединения в пуле (мс), после которого соединение закрывается.
            'maxIdleTimeMS' => Config::get('mongodb.maxIdleTimeMS', 60000),

            // ====== Надёжность и политика чтения/записи ======
            // Повторять операции записи при временных сетевых сбоях (idempotent/безопасные места).
            'retryWrites' => Config::get('mongodb.retryWrites', true),

            // Повторять операции чтения при временных сбоях.
            'retryReads'  => Config::get('mongodb.retryReads', true),

            // Предпочтение чтения. Для транзакционной целостности используйте 'primary'.
            // В случаях аналитики/реплик — 'secondary' или 'primaryPreferred' по осознанной необходимости.
            'readPreference' => Config::get('mongodb.readPreference', 'primary'),

            // Уровень подтверждения записи. 'majority' — баланс надёжности и производительности.
            'w' => Config::get('mongodb.w', 'majority'),

            // Требовать журналирования записи (journaling). true — повышает устойчивость к сбоям.
            'journal' => Config::get('mongodb.journal', true),


            // ====== Безопасность и производительность ======
            // Шифрование трафика (TLS). Для продакшна обязательно true.
            // В новых драйверах ключ называется 'tls', старое 'ssl' алиасно. Укажем оба для ясности.
            'tls' => Config::get('mongodb.tls', false),
            'ssl' => Config::get('mongodb.ssl', false), // совместимость
            // Сжатие сетевого трафика. Zstd обычно эффективнее, затем Snappy.
            'compressors' => Config::get('mongodb.compressors', 'zstd,snappy'),



        ];



        $host = Config::get("mongodb.host", 'localhost');
        $port = Config::get("mongodb.port", 27017);
        $server = "mongodb://$host:$port";



        self::$client = new Client($server, $params);

        $db = Config::get("mongodb.database", 'default_db');
        self::$database = self::$client->$db;
    }

    public function isValid($value)
    {
        if ($value instanceof \MongoDB\BSON\ObjectID) {
            return true;
        }
        try {
            new \MongoDB\BSON\ObjectID($value);
            return true;
        } catch (\Exception $e) {

            return false;
        }
    }

    public static function id($val)
    {

        if ($val && gettype($val) == 'string') {
            return self::getId($val);
        } else {
            return $val;
        }
    }

    public static function getId($id = false)
    {
        if ($id) {
            $id = (string) $id;

            if (preg_match('/^[0-9a-f]{24}$/i', $id) === 1) {
                return $id ? new \MongoDB\BSON\ObjectID($id) : new \MongoDB\BSON\ObjectID();
            } else {
                return false;
            }
        }
    }

    public static function  bsonDocumentToArray($document)
    {
        if ($document instanceof \MongoDB\Model\BSONDocument || $document instanceof \MongoDB\Model\BSONArray) {
            $array = $document->getArrayCopy();
            foreach ($array as $key => $value) {
                $array[$key] = self::bsonDocumentToArray($value);
            }
            return $array;
        }
        return $document;
    }



    public static function collection($collection): CollectionEvents
    {



        if (!self::$database) {
            self::connect();
        }

        $collection = self::$database->$collection;

        return new CollectionEvents($collection);
    }

    public static function _collection($collection): Collection
    {




        if (!self::$database) {
            self::connect();
        }

        return self::$database->$collection;
    }

    public static function replaceObjectIdsToString($data)
    {
        if (!$data) {
            return $data;
        }


        if (!(is_object($data) || is_array($data) || $data instanceof Traversable)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ($value instanceof \MongoDB\Model\BSONArray) {
                $data[$key] = self::replaceObjectIdsToString($value->getArrayCopy());
            } elseif ($value instanceof \MongoDB\Model\BSONDocument) {
                $data[$key] = self::replaceObjectIdsToString($value->getArrayCopy());
            } elseif ($value instanceof \MongoDB\BSON\ObjectID) {
                $data[$key] = (string) $value;
            } else if (is_array($value)) {
                $data[$key] = self::replaceObjectIdsToString($value);
            }
        }
        return $data;
    }

    public static function hashDocuments($documents): string | null
    {

        if (!$documents) return md5("empty");
        if (isset($documents['_id'])) {
            $documents = [$documents];
        }


        $hash = [];
        foreach ($documents as $order) {
            $id = $order['_id'] ?? null;
            $updated_at = $order['updated_at'] ?? null;
            $hash[] = $id . $updated_at;
        }

        $hash =  md5(implode('-', $hash));

        return $hash;
    }


    public static function replaceStringToObjectIds($data)
    {
        if (!$data) {
            return $data;
        }


        if (!(is_object($data) || is_array($data) || $data instanceof Traversable)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::replaceStringToObjectIds($value);
            } else {
                if (is_string($value) && preg_match('/^[0-9a-f]{24}$/', $value)) {
                    $data[$key] = new \MongoDB\BSON\ObjectID($value);
                }
            }
        }
        return $data;
    }
}
