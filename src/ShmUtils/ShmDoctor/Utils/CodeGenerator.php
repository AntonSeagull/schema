<?php

namespace Shm\ShmUtils\ShmDoctor\Utils;

class CodeGenerator
{
    /**
     * Convert string to snake_case (uppercase)
     * 
     * @param string $input Input string
     * @return string Uppercase snake_case string
     */
    public static function toSnakeCase(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Extract constant value from PHP file content
     * 
     * @param string $fileContent PHP file content as string
     * @param string $constName Constant name to find
     * @return string Constant value or empty string if not found
     */
    public static function extractConstantValue(string $fileContent, string $constName): string
    {
        // Pattern to match: public const CONST_NAME = 'value';
        // Supports single quotes and double quotes
        $pattern = '/public\s+const\s+' . preg_quote($constName, '/') . '\s*=\s*([\'"])((?:[^\'"]|\\\\\'|\\\\")*)\1\s*;/';

        if (preg_match($pattern, $fileContent, $matches)) {
            $value = $matches[2];
            // Unescape the value
            if ($matches[1] === "'") {
                // For single quotes: only \' and \\ are escaped
                $value = str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
            } else {
                // For double quotes: use stripcslashes to handle all escape sequences
                $value = stripcslashes($value);
            }
            return $value;
        }

        return "";
    }

    /**
     * Map schema type to PHP type
     * 
     * @param string $type Schema type
     * @return string|null PHP type or null if unknown
     */
    public static function mapType(string $type): ?string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'int',
            'string', 'text' => 'string',
            'float', 'double', 'number' => 'float',
            'bool', 'boolean' => 'bool',
            'array', 'list', 'object' => 'array',
            default => null,
        };
    }
}
