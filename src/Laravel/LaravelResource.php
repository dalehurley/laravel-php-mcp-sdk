<?php

namespace MCP\Laravel\Laravel;

use MCP\Laravel\Contracts\McpResourceInterface;
use MCP\Types\Content\TextContent;
use MCP\Types\Content\ImageContent;
use MCP\Shared\UriTemplate;

/**
 * Base class for Laravel MCP resources.
 * 
 * Provides a Laravel-friendly interface for creating MCP resources with
 * built-in support for URI templates, caching, and content helpers.
 */
abstract class LaravelResource implements McpResourceInterface
{
    /**
     * Get the resource URI or URI template.
     */
    abstract public function uri(): string;

    /**
     * Get the resource description.
     */
    abstract public function description(): string;

    /**
     * Read the resource content.
     */
    abstract public function read(string $uri): array;

    /**
     * Determine if the resource requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Get required scopes for the resource.
     */
    public function requiredScopes(): array
    {
        return [];
    }

    /**
     * Get resource metadata.
     */
    public function metadata(): array
    {
        return [
            'uri' => $this->uri(),
            'name' => $this->name(),
            'description' => $this->description(),
            'mimeType' => $this->mimeType(),
        ];
    }

    /**
     * Get the resource name.
     */
    protected function name(): string
    {
        return basename($this->uri());
    }

    /**
     * Get the resource MIME type.
     */
    protected function mimeType(): string
    {
        return 'text/plain';
    }

    /**
     * Check if the URI matches this resource.
     */
    public function matches(string $uri): bool
    {
        $resourceUri = $this->uri();

        // Exact match
        if ($uri === $resourceUri) {
            return true;
        }

        // URI template match
        if (str_contains($resourceUri, '{')) {
            return $this->matchesTemplate($uri, $resourceUri);
        }

        // Pattern match (simple wildcards)
        if (str_contains($resourceUri, '*')) {
            $pattern = str_replace('*', '.*', preg_quote($resourceUri, '/'));
            return preg_match("/^{$pattern}$/", $uri) === 1;
        }

        return false;
    }

    /**
     * Extract variables from URI using template.
     */
    protected function extractUriVariables(string $uri): array
    {
        $template = $this->uri();

        if (!str_contains($template, '{')) {
            return [];
        }

        return $this->extractVariablesFromTemplate($uri, $template);
    }

    /**
     * Check if URI matches a template.
     */
    protected function matchesTemplate(string $uri, string $template): bool
    {
        // Simple template matching - convert {var} to regex groups
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', preg_quote($template, '/'));
        return preg_match("/^{$pattern}$/", $uri) === 1;
    }

    /**
     * Extract variables from template.
     */
    protected function extractVariablesFromTemplate(string $uri, string $template): array
    {
        // Extract variable names from template
        preg_match_all('/\{([^}]+)\}/', $template, $templateMatches);
        $variableNames = $templateMatches[1];

        // Convert template to regex and extract values
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', preg_quote($template, '/'));
        preg_match("/^{$pattern}$/", $uri, $valueMatches);

        $variables = [];
        for ($i = 0; $i < count($variableNames); $i++) {
            if (isset($valueMatches[$i + 1])) {
                $variables[$variableNames[$i]] = $valueMatches[$i + 1];
            }
        }

        return $variables;
    }

    /**
     * Create a text content response.
     */
    protected function textContent(string $text, ?string $mimeType = null): array
    {
        return [
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $text,
                    'mimeType' => $mimeType ?? 'text/plain',
                ]
            ]
        ];
    }

    /**
     * Create a binary content response.
     */
    protected function binaryContent(string $data, string $mimeType): array
    {
        return [
            'contents' => [
                [
                    'type' => 'blob',
                    'blob' => base64_encode($data),
                    'mimeType' => $mimeType,
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
            'contents' => [
                [
                    'type' => 'image',
                    'data' => base64_encode($data),
                    'mimeType' => $mimeType,
                ]
            ]
        ];
    }

    /**
     * Create a JSON content response.
     */
    protected function jsonContent(array $data): array
    {
        return [
            'contents' => [
                [
                    'type' => 'text',
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                    'mimeType' => 'application/json',
                ]
            ]
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
     * Read file content.
     */
    protected function readFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException("File not readable: {$path}");
        }

        return file_get_contents($path);
    }

    /**
     * Get file MIME type.
     */
    protected function getFileMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path) ?: 'application/octet-stream';
        }

        // Fallback based on extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'php' => 'text/x-php',
            'py' => 'text/x-python',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Cache resource content.
     */
    protected function cache(string $key, mixed $value, int $ttl = 3600): void
    {
        if (function_exists('cache')) {
            cache()->put("mcp:resource:{$key}", $value, $ttl);
        }
    }

    /**
     * Get cached resource content.
     */
    protected function getCached(string $key, mixed $default = null): mixed
    {
        if (function_exists('cache')) {
            return cache()->get("mcp:resource:{$key}", $default);
        }

        return $default;
    }

    /**
     * Log resource access.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger()->log($level, "[MCP Resource: {$this->uri()}] {$message}", $context);
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
     * Fire an event.
     */
    protected function event(object|string $event, array $payload = []): void
    {
        if (function_exists('event')) {
            event($event, $payload);
        }
    }

    /**
     * Register the resource with a server.
     */
    public function register(LaravelMcpServer $server): void
    {
        $server->addResource($this->uri(), [$this, 'read'], $this->metadata());
    }

    /**
     * Get resource metadata for registration.
     */
    public function getMetadata(): array
    {
        return [
            'uri' => $this->uri(),
            'name' => $this->name(),
            'description' => $this->description(),
            'mimeType' => $this->mimeType(),
            'requiresAuth' => $this->requiresAuth(),
            'requiredScopes' => $this->requiredScopes(),
            'class' => static::class,
        ];
    }
}
