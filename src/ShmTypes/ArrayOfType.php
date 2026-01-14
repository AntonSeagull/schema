<?php

namespace Shm\ShmTypes;

use Sentry\Util\Str;
use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBRedis;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\Response;
use Traversable;

/**
 * Array type for schema definitions
 * 
 * This class represents an array type that contains items of a specific type.
 * It provides validation, normalization, and serialization for arrays.
 */
class ArrayOfType extends BaseType
{
    public string $type = 'array';

    /**
     * Constructor for ArrayOfType
     * 
     * @param BaseType $itemType The type of items in the array
     */
    public function __construct(BaseType $itemType)
    {
        if ($itemType instanceof EnumType) {
            $this->type = 'enums';
        }


        $itemType->setParent($this);
        $this->itemType = $itemType;
    }

    /**
     * Check if two arrays are equal
     * 
     * @param mixed $a First array
     * @param mixed $b Second array
     * @return bool True if arrays are equal
     */
    public function equals(mixed $a, mixed $b): bool
    {
        return json_encode($a) === json_encode($b);
    }


    /**
     * Normalize array value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }

        if ($addDefaultValues && !$value && $this->defaultIsSet) {
            return $this->getDefault();
        }

        if (!$value) {
            return [];
        }

        $newValue = [];


        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] = $this->itemType->normalize($valueItem, $addDefaultValues, $processId);
        }


        return $newValue;
    }



    public function removeOtherItems(mixed $value): mixed
    {
        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }

        $newValue = [];
        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] = $this->itemType->removeOtherItems($valueItem);
        }

        return $newValue;
    }






    /**
     * Validate array value
     * 
     * @param mixed $value Value to validate
     * @throws \Exception If validation fails
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be an array.");
        }

        foreach ($value as $k => $item) {
            try {
                $this->itemType->validate($item);
            } catch (\Exception $e) {
                $field = $this->title ?? "Element {$k}";
                throw new \Exception("{$field}[{$k}]: " . $e->getMessage());
            }
        }
    }




    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;
        $this->itemType->fullCleanDefault();

        return $this;
    }







    public function filterType($safeMode = false): ?BaseType
    {



        $this->itemType->key = $this->key;

        $itemTypeFilter = $this->itemType->filterType($safeMode);
        if (!$itemTypeFilter) {
            return null;
        }
        $itemTypeFilter->editable();

        return $itemTypeFilter->inAdmin($this->inAdmin)->title($this->title);
    }

    public function tsType(): TSType
    {



        $TSType = new TSType($this->itemType->tsType()->getTsTypeName() . '[]', '');



        return $TSType;
    }

    public function tsInputType(): TSType
    {
        $TSType = new TSType($this->itemType->tsInputType()->getTsTypeName() . '[]', '');
        return $TSType;
    }




    public function exportRow(mixed $value): string | array | null
    {
        if (is_array($value) || $value instanceof Traversable) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->itemType->exportRow($item);
            }

            if (count($result) == 0) {
                return "";
            }

            return $result;
        }
        return "";
    }
}
