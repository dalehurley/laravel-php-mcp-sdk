<?php

namespace MCP\Laravel\Laravel;

use MCP\Laravel\Contracts\McpToolInterface;
use MCP\Types\Content\TextContent;
use MCP\Types\Content\ImageContent;
use MCP\Types\Content\EmbeddedResource;

/**
 * Base class for Laravel MCP tools.
 * 
 * Provides a Laravel-friendly interface for creating MCP tools with
 * built-in support for validation, authentication, and content helpers.
 */
abstract class LaravelTool implements McpToolInterface
{
    /**
     * Get the tool name.
     */
    abstract public function name(): string;

    /**
     * Get the tool description.
     */
    abstract public function description(): string;

    /**
     * Handle the tool execution.
     */
    abstract public function handle(array $params): array;

    /**
     * Get the input schema for the tool.
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->properties(),
            'required' => $this->required(),
        ];
    }

    /**
     * Define the tool's input properties.
     */
    protected function properties(): array
    {
        return [];
    }

    /**
     * Define the required parameters.
     */
    protected function required(): array
    {
        return [];
    }

    /**
     * Determine if the tool requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Get required scopes for the tool.
     */
    public function requiredScopes(): array
    {
        return [];
    }

    /**
     * Validate input parameters.
     */
    protected function validate(array $params): array
    {
        // Use Laravel's validator if available
        if (function_exists('validator')) {
            $rules = $this->validationRules();
            if (!empty($rules)) {
                $validator = validator($params, $rules);
                if ($validator->fails()) {
                    throw new \InvalidArgumentException('Validation failed: ' . $validator->errors()->first());
                }
            }
        }

        return $params;
    }

    /**
     * Define validation rules for parameters.
     */
    protected function validationRules(): array
    {
        return [];
    }

    /**
     * Create a text content response.
     */
    protected function textContent(string $text): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ]
            ]
        ];
    }

    /**
     * Create an image content response.
     */
    protected function imageContent(string $data, string $mimeType): array
    {
        return [
            'content' => [
                [
                    'type' => 'image',
                    'data' => $data,
                    'mimeType' => $mimeType,
                ]
            ]
        ];
    }

    /**
     * Create an embedded resource response.
     */
    protected function embeddedResource(string $type, string $resource, ?string $text = null): array
    {
        $content = [
            'type' => 'resource',
            'resource' => [
                'type' => $type,
                'uri' => $resource,
            ]
        ];

        if ($text !== null) {
            $content['text'] = $text;
        }

        return [
            'content' => [$content]
        ];
    }

    /**
     * Create a mixed content response.
     */
    protected function mixedContent(array $contents): array
    {
        return [
            'content' => $contents
        ];
    }

    /**
     * Create an error response.
     */
    protected function errorResponse(string $message, int $code = -1): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ];
    }

    /**
     * Create a success response with data.
     */
    protected function successResponse(mixed $data = null): array
    {
        $response = ['success' => true];

        if ($data !== null) {
            if (is_string($data)) {
                return $this->textContent($data);
            }
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Log tool execution.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger()->log($level, "[MCP Tool: {$this->name()}] {$message}", $context);
        }
    }

    /**
     * Get the authenticated user if available.
     */
    protected function user(): mixed
    {
        if (function_exists('auth') && auth()->check()) {
            return auth()->user();
        }

        return null;
    }

    /**
     * Check if user has permission.
     */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        if (function_exists('auth') && auth()->check()) {
            return auth()->user()->can($ability, $arguments);
        }

        return false;
    }

    /**
     * Get a configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config($key, $default);
        }

        return $default;
    }

    /**
     * Cache a value.
     */
    protected function cache(string $key, mixed $value, int $ttl = 3600): void
    {
        if (function_exists('cache')) {
            cache()->put($key, $value, $ttl);
        }
    }

    /**
     * Get a cached value.
     */
    protected function getCached(string $key, mixed $default = null): mixed
    {
        if (function_exists('cache')) {
            return cache()->get($key, $default);
        }

        return $default;
    }

    /**
     * Dispatch a job to the queue.
     */
    protected function dispatch(object $job): void
    {
        if (function_exists('dispatch')) {
            dispatch($job);
        }
    }

    /**
     * Fire an event.
     */
    protected function event(object|string $event, array $payload = []): void
    {
        if (function_exists('event')) {
            event($event, $payload);
        }
    }

    /**
     * Register the tool with a server.
     */
    public function register(LaravelMcpServer $server): void
    {
        $server->addTool($this->name(), [$this, 'handle'], [
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
        ]);
    }

    /**
     * Get tool metadata.
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
            'requiresAuth' => $this->requiresAuth(),
            'requiredScopes' => $this->requiredScopes(),
            'class' => static::class,
        ];
    }
}
