<?php

namespace Shm\ShmTypes;


use Nette\PhpGenerator\Method;
use Sentry\Util\Arr;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\Utils\JsonLogicBuilder;
use Shm\ShmUtils\MaterialIcons;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmUtils;
use Traversable;

abstract class BaseType
{

    public $hide = false;


    public $unique = false;

    public $globalUnique = false;

    public $expanded = false;



    public $description = null;



    public int $depth = 0;

    public function depth(int $depth): static
    {
        $this->depth = $depth;
        return $this;
    }


    /**
     * Трансформеры «перед отдачей в RPC».
     * @var callable[]
     */
    public array $outputTransformers = [];

    /**
     * Устанавливает обработчик «перед отдачей в RPC» значения.
     * Если $enabled = false, обработчик не регистрируется.
     *
     * @param callable $fn function(mixed $root, mixed $value): mixed
     * @param bool $enabled
     */
    public function onOutput(callable $fn, bool $enabled = true): static
    {
        if ($enabled) {
            $this->outputTransformers[] = $fn;
        }
        return $this;
    }

    public function hasOutputTransformers(): bool
    {
        if (!empty($this->outputTransformers)) {
            return true;
        }
        if (isset($this->items)) {
            foreach ($this->items as $item) {
                if ($item->hasOutputTransformers()) {
                    return true;
                }
            }
        }
        if (isset($this->itemType) && $this->itemType instanceof BaseType) {
            if ($this->itemType->hasOutputTransformers()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Рекурсивно применяет все зарегистрированные onOutput-хендлеры.
     */
    public function applyOutput(mixed $root, mixed $value): mixed
    {





        if ($this instanceof StructureType && $this->collection) {


            $root = $value;
        }

        // Применяем обработчики текущего узла
        foreach ($this->outputTransformers as $fn) {

            $value = $fn($root, $value);
        }

        // Спуск в подтипы
        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType && isset($value[$key])) {

                    $value[$key] = $item->applyOutput($root, $value[$key]);
                }
            }
        }

        // Спуск в itemType (массивы/коллекции)
        if (isset($this->itemType) && (is_array($value) || $value instanceof Traversable)) {


            foreach ($value as $i => $v) {
                $value[$i] = $this->itemType->applyOutput($root, $v);
            }
        }

        return $value;
    }

    public function toOutput(mixed $value): mixed
    {




        $root = null;



        return $this->applyOutput($root, $value);
    }

    public function globalUnique(bool $globalUnique = true): static
    {
        $this->globalUnique = $globalUnique;
        return $this;
    }


    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;
        return $this;
    }

    public function ai(bool $ai = true): static
    {
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function expand(): static
    {
        $this->expanded = true;


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {


                $item->expand();
            }
        }

        if (isset($this->itemType)) {
            $this->itemType->expand();
        }



        return $this;
    }




    public $display = false;

    public $displayPrefix = null;


    public function display(bool | string $display = true): static
    {

        if (is_string($display)) {
            $this->displayPrefix = $display;
            $this->display = true;
            return $this;
        }

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

    public function hide($hide = true): static
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

    public function hideNotInTable(): static
    {

        if (!$this->key || $this->key == '_id') {
            return $this;
        }

        if (!$this->inTable) {
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

    public BaseType | null $itemType = null;


    public $onlyAuth = false;

    public  $key = null;

    public $path = null;

    public  $min = null;
    public  $max = null;

    public bool $editable = false;

    private bool $editableSet = false;

    private bool $inAdminSet = false;

    private bool $inTableSet = false;


    public function isInTableSet(): bool
    {
        return $this->inTableSet;
    }


    public function isInAdminSet(): bool
    {
        return $this->inAdminSet;
    }


    public function isEditableSet(): bool
    {
        return $this->editableSet;
    }

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




    public function getAllCollections($result = [])
    {


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                if ($item instanceof StructureType && $item->collection) {



                    $result[$item->collection] = $item;
                } else {


                    $result =  $item->getAllCollections($result);
                }
            }
        }

        if (isset($this->itemType)) {
            $result = $this->itemType->getAllCollections($result);
        }


        return $result;
    }


    public bool $report = false;

    public function report(bool $report = true): static
    {
        $this->report = $report;
        return $this;
    }


    public function displayValues($value): array | string | null
    {

        if ($value && (is_string($value) || is_numeric($value))) {
            return $value;
        }

        return null;
    }





    public function computedReport(StructureType | null $root = null, $path = [], $pipeline = [])
    {





        return   null;
    }


    /**
     * Set whether this field is for admin forms.
     * Ignores children fields
     */
    public function  inAdminThis(bool $inAdmin = true): static
    {
        $this->inAdmin = $inAdmin;
        $this->inAdminSet = true;


        return $this;
    }


    /**
     * Set whether this field is for admin forms.
     * For all children fields, this will also set the inAdmin property.
     */

    public function inAdmin(bool $isAdmin = true): static
    {
        $this->inAdmin = $isAdmin;

        $this->inAdminSet = true;

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                if ($key === '_id') {
                    continue;
                }

                if ($item->isInAdminSet()) {
                    continue;
                }

                $item->inAdmin($isAdmin);
            }
        }

        if (isset($this->itemType)) {

            if (!$this->itemType->isInAdminSet()) {


                $this->itemType->inAdmin($isAdmin);
            }
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


    public function icon(string $icon): static
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



    public null | int $tablePriority = null;


    public function inTableThis(bool | int $inTableThis = true): static
    {
        if (is_int($inTableThis)) {
            $this->tablePriority = $inTableThis;
            $this->inTable = true;

            return $this;
        }

        $this->inTable = $inTableThis;

        return $this;
    }

    /**
     * Set whether this field is for table display.
     * If true, it will use the table layout.
     */
    public function inTable(bool | int $isInTable = true): static
    {

        if (is_int($isInTable)) {
            $this->tablePriority = $isInTable;
            $this->inTable = true;

            return $this;
        }

        $this->inTable = $isInTable;


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                if ($key === '_id') {
                    continue;
                }

                if ($item->isInTableSet()) {
                    continue;
                }

                $item->inTable($isInTable);
            }
        }

        if (isset($this->itemType)) {

            if (!$this->itemType->isInTableSet()) {
                $this->itemType->inTable($isInTable);
            }
        }


        return $this;
    }

    public $cond = null;


    public function condLogic(JsonLogicBuilder $cond): static
    {
        $this->cond = $cond->build();
        return $this;
    }


    /**
     * Set a condition using JsonLogicBuilder.
     * This allows complex conditions to be applied to the field.
     */
    public  function cond(JsonLogicBuilder $cond): static
    {
        $this->cond = $cond->build();
        return $this;
    }




    public $localCond = null;


    /**
     * Set a condition using JsonLogicBuilder.
     * This allows complex conditions to be applied to the field.
     */
    public  function localCond(JsonLogicBuilder $cond): static
    {
        $this->localCond = $cond->build();
        return $this;
    }

    public function addUUIDInArray(): static
    {



        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $item->addUUIDInArray();
            }
        }

        if (isset($this->itemType)) {


            if ($this->itemType instanceof StructureType && !$this->itemType->collection && !$this->itemType->haveItemByKey('_id')) {



                $this->itemType->addFieldIfNotExists('uuid', Shm::uuid());
            }



            $this->itemType->addUUIDInArray();
        }

        return $this;
    }


    public function childrenInAdmin(bool $inAdmin = true)
    {

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {



                if (!$item->isInAdminSet()) {
                    $item->inAdmin($inAdmin);
                }
            }
        }

        if (isset($this->itemType)) {
            if (!$this->itemType->isInAdminSet()) {
                $this->itemType->inAdmin($inAdmin);
            }
        }



        return $this;
    }

    public function childrenEditable(bool $isEditable = true)
    {

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {



                if (!$item->isEditableSet()) {
                    $item->editable($isEditable);
                }
            }
        }

        if (isset($this->itemType)) {
            if (!$this->itemType->isEditableSet()) {
                $this->itemType->editable($isEditable);
            }
        }



        return $this;
    }


    public function  editableThis(bool $editable = true): static
    {
        $this->editable = $editable;
        $this->editableSet = true;


        return $this;
    }



    /**
     * Set whether this field is editable.
     * If true, it will be editable.
     */
    public function editable(bool $isEditable = true): static
    {
        $this->editable = $isEditable;
        $this->editableSet = true;


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                if (!$item->editableSet) {
                    $item->editable($isEditable);
                }
            }
        }

        if (isset($this->itemType)) {
            if (!$this->itemType->editableSet) {
                $this->itemType->editable($isEditable);
            }
        }



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
     * Duplicate the current type.
     */
    public function protected(bool $protected = true): static
    {
        return $this->hide($protected);
    }

    /**
     * Mark the field as required or optional.
     */
    public function required(bool $isRequired = true): static
    {
        $this->required = $isRequired;
        return $this;
    }



    public function depthExpand(): BaseType | static
    {


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {
                $this->items[$key] = $item->depthExpand();
            }
        }

        if (isset($this->itemType)) {
            $this->itemType = $this->itemType->depthExpand();
        }




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


    public function setTitle(null | string $title): static
    {

        return $this->title($title);
    }

    /**
     * Set a title for use in error messages.
     */
    public function title(null | string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public bool $private = false;

    public function private(bool $private = true): static
    {
        $this->private = $private;
        return $this;
    }




    /**
     * Normalize the input value to the expected type.
     */
    public function normalizePrivate(mixed $value): mixed
    {

        if ($this->private) return null;



        if (isset($this->items)) {
            foreach ($this->items as $name => $type) {
                if (isset($value[$name])) {
                    $value[$name] =  $type->normalizePrivate($value[$name]);
                }
            }
        }

        if (isset($this->itemType)) {


            if ((is_array($value) || $value instanceof Traversable)) {




                foreach ($value as  $index => $valueItem) {
                    if (!$valueItem) {
                        continue;
                    }

                    $value[$index] = $this->itemType->normalizePrivate($valueItem);
                }
            }
        }


        return $value;
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
                throw new \Exception("{$field} is required and cannot be null.");
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


    public function haveGetDisplayProjection(): bool
    {
        if ($this instanceof StructureType && isset($this->items)) {

            foreach ($this->items as $key => $item) {

                if ($item->haveGetDisplayProjection()) {
                    return true;
                }
            }
        }

        if ($this instanceof ArrayOfType && isset($this->itemType)) {
            if ($this->itemType->haveGetDisplayProjection()) {
                return true;
            }
        }

        if ($this->display) {
            return true;
        }

        return false;
    }





    /**
     * Преобразует строку/коллбек в предикат: fn($node): bool
     * @param string|callable $criteria
     * @return callable
     */
    private function makePredicate(string|callable $criteria): callable
    {
        if (is_string($criteria)) {
            $prop = $criteria;
            return static function ($node) use ($prop): bool {
                // безопасно проверяем свойство
                return property_exists($node, $prop) && (bool) $node->{$prop};
            };
        }

        if (is_callable($criteria)) {
            return $criteria;
        }

        throw new \InvalidArgumentException('Criteria must be string or callable.');
    }

    /**
     * Рекурсивно проверяет, есть ли true где-либо в узле/детях по критерию.
     * @param string|callable $criteria
     */
    public function hasTrueValueDeep(string|callable $criteria): bool
    {
        $pred = $this->makePredicate($criteria);

        if ($pred($this)) {
            return true;
        }

        if (isset($this->items)) {
            foreach ($this->items as $item) {
                if ($item->hasTrueValueDeep($pred)) {
                    return true;
                }
            }
        }

        if (isset($this->itemType) && $this->itemType->hasTrueValueDeep($pred)) {
            return true;
        }

        return false;
    }

    /**
     * Получает проекцию по типу или произвольному предикату.
     * Если передана строка — валидируем, как раньше.
     *
     * @param string|callable $criteria 'inAdmin'|'hide'|'display'|'inTable' или callable($node): bool
     * @param bool $childrenCalled
     * @return array
     */
    public function getProjection(string|callable $criteria, bool $childrenCalled = false): array
    {
        // Валидация только для строкового режима (сохраняем прежнее поведение)
        if (is_string($criteria)) {
            if (!in_array($criteria, ['inAdmin', 'hide', 'display', 'inTable'], true)) {
                throw new \LogicException('Invalid projection type: ' . $criteria);
            }
        }

        $pred = $this->makePredicate($criteria);

        if ($this->type === 'structure' && isset($this->items)) {
            if (!$pred($this) && !$this->hasTrueValueDeep($pred)) {
                return [];
            }

            $res = [];
            foreach ($this->items as $item) {
                $val = $item->getProjection($pred, true);

                if (!is_array($val)) {
                    throw new \LogicException('Projection must return an array for structure items. Key: ' . $item->key);
                }

                if (count($val) === 0) {
                    continue;
                }

                $res = array_merge($res, $val);
            }

            if (!$childrenCalled) {
                return $res;
            }

            return [$this->key => $res];
        }

        if ($this->type === 'array' && isset($this->itemType)) {
            if (!$pred($this) && !$this->hasTrueValueDeep($pred)) {
                return [];
            }

            $val = $this->itemType->getProjection($pred, true);

            if (!is_array($val)) {
                throw new \LogicException('Projection must return an array for array itemType. Key: ' . $this->key);
            }

            // if (!$childrenCalled) {
            //     return $val;
            // }

            return  $val;
        }

        if ($pred($this)) {
            return [$this->key => 1];
        }

        return [];
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


    public function getGlobalUniquePath(array $path): array
    {

        if (count($path) > 0 && $this->globalUnique) {
            return [...$path, $this->key];
        }

        $findPaths = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $findPaths = [...$findPaths, ...$item->getUniquePath([...$path, $key])];
            }
        }

        if (isset($this->itemType)) {
            $findPaths =   [...$findPaths, ...$this->itemType->getUniquePath([...$path])];
        }

        return  $findPaths;
    }

    public function getUniquePath(array $path): array
    {

        if (count($path) > 0 && $this->unique) {
            return [...$path, $this->key];
        }

        $findPaths = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                $findPaths = [...$findPaths, ...$item->getUniquePath([...$path, $key])];
            }
        }

        if (isset($this->itemType)) {
            $findPaths =   [...$findPaths, ...$this->itemType->getUniquePath([...$path])];
        }

        return  $findPaths;
    }

    public function haveID(): bool
    {


        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {

                if ($item->haveID()) {
                    return true;
                }
            }
        }

        if (isset($this->itemType)) {
            if ($this->itemType->haveID()) {
                return true;
            }
        }

        return  false;
    }




    public function getIDsPathsForCollection(array $path, string $collection): array
    {


        if (($this instanceof IDType || $this instanceof IDsType)) {

            if ($this->document && $this->document->collection == $collection) {
                return [
                    [
                        'path' => [...$path],
                        'document' => $this->document,
                    ]
                ];
            } else {
                return [];
            }
        }


        $findPaths = [];

        if (isset($this->items)) {
            foreach ($this->items as $key => $item) {



                $findPaths = [...$findPaths, ...$item->getIDsPathsForCollection([...$path, $key], $collection)];
            }
        }

        if (isset($this->itemType)) {
            $findPaths =   [...$findPaths, ...$this->itemType->getIDsPathsForCollection([...$path, '[]'], $collection)];
        }

        return  $findPaths;
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

    public function width(int $width): static
    {
        $this->columnsWidth = $width;
        return $this;
    }


    public function setColumnsWidth(int $width): static
    {
        $this->columnsWidth = $width;
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
     * Устанавливает обработчик события вставки или изменения значения.
     *
     * @param callable $handler Функция с сигнатурой function(array $_ids, mixed $newValue, array $docs)
     */
    public function insertOrUpdateEvent(callable $handler): static
    {
        $this->onInsertEvent = $handler;
        $this->onUpdateEvent = $handler;
        return $this;
    }


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

    private $onBeforeInsertEvent = null;
    private $onBeforeUpdateEvent = null;
    /**
     * Устанавливает обработчик события перед вставкой значения, должно возвращать значение.
     *
     * @param callable $handler Функция с сигнатурой function($value): mixed
     */
    public function beforeInsertEvent(callable $handler): static
    {
        $this->onBeforeInsertEvent = $handler;
        return $this;
    }

    /**
     * Устанавливает обработчик события перед изменением значения, должно возвращать значение.
     *
     * @param callable $handler Функция с сигнатурой function($value): mixed
     */
    public function beforeUpdateEvent(callable $handler): static
    {
        $this->onBeforeUpdateEvent = $handler;
        return $this;
    }


    /**
     * Устанавливает обработчик события перед вставкой или изменением значения, должно возвращать значение.
     *
     * @param callable $handler Функция с сигнатурой function($value): mixed
     */
    public function beforeInsertOrUpdateEvent(callable $handler): static
    {
        $this->onBeforeInsertEvent = $handler;
        $this->onBeforeUpdateEvent = $handler;
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


    public function haveBeforeInsertEvent(): bool
    {
        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType) {
                    if (is_callable($item->onBeforeInsertEvent)) {
                        return true;
                    }
                }
            }
        }

        if (is_callable($this->onBeforeInsertEvent)) {
            return true;
        }

        return false;
    }

    public function haveBeforeUpdateEvent(): bool
    {
        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {
                if ($item instanceof BaseType) {
                    if (is_callable($item->onBeforeUpdateEvent)) {
                        return true;
                    }
                }
            }
        }

        if (is_callable($this->onBeforeUpdateEvent)) {
            return true;
        }

        return false;
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


    public function callBeforeInsertEvent($value): mixed
    {

        if (is_callable($this->onBeforeInsertEvent)) {
            return call_user_func($this->onBeforeInsertEvent, $value);
        }

        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {

                if ($item instanceof BaseType) {
                    if ($item->haveBeforeInsertEvent() && isset($value[$key])) {
                        $value[$key] = $item->callBeforeInsertEvent($value[$key]);
                    }
                }
            }
        }

        return $value;
    }

    public function callBeforeUpdateEvent($value): mixed
    {

        if (is_callable($this->onBeforeUpdateEvent)) {
            return call_user_func($this->onBeforeUpdateEvent, $value);
        }

        if (isset($this->items)) {

            foreach ($this->items as $key => $item) {

                if ($item instanceof BaseType) {
                    if ($item->haveBeforeUpdateEvent() && isset($value[$key])) {
                        $value[$key] = $item->callBeforeUpdateEvent($value[$key]);
                    }
                }
            }
        }

        return $value;
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
