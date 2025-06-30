<?php


namespace Shm\ShmDB;


use \MongoDB\Client;
use \MongoDB\Collection;
use \MongoDB\Driver\WriteConcern;
use Shm\ShmUtils\Config;
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

        return $this->collection->find($filter, $options);


        return $result;
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

        return $this->collection->findOne($filter, $options);
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

            // Ensure timestamps are set for inserts and updates
            if ($type === 'insert' || $type === 'updateOne' || $type === 'updateMany') {
                if (!isset($data['created_at'])) {
                    $data['created_at'] = time();
                }

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

        if ($result->getModifiedCount() > 0 && mDB::hasListener($this->collection->getCollectionName(), 'update')) {

            // Используем проекцию для получения только поля _id
            $projection = ['_id' => 1];
            // Извлеките идентификаторы документов, которые были обновлены
            $documentsToUpdate = $this->collection->find($filter, ['projection' => $projection]);

            $idsToUpdate = [];
            foreach ($documentsToUpdate as $document) {
                $idsToUpdate[] = $document['_id'];
            }

            mDB::notifyListeners($this->collection->getCollectionName(), 'update', $idsToUpdate);
        }
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

        if ($result->getModifiedCount() > 0 && mDB::hasListener($this->collection->getCollectionName(), 'update')) {

            // Используем проекцию для получения только поля _id
            $projection = ['_id' => 1];
            // Извлеките идентификаторы документов, которые были обновлены
            $documentsToUpdate = $this->collection->find($filter, ['projection' => $projection]);

            $idsToUpdate = [];
            foreach ($documentsToUpdate as $document) {
                $idsToUpdate[] = $document['_id'];
            }

            mDB::notifyListeners($this->collection->getCollectionName(), 'update', $idsToUpdate);
        }

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

        return $this->updateOne($filter, ['$set' => ['deleted_at' => time()]], $options);

        //  return $this->collection->deleteOne($filter, $options);
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
        return  $this->updateMany($filter, ['$set' => ['deleted_at' => time()]], $options);


        //  return $this->collection->deleteMany($filter, $options);
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
            }

            if (!isset($document['updated_at']) || !$document['updated_at']) {
                $document['updated_at'] = time();
            }

            if (!isset($document['_sortWeight'])) {
                $document['_sortWeight'] = time();
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

        if (!isset($document['updated_at']) || !$document['updated_at']) {
            $document['updated_at'] = time();
        }

        if (!isset($document['_sortWeight']) || !$document['_sortWeight']) {
            $document['_sortWeight'] = time();
        }

        $result = $this->collection->insertOne($document, $options);


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

            if (!isset($document['updated_at']) || !$document['updated_at']) {
                $document['updated_at'] = time();
            }

            if (!isset($document['_sortWeight'])) {
                $document['_sortWeight'] = time();
            }
        }
        $result = $this->collection->insertMany($documents, $options);

        mDB::notifyListeners($this->collection->getCollectionName(), 'insert', $result->getInsertedIds());

        return $result;
    }
}

class mDB
{

    /*
    protected static array $config = [
        'host' => 'localhost',
        'port' => 27017,
        'username' => '',
        'password' => '',
        'database' => '',
        'authSource' => 'admin',
        'poolSize' => 1000,
        'ssl' => false,
        "connectTimeoutMS" => 360000,
        "socketTimeoutMS" => 360000,
    ];

    public static function setConfig(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }
*/

    /**
     * @var array массив слушателей, где ключи - это имена коллекций,
     * а значения - массивы с обратными вызовами
     */
    static $listeners = [];

    /**
     * Добавляет слушатель в реестр слушателей.
     *
     * @param string $collectionName Имя коллекции MongoDB.
     * @param string $operation Тип операции (insert, update).
     * @param callable $callback Обратный вызов, который будет выполнен при уведомлении слушателей.
     */
    public static function addListener(string $collectionName, string $operation, callable $callback)
    {
        $validOperations = ['insert', 'update'];

        if (!in_array($operation, $validOperations)) {
            throw new \InvalidArgumentException("Invalid operation type. Allowed values are: " . implode(', ', $validOperations));
        }

        if (!isset(self::$listeners[$collectionName][$operation])) {
            self::$listeners[$collectionName][$operation] = [];
        }
        self::$listeners[$collectionName][$operation][] = $callback;
    }

    /**
     * Проверяет наличие слушателя для определенной операции и коллекции.
     *
     * @param string $collectionName Имя коллекции MongoDB.
     * @param string $operation Тип операции (insert, update).
     * @return bool
     */
    public static function hasListener(string $collectionName, string $operation): bool
    {
        if (isset(self::$listeners[$collectionName][$operation])) {
            return true;
        }
        return false;
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




    /**
     * Уведомляет всех слушателей о выполнении операции.
     *
     * @param string $collectionName Имя коллекции MongoDB.
     * @param string $operation Тип операции (insert, update).
     * @param array $ids Массив идентификаторов документов, над которыми была выполнена операция.
     */
    public static function notifyListeners(string $collectionName, string $operation, array $ids)
    {
        $validOperations = ['insert', 'update'];

        if (!in_array($operation, $validOperations)) {
            throw new \InvalidArgumentException("Invalid operation type. Allowed values are: " . implode(', ', $validOperations));
        }

        if (isset(self::$listeners[$collectionName][$operation])) {
            foreach (self::$listeners[$collectionName][$operation] as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $ids);
                }
            }
        }
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
            'username' => Config::get("mongodb.username"),
            'password' => Config::get("mongodb.password"),
            'authSource' => Config::get("mongodb.authSource", "admin"),
            'connectTimeoutMS' => Config::get("mongodb.connectTimeoutMS", 360000),
            'socketTimeoutMS' =>  Config::get("mongodb.socketTimeoutMS", 360000),
            'poolSize' => Config::get("mongodb.poolSize", 1000),
            'ssl' => Config::get("mongodb.ssl", false),
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
