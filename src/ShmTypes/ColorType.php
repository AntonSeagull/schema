<?php

namespace Shm\ShmTypes;


use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * Color type for schema definitions
 * 
 * This class represents a color type with validation for color formats
 * including hex, rgb, and named colors.
 */
class ColorType extends BaseType
{
    public string $type = 'color';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Nothing extra for now
    }

    /**
     * Normalize color value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        return (string) $value;
    }

    /**
     * Validate color value
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

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a string.");
        }

        // Validate color format (hex, rgb, rgba, or named color)
        if (!$this->isValidColor($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a valid color format (hex, rgb, rgba, or named color).");
        }
    }

    /**
     * Check if value is a valid color format
     * 
     * @param string $color Color value to validate
     * @return bool True if valid color format
     */
    private function isValidColor(string $color): bool
    {
        // Hex color validation
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return true;
        }

        // RGB/RGBA color validation
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $color)) {
            return true;
        }

        // Named colors (basic validation)
        $namedColors = [
            'red',
            'green',
            'blue',
            'yellow',
            'orange',
            'purple',
            'pink',
            'brown',
            'black',
            'white',
            'gray',
            'grey',
            'transparent',
            'inherit',
            'initial',
            'unset'
        ];

        return in_array(strtolower($color), $namedColors);
    }





    public function filterType($safeMode = false): ?BaseType
    {


        $itemTypeFilter = Shm::string()->editable();

        return $itemTypeFilter->inAdmin($this->inAdmin)->title($this->title);
    }


    public function tsType(): TSType
    {
        $TSType = new TSType('string');


        return $TSType;
    }
}
