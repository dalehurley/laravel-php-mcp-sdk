<?php

namespace MCP\Laravel\Contracts;

/**
 * Interface for MCP tools in Laravel.
 */
interface McpToolInterface
{
    /**
     * Get the tool name.
     */
    public function name(): string;

    /**
     * Get the tool description.
     */
    public function description(): string;

    /**
     * Get the input schema for the tool.
     */
    public function inputSchema(): array;

    /**
     * Handle the tool execution.
     */
    public function handle(array $params): array;

    /**
     * Determine if the tool requires authentication.
     */
    public function requiresAuth(): bool;

    /**
     * Get required scopes for the tool.
     */
    public function requiredScopes(): array;
}
