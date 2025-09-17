<?php

namespace MCP\Laravel\Contracts;

/**
 * Interface for MCP prompts in Laravel.
 */
interface McpPromptInterface
{
    /**
     * Get the prompt name.
     */
    public function name(): string;

    /**
     * Get the prompt description.
     */
    public function description(): string;

    /**
     * Get the prompt arguments schema.
     */
    public function arguments(): array;

    /**
     * Handle the prompt execution.
     */
    public function handle(array $args): array;

    /**
     * Determine if the prompt requires authentication.
     */
    public function requiresAuth(): bool;

    /**
     * Get required scopes for the prompt.
     */
    public function requiredScopes(): array;
}
