<?php

namespace Shm\ShmBlueprints\Interfaces;

use Shm\ShmTypes\StructureType;

/**
 * Interface for RPC mutation blueprints
 * 
 * This interface defines the contract for creating RPC mutations
 * that interact with MongoDB collections through the schema system.
 */
interface MutationBlueprintInterface
{
    /**
     * Set whether this mutation operates on a single row
     * 
     * @param bool $oneRow Whether to operate on single row
     * @return static
     */
    public function oneRow(bool $oneRow): static;

    /**
     * Set whether this mutation supports deletion
     * 
     * @param bool $delete Whether to support deletion
     * @return static
     */
    public function delete(bool $delete): static;

    /**
     * Set a callback to be executed before the main resolve function
     * 
     * @param callable $callback Before resolve callback
     * @return static
     */
    public function beforeResolve(callable $callback): static;

    /**
     * Set the pipeline for database operations
     * 
     * @param array|callable|null $pipeline MongoDB aggregation pipeline or function returning pipeline
     * @return static
     * @throws \InvalidArgumentException If pipeline is neither array nor callable
     */
    public function pipeline(array|callable|null $pipeline): static;

    /**
     * Get the validated pipeline for database operations
     * 
     * @return array MongoDB aggregation pipeline
     */
    public function getPipeline(): array;

    /**
     * Create the RPC mutation definition
     * 
     * @return array RPC mutation configuration with type, args, and resolve function
     */
    public function make(): array;
}
