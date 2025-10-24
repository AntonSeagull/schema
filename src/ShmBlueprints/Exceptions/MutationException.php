<?php

namespace Shm\ShmBlueprints\Exceptions;

use Exception;

/**
 * Base exception for mutation operations
 */
class MutationException extends Exception
{
    /**
     * Create a validation error exception
     * 
     * @param string $message Error message
     * @return static
     */
    public static function validation(string $message): static
    {
        return new static("Validation error: {$message}", 400);
    }

    /**
     * Create an access error exception
     * 
     * @param string $message Error message
     * @return static
     */
    public static function access(string $message): static
    {
        return new static("Access error: {$message}", 403);
    }

    /**
     * Create a not found error exception
     * 
     * @param string $message Error message
     * @return static
     */
    public static function notFound(string $message): static
    {
        return new static("Not found: {$message}", 404);
    }

    /**
     * Create a database error exception
     * 
     * @param string $message Error message
     * @return static
     */
    public static function database(string $message): static
    {
        return new static("Database error: {$message}", 500);
    }
}
