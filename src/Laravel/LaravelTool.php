<?php

namespace MCP\Laravel\Laravel;

use MCP\Laravel\Contracts\McpToolInterface;
use MCP\Types\Content\TextContent;
use MCP\Types\Content\ImageContent;
use MCP\Types\Content\EmbeddedResource;
use MCP\UI\UIResource;
use MCP\UI\UITemplate;

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

    // =========================================================================
    // UI Resource Helpers (MCP-UI Integration)
    // =========================================================================

    /**
     * Create a UI card widget.
     *
     * @param array{title?: string, content?: string, footer?: string, actions?: array<array{label: string, action: string, data?: array}>} $options
     */
    protected function uiCard(array $options): array
    {
        return UITemplate::card($options);
    }

    /**
     * Create a UI table widget.
     *
     * @param string $title Table title
     * @param array<string> $headers Column headers
     * @param array<array<string|int|float>> $rows Table rows
     * @param array{actions?: array<array{label: string, action: string}>} $options Additional options
     */
    protected function uiTable(string $title, array $headers, array $rows, array $options = []): array
    {
        return UITemplate::table($title, $headers, $rows, $options);
    }

    /**
     * Create a UI stats dashboard widget.
     *
     * @param array<array{label: string, value: string|int|float, icon?: string, change?: string}> $stats
     * @param array{title?: string, actions?: array} $options
     */
    protected function uiStats(array $stats, array $options = []): array
    {
        return UITemplate::stats($stats, $options);
    }

    /**
     * Create a UI form widget.
     *
     * @param array<array{name: string, type: string, label?: string, value?: mixed, options?: array}> $fields
     * @param array{title?: string, submitLabel?: string, submitAction?: string, method?: string} $options
     */
    protected function uiForm(array $fields, array $options = []): array
    {
        return UITemplate::form($fields, $options);
    }

    /**
     * Create a raw HTML UI resource.
     *
     * @param string $uri Unique identifier for the resource
     * @param string $html HTML content
     */
    protected function uiHtml(string $uri, string $html): array
    {
        return UIResource::html($uri, $html);
    }

    /**
     * Create a UI resource from a Blade view.
     *
     * This is a Laravel-specific helper that renders a Blade view
     * and wraps it in a UI resource.
     *
     * @param string $view View name (e.g., 'mcp.widgets.weather')
     * @param array<string, mixed> $data Data to pass to the view
     * @param string|null $uri Custom URI (auto-generated if null)
     */
    protected function uiView(string $view, array $data = [], ?string $uri = null): array
    {
        $html = view($view, $data)->render();
        $uri = $uri ?? 'ui://view/' . str_replace('.', '/', $view) . '/' . md5(serialize($data));

        return UIResource::html($uri, $html);
    }

    /**
     * Combine text content with one or more UI resources.
     *
     * @param string $text Text message to include
     * @param array ...$uiResources UI resources created by ui* methods
     */
    protected function withUi(string $text, array ...$uiResources): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ];

        foreach ($uiResources as $resource) {
            $content[] = $resource;
        }

        return ['content' => $content];
    }

    /**
     * Create a URL-based UI resource.
     *
     * @param string $uri Unique identifier
     * @param string $url URL to load in the iframe
     */
    protected function uiUrl(string $uri, string $url): array
    {
        return UIResource::url($uri, $url);
    }

    /**
     * Create a remote DOM UI resource.
     *
     * @param string $uri Unique identifier
     * @param string $url URL that returns DOM updates
     */
    protected function uiRemoteDom(string $uri, string $url): array
    {
        return UIResource::remoteDom($uri, $url);
    }

    /**
     * Include the action handler JavaScript in a UI resource.
     *
     * This JavaScript enables widgets to communicate back to the server
     * through postMessage API calls.
     *
     * @param string $action Default action name
     * @param array<string, mixed> $defaultData Default data to include with actions
     */
    protected function uiActionScript(string $action = 'tool', array $defaultData = []): string
    {
        return UIResource::actionScript($action, $defaultData);
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
