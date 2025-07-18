<?php

namespace Shm\ShmTypes;


use Nette\PhpGenerator\Method;
use Sentry\Util\Arr;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\Utils\JsonLogicBuilder;
use Shm\ShmUtils\MaterialIcons;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmUtils;

abstract class BaseType
{

    public $hide = false;


    private bool $flatted = false;


    public function flatted(bool $flatted = true): static
    {
        $this->flatted = $flatted;

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                $item->flatted($flatted);
            }
        }

        if (isset($this->itemType)) {
            $this->itemType->flatted($flatted);
        }



        return $this;
    }

    public function isFlatted(): bool
    {
        return $this->flatted;
    }


    public $display = false;


    public function display(bool $display = true): static
    {
        $this->display = $display;
        return $this;
    }


    public $notNull = false;

    public function notNull(bool $notNull = true): static
    {
        $this->notNull = $notNull;
        return $this;
    }

    public $single = false;

    public function hide($hide = true): self
    {

        $this->hide = $hide;
        return $this;
    }

    public array $assets = [];

    public function assets(array $assets): static
    {
        $this->assets = array_merge($this->assets, $assets);
        return $this;
    }


    /** @var array<string, BaseType> */
    public array $items;

    public array $values;


    public $group = [
        'key' => 'default'
    ];

    public function hideNotInTable(): self
    {

        if (!$this->key || $this->key == '_id') {
            return $this;
        }

        if (! $this->inTable) {
            $this->hide = true;
        }
        return $this;
    }

    public function group(string $groupTitle, string | null $icon): static
    {
        $this->group = [
            'key' => md5($groupTitle),
            'icon' => $icon,
            'title' => $groupTitle,
        ];
        return $this;
    }

    public BaseType $itemType;


    public $onlyAuth = false;

    public  $key = null;

    public $path = null;

    public  $min = null;
    public  $max = null;

    public bool $editable = false;

    public bool $inAdmin = false;

    public bool $inTable = false;

    public int $col = 24;

    /**
     * Whether the value is required.
     */
    public bool $required = false;

    /**
     * Whether the value can be null.
     */
    public bool $nullable = true;

    /**
     * Default value if input is missing or null (when nullable).
     */
    public mixed $default = null;

    /**
     * Human-readable field title (used in error messages).
     */
    public ?string $title = null;

    /**
     * The internal type name (e.g., "string", "int", "array").
     */
    public string $type = 'mixed';

    public function __construct()
    {
        // No initialization by default
    }


    public function onlyAuth(bool $onlyAuth = true): static
    {
        $this->onlyAuth = $onlyAuth;
        return $this;
    }





    /**
     * Set whether this field is for admin forms.
     * If true, it will use the admin form layout.
     */

    public function inAdmin(bool $isAdmin = true): static
    {
        $this->inAdmin = $isAdmin;

        /* if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                $item->inAdmin($isAdmin);
            }
        }*/

        if (isset($this->itemType)) {
            $this->itemType->inAdmin($isAdmin);
        }

        return $this;
    }


    public function single(bool $single = true): static
    {
        $this->single = $single;
        return $this;
    }


    /**
     * Set the key for this field.
     * This is used to identify the field in forms and data structures.
     */

    public function key(string $key): static
    {

        ShmUtils::isValidKey($key);
        $this->key = $key;


        if (isset($this->itemType)) {
            $this->itemType->keyIfNot($key);
        }



        return $this;
    }


    //Установить ключ если если он не установлен
    public function keyIfNot(string $key): static
    {

        ShmUtils::isValidKey($key);

        if ($this->key === null) {
            $this->key($key);
        }
        return $this;
    }



    /**
     * Set the minimum value (for numeric types).
     */
    public function min(int|float $min): static
    {
        $this->min = $min;
        return $this;
    }
    /**
     * Set the maximum value (for numeric types).
     */
    public function max(int|float $max): static
    {
        $this->max = $max;
        return $this;
    }


    public function icon(string $icon): self
    {
        $this->assets([
            'icon' => $icon,
        ]);

        return $this;
    }



    /**
     * Set the column width for admin forms.
     * 12 = half-width, 24 = full-width.
     */
    public function setCol(int $col): static
    {
        $this->col($col);
        return $this;
    }


    /**
     * Set whether this field is for table display.
     * If true, it will use the table layout.
     */
    public function inTable(bool $isInTable = true): static
    {
        $this->inTable = $isInTable;
        return $this;
    }

    public $cond = null;


    /**
     * Set a condition using JsonLogicBuilder.
     * This allows complex conditions to be applied to the field.
     */
    public  function cond(JsonLogicBuilder $cond): self
    {
        $this->cond = $cond->build();
        return $this;
    }



    /**
     * Set whether this field is editable.
     * If true, it will be editable.
     */
    public function editable(bool $isEditable = true): static
    {
        $this->editable = $isEditable;
        return $this;
    }


    /**
     * Set the column width for admin forms.
     * 12 = half-width, 24 = full-width.
     */
    public function col(int $col): static
    {
        $this->col = $col;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Mark the field as required or optional.
     */
    public function required(bool $isRequired = true): static
    {
        $this->required = $isRequired;
        return $this;
    }


    public function hideDocuments(): void
    {

        if ($this instanceof IDsType || $this instanceof IDType) {
            if ($this->document)
                $this->document->hide = true;
        }

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                $item->hideDocuments();
            }
        }

        if (isset($this->itemType)) {
            $this->itemType->hideDocuments();
        }
    }


    public function fullRequired(bool $required = true): static
    {
        $this->required = $required;

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                $item->fullRequired($required);
            }
        }

        if (isset($this->itemType)) {
            $this->itemType->fullRequired($required);
        }

        return $this;
    }



    /**
     * Mark the field as nullable or not.
     */
    public function nullable(bool $isNullable = true): static
    {
        $this->nullable = $isNullable;
        return $this;
    }

    public function noNullable(): static
    {
        $this->nullable = false;
        return $this;
    }

    public bool $defaultIsSet = false;

    /**
     * Set a default value.
     */
    public function default(mixed $value): static
    {
        $this->defaultIsSet = true;
        $this->default = $value;
        return $this;
    }


    /**
     * Clear the default value.
     * This will remove any previously set default.
     */
    public function cleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;
        return $this;
    }

    /**
     * Set a title for use in error messages.
     */
    public function title(null | string $title): static
    {
        $this->title = $title;
        return $this;
    }



    /**
     * Normalize the input value to the expected type.
     */
    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        return $value;
    }

    public function removeOtherItems(mixed $value): mixed
    {
        return $value;
    }


    /**
     * Validate the value. Should throw if invalid.
     */
    public function validate(mixed $value): void
    {
        if ($value === null) {
            if (!$this->nullable && $this->required) {
                $field = $this->title ?? 'Value';
                throw new \InvalidArgumentException("{$field} is required and cannot be null.");
            }
        }
    }






    //  public ?BaseType $filterType = null;

    public function filterType($safeMode = false): ?BaseType
    {
        return null;
    }
    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {
        return null;
    }

    public function safeFullEditable(bool $editable = true): static
    {
        return $this->fullEditable($editable);
    }


    public function fullEditable(bool $editable = true): static
    {
        $this->editable = $editable;
        return $this;
    }

    public function fullInAdmin(bool $isAdmin = true): static
    {
        $this->inAdmin = $isAdmin;

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                if ($key === '_id') {
                    continue;
                }
                $item->fullInAdmin($isAdmin);
            }
        }

        if (isset($this->itemType)) {
            $this->itemType->fullInAdmin($isAdmin);
        }

        return $this;
    }



    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;
        return $this;
    }




    public function tsType(): TSType
    {
        $TSType = new TSType('any');

        return $TSType;
    }

    public function tsInputType(): TSType
    {
        return $this->tsType();
    }



    public function updateKeys(null | string $rootKey = null)
    {

        if ($this->key === null && !$rootKey) {
            throw new \LogicException('Keys must be set before updating keys.');
        }

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $item->updateKeys($key);
            }
        }
        if (isset($this->itemType)) {
            if ($rootKey)
                $this->itemType->updateKeys($rootKey);
        }

        if ($rootKey)
            $this->keyIfNot($rootKey);
    }




    public function phpGetter(): Method
    {

        if ($this->key === null) {
            throw new \LogicException('Key must be set before generating PHP getter.');
        }

        $method = new Method($this->key);
        $method->setReturnType('mixed');
        $method->setBody('return DeepAccess::safeGet("' . $this->key . '", $this->data);');
        $method->addComment('Get the value of ' . $this->key);
        $method->addComment('@return mixed|null');
        $method->addComment('Returns the value of ' . $this->key . ' or null if not set.');
        return $method;
    }




    public function updatePath(array | null $path = null): void
    {

        if ($this->key === null) {
            throw new \LogicException('Key must be set before updating path.');
        }

        $this->path = [...($path ?? []), $this->key];



        if (isset($this->itemType)) {

            $this->itemType->updatePath([...$this->path, "[]"]);
        }
    }

    public function getIDsPaths(array $path): array
    {

        $findPaths = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $findPaths = [...$findPaths, ...$item->getIDsPaths([...$path, $key])];
            }
        }

        if (isset($this->itemType)) {
            $findPaths =   [...$findPaths, ...$this->itemType->getIDsPaths([...$path, '[]'])];
        }

        return  $findPaths;
    }


    public function getSearchPaths(): array
    {


        $findPaths = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $findPaths = [...$findPaths, ...$item->getSearchPaths()];
            }
        }

        if (isset($this->itemType)) {
            $findPaths =   [...$findPaths, ...$this->itemType->getSearchPaths()];
        }


        return  $findPaths;
    }


    public $columnsWidth = null;

    public function setColumnsWidth(int $width): static
    {
        $this->columnsWidth = $width;
        return $this;
    }






    public function stripNestedIds(): self
    {




        if (isset($this->items)) {


            foreach ($this->items as $key => $item) {



                if ($item instanceof IDType || $item instanceof IDsType) {
                    $item->documentResolver();
                }

                if ($item instanceof StructureType) {
                    $item->stripNestedIds();
                }

                if ($item instanceof ArrayOfType) {
                    $item->stripNestedIds();
                }
            }
        }

        if (isset($this->itemType)) {


            $this->itemType->stripNestedIds();
        }

        return $this;
    }




    public function getKeysGraph(): array
    {
        if (!$this->key) {
            return ['->X'];
        }


        if (isset($this->items)) {

            $keys = [];
            foreach ($this->items as $key => $item) {
                $keys = [
                    $key => $item->getKeysGraph(),
                    ...$keys
                ];
            }
            return $keys;

            return [$this->key => $keys];
        }

        if (isset($this->itemType)) {
            return [$this->key . "[]" => $this->itemType->getKeysGraph()];
        }



        return [$this->key];
    }


    private $onInsertEvent = null;


    private $onUpdateEvent = null;

    /**
     * Устанавливает обработчик события изменения значения.
     *
     * @param callable $handler Функция с сигнатурой function(array $_ids, mixed $newValue, array $docs)
     */
    public function updateEvent(callable $handler): static
    {
        $this->onUpdateEvent = $handler;
        return $this;
    }


    /**
     * Устанавливает обработчик события вставки значения.
     *
     * @param callable $handler Функция с сигнатурой function(array $_ids, mixed $newValue, array $docs)
     */
    public function insertEvent(callable $handler): static
    {
        $this->onInsertEvent = $handler;
        return $this;
    }


    public function haveInsertEvent(): bool
    {
        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType) {
                    if (is_callable($item->onInsertEvent)) {
                        return true;
                    }
                }
            }
        }

        if (is_callable($this->onInsertEvent)) {
            return true;
        }

        return false;
    }


    public function haveUpdateEvent(): bool
    {
        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType) {
                    if (is_callable($item->onUpdateEvent)) {
                        return true;
                    }
                }
            }
        }

        if (is_callable($this->onUpdateEvent)) {
            return true;
        }

        return false;
    }


    private  function  groupChangedIdsByNewValue(array $newDocs, array $oldDocs, array $allNewDocs): array
    {
        $oldById = [];
        foreach ($oldDocs as $doc) {
            $oldById[(string)$doc['_id']] = $doc;
        }

        $grouped = [];

        $allNewDocsById = [];
        foreach ($allNewDocs as $doc) {
            $allNewDocsById[(string)$doc['_id']] = $doc;
        }

        foreach ($newDocs as $newDoc) {
            $idStr = (string)$newDoc['_id'];
            $newValue = $newDoc['_value'] ?? null;
            $oldValue = $oldById[$idStr]['_value'] ?? null;

            if ($newValue !== $oldValue) {
                $key = json_encode($newValue); // сериализуем для группировки
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'new_value' => $newValue,
                        'ids' => []
                    ];
                }
                $grouped[$key]['ids'][] = $newDoc['_id'];
                if (isset($allNewDocsById[$idStr]))
                    $grouped[$key]['all_new_docs'][] = $allNewDocsById[$idStr];
            }
        }

        return array_values($grouped); // массив без сериализованных ключей
    }

    /**
     * Вызывает ранее установленный обработчик изменения значения.
     */
    public function callUpdateEvent($newDocs, $oldDocs, $allNewDocs): void
    {

        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {

                if (in_array($key, ['_id',  'created_at', 'updated_at'])) {
                    continue;
                }


                if ($item instanceof BaseType) {


                    if ($item->haveUpdateEvent()) {



                        $_newDocs = array_map(function ($item) use ($key) {
                            return ['_value' => $item['_value'][$key], '_id' => $item['_id']];
                        }, array_filter($newDocs, function ($doc) use ($key) {
                            return isset($doc['_value'][$key]);
                        }));

                        $_oldDocs = array_map(function ($item) use ($key) {
                            return ['_value' => $item['_value'][$key], '_id' => $item['_id']];
                        }, array_filter($oldDocs, function ($doc) use ($key) {
                            return isset($doc['_value'][$key]);
                        }));



                        if (count($_newDocs) == 0) {
                            continue;
                        }

                        $item->callUpdateEvent($_newDocs, $_oldDocs, $allNewDocs);
                    }
                }
            }
        }

        if (is_callable($this->onUpdateEvent)) {

            $groupChangeds = ($this->groupChangedIdsByNewValue($newDocs, $oldDocs, $allNewDocs));

            foreach ($groupChangeds as $groupChanged) {
                $ids = $groupChanged['ids'];
                $newValue = $groupChanged['new_value'];
                $allNewDocs = $groupChanged['all_new_docs'] ?? [];


                call_user_func($this->onUpdateEvent, $ids, $newValue, $allNewDocs);
            }
        }
    }


    public function callInsertEvent($newDocs, $allNewDocs): void
    {

        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {

                if (in_array($key, ['_id',  'created_at', 'updated_at'])) {
                    continue;
                }

                if ($item instanceof BaseType) {

                    if ($item->haveInsertEvent()) {
                        $_newDocs = array_map(function ($item) use ($key) {
                            return ['_value' => $item['_value'][$key], '_id' => $item['_id']];
                        }, $newDocs);

                        $item->callInsertEvent($_newDocs, $allNewDocs);
                    }
                }
            }
        }

        if (is_callable($this->onInsertEvent)) {
            foreach ($newDocs as $doc) {
                call_user_func($this->onInsertEvent, [$doc['_id']], $doc['_value'], $allNewDocs);
            }
        }
    }



    public function createIndex($absolutePath = null): array
    {


        $result = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType) {
                    $result = [...$result,   ...$item->createIndex([...($absolutePath ?? []), $key])];
                }
            }
        }

        if (isset($this->itemType)) {
            $result = [
                ...$result,
                ...$this->itemType->createIndex([...($absolutePath ?? [])])
            ];
        }

        return $result;
    }

    private  function removeNullValues($data)
    {

        foreach ($data as $key => $val) {

            if ($val === null || $val === false || $val == []) {
                unset($data[$key]);
                continue;
            }
            if (is_array($val) || is_object($val)) {
                $data[$key] = $this->removeNullValues($val);
                if ($val === null || $val === false) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }



    public function json()
    {



        $data = json_decode(json_encode(get_object_vars($this)), true);
        $data = $this->removeNullValues($data);

        return $data;
    }
}
