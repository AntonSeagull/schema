<?php

namespace Shm\ShmDB;

use Shm\Shm;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\ShmInit;

class mDBLite
{
    public static \PDO $pdo;
    protected static array $collections = [];


    public static function connect(): void
    {

        $path = ShmInit::$rootDir . '/' . Config::get('mongodbLite.sqlite', 'database/database.sqlite');

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }


        if (!isset(self::$pdo)) {
            self::$pdo = new \PDO('sqlite:' . $path);
            self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
    }

    public static function collection(string $name): MiniCollection
    {
        self::connect();

        if (!isset(self::$collections[$name])) {
            self::$collections[$name] = new MiniCollection($name);
        }

        return self::$collections[$name];
    }
}

class MiniCollection
{
    private string $table;

    public function __construct(string $name)
    {
        $this->table = 'coll_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            _id TEXT PRIMARY KEY,
            document TEXT NOT NULL
        )";
        mDBMini::$pdo->exec($sql);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(12));
    }

    public function find(array $filter = []): array
    {
        $stmt = mDBMini::$pdo->query("SELECT document FROM {$this->table}");
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $results = [];
        foreach ($rows as $json) {
            $doc = json_decode($json, true);
            if ($this->matchFilter($doc, $filter)) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    public function findOne(array $filter = []): ?array
    {
        foreach ($this->find($filter) as $doc) {
            return $doc;
        }
        return null;
    }

    public function insertOne(array $document): string
    {
        $document['_id'] ??= $this->generateId();
        $json = json_encode($document, JSON_UNESCAPED_UNICODE);
        $stmt = mDBMini::$pdo->prepare("INSERT INTO {$this->table} (_id, document) VALUES (:id, :doc)");
        $stmt->execute(['id' => $document['_id'], 'doc' => $json]);
        return $document['_id'];
    }

    public function updateOne(array $filter, array $update): int
    {
        $updated = 0;
        $docs = $this->find($filter);
        foreach ($docs as $doc) {
            $id = $doc['_id'];
            $doc = $this->applyUpdate($doc, $update);
            $json = json_encode($doc, JSON_UNESCAPED_UNICODE);
            $stmt = mDBMini::$pdo->prepare("UPDATE {$this->table} SET document = :doc WHERE _id = :id");
            $stmt->execute(['doc' => $json, 'id' => $id]);
            $updated++;
            break; // only one
        }
        return $updated;
    }

    public function updateMany(array $filter, array $update): int
    {
        $updated = 0;
        $docs = $this->find($filter);
        foreach ($docs as $doc) {
            $id = $doc['_id'];
            $doc = $this->applyUpdate($doc, $update);
            $json = json_encode($doc, JSON_UNESCAPED_UNICODE);
            $stmt = mDBMini::$pdo->prepare("UPDATE {$this->table} SET document = :doc WHERE _id = :id");
            $stmt->execute(['doc' => $json, 'id' => $id]);
            $updated++;
        }
        return $updated;
    }

    public function deleteOne(array $filter): int
    {
        $docs = $this->find($filter);
        foreach ($docs as $doc) {
            $stmt = mDBMini::$pdo->prepare("DELETE FROM {$this->table} WHERE _id = :id");
            $stmt->execute(['id' => $doc['_id']]);
            return 1;
        }
        return 0;
    }

    public function deleteMany(array $filter): int
    {
        $count = 0;
        $docs = $this->find($filter);
        foreach ($docs as $doc) {
            $stmt = mDBMini::$pdo->prepare("DELETE FROM {$this->table} WHERE _id = :id");
            $stmt->execute(['id' => $doc['_id']]);
            $count++;
        }
        return $count;
    }

    public function count(array $filter = []): int
    {
        return count($this->find($filter));
    }

    public function drop(): void
    {
        mDBMini::$pdo->exec("DROP TABLE IF EXISTS {$this->table}");
    }

    private function matchFilter(array $doc, array $filter): bool
    {
        foreach ($filter as $key => $val) {
            // Оператор $or
            if ($key === '$or') {
                if (!is_array($val)) return false;
                $anyMatched = false;
                foreach ($val as $subFilter) {
                    if ($this->matchFilter($doc, $subFilter)) {
                        $anyMatched = true;
                        break;
                    }
                }
                if (!$anyMatched) return false;
                continue;
            }

            $docVal = $doc[$key] ?? null;

            if (is_array($val)) {
                foreach ($val as $op => $cond) {
                    if ($op === '$in') {
                        if (!in_array($docVal, $cond, true)) return false;
                    } elseif ($op === '$gt') {
                        if (!($docVal > $cond)) return false;
                    } elseif ($op === '$lt') {
                        if (!($docVal < $cond)) return false;
                    } elseif ($op === '$gte') {
                        if (!($docVal >= $cond)) return false;
                    } elseif ($op === '$lte') {
                        if (!($docVal <= $cond)) return false;
                    } elseif ($op === '$ne') {
                        if ($docVal == $cond) return false;
                    } elseif ($op === '$regex') {
                        if (!preg_match($cond, (string)$docVal)) return false;
                    } elseif ($op === '$exists') {
                        $exists = array_key_exists($key, $doc);
                        if ($exists !== $cond) return false;
                    }
                }
            } else {
                if ($docVal !== $val) return false;
            }
        }

        return true;
    }

    private function applyUpdate(array $doc, array $update): array
    {
        foreach ($update as $op => $changes) {
            if ($op === '$set') {
                foreach ($changes as $k => $v) {
                    $doc[$k] = $v;
                }
            } elseif ($op === '$unset') {
                foreach ($changes as $k => $_) {
                    unset($doc[$k]);
                }
            }
        }
        return $doc;
    }

    public function aggregate(array $pipeline): array
    {
        $docs = $this->find(); // загрузить все документы

        foreach ($pipeline as $stage) {
            $op = key($stage);
            $args = $stage[$op];

            if ($op === '$match') {
                $docs = array_filter($docs, fn($doc) => $this->matchFilter($doc, $args));
            } elseif ($op === '$group') {
                $grouped = [];
                foreach ($docs as $doc) {
                    $key = $doc[$args['_id']] ?? null;
                    if (!isset($grouped[$key])) $grouped[$key] = ['_id' => $key];

                    foreach ($args as $k => $expr) {
                        if ($k === '_id') continue;

                        if (is_array($expr) && isset($expr['$sum'])) {
                            $grouped[$key][$k] = ($grouped[$key][$k] ?? 0) + ($doc[$expr['$sum']] ?? 0);
                        } elseif (is_array($expr) && isset($expr['$count'])) {
                            $grouped[$key][$k] = ($grouped[$key][$k] ?? 0) + 1;
                        }
                    }
                }
                $docs = array_values($grouped);
            } elseif ($op === '$sort') {
                uasort($docs, function ($a, $b) use ($args) {
                    foreach ($args as $field => $dir) {
                        $av = $a[$field] ?? null;
                        $bv = $b[$field] ?? null;
                        if ($av == $bv) continue;
                        return $dir > 0 ? ($av <=> $bv) : ($bv <=> $av);
                    }
                    return 0;
                });
            }
        }

        return array_values($docs);
    }
}
