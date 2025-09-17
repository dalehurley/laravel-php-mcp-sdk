<?php

namespace MCP\Laravel\Contracts;

/**
 * Interface for MCP resources in Laravel.
 */
interface McpResourceInterface
{
    /**
     * Get the resource URI.
     */
    public function uri(): string;

    /**
     * Get the resource description.
     */
    public function description(): string;

    /**
     * Read the resource content.
     */
    public function read(string $uri): array;

    /**
     * Determine if the resource requires authentication.
     */
    public function requiresAuth(): bool;

    /**
     * Get required scopes for the resource.
     */
    public function requiredScopes(): array;

    /**
     * Get resource metadata.
     */
    public function metadata(): array;
}
