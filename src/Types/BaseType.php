<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;
use Shm\Types\Utils\JsonLogicBuilder;

abstract class BaseType
{

    public $isProtected = false;


    public function protected($isProtected = true): self
    {

        $this->isProtected = $isProtected;
        return $this;
    }



    /** @var array<string, BaseType> */
    public array $items;

    public array $values;


    public BaseType $itemType;

    public  $key = null;


    public  $reg = false;
    public  $auth = false;

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

    /**
     * Set whether this field is for admin forms.
     * If true, it will use the admin form layout.
     */

    public function inAdmin(bool $isAdmin = true): static
    {
        $this->inAdmin = $isAdmin;
        return $this;
    }

    public function reg(bool $isReg = true): static
    {
        $this->reg = $isReg;
        return $this;
    }
    public function auth(bool $isAuth = true): static
    {
        $this->auth = $isAuth;
        return $this;
    }

    /**
     * Set the key for this field.
     * This is used to identify the field in forms and data structures.
     */

    public function key(string $key): static
    {
        $this->key = $key;




        return $this;
    }


    //Установить ключ если если он не установлен
    public function keyIfNot(string $key): static
    {
        if ($this->key === null) {
            $this->key = $key;
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
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }



    /**
     * Normalize the input value to the expected type.
     */
    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }

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

    /**
     * Get the GraphQL output type.
     */
    public function GQLType(): Type | array | null
    {
        return null;
    }

    /**
     * Get the GraphQL input type.
     */
    public function GQLTypeInput(): ?Type
    {
        return null;
    }





    public function filterType(): ?BaseType
    {
        return null;
    }
    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {
        return null;
    }


    public function fullEditable(bool $editable = true): static
    {
        $this->editable = $editable;
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
        $TSType = new TSType('Any', 'any');



        return $TSType;
    }

    public function tsInputType(): TSType
    {
        return $this->tsType();
    }

    public function tsGQLFullRequest(): string
    {


        if (isset($this->items)) {
            $result = [];

            foreach ($this->items as $key => $item) {




                if ($item instanceof StructureType) {


                    $result[] = $key . $item->tsGQLFullRequest();
                    continue;
                }

                if ($item instanceof IDsType && isset($item->document)) {
                    $result[] = $key .  $item->document->tsGQLFullRequest();

                    continue;
                }

                if ($item instanceof ArrayOfType && $item->itemType instanceof StructureType) {



                    $result[] = $key . $item->itemType->tsGQLFullRequest();
                    continue;
                }

                $result[] = $key;
            }

            return '{\n' . implode('\n', $result) . '\n}';
        }

        if (isset($this->itemType)) {
            return $this->itemType->tsGQLFullRequest();
        }
        return '';
    }
}