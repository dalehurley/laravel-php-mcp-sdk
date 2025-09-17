<?php

namespace MCP\Laravel\Laravel;

use MCP\Laravel\Contracts\McpPromptInterface;

/**
 * Base class for Laravel MCP prompts.
 * 
 * Provides a Laravel-friendly interface for creating MCP prompts with
 * built-in support for argument validation and content generation.
 */
abstract class LaravelPrompt implements McpPromptInterface
{
    /**
     * Get the prompt name.
     */
    abstract public function name(): string;

    /**
     * Get the prompt description.
     */
    abstract public function description(): string;

    /**
     * Handle the prompt execution.
     */
    abstract public function handle(array $args): array;

    /**
     * Get the prompt arguments schema.
     */
    public function arguments(): array
    {
        return $this->argumentSchema();
    }

    /**
     * Define the prompt's argument schema.
     */
    protected function argumentSchema(): array
    {
        return [];
    }

    /**
     * Determine if the prompt requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Get required scopes for the prompt.
     */
    public function requiredScopes(): array
    {
        return [];
    }

    /**
     * Validate prompt arguments.
     */
    protected function validate(array $args): array
    {
        // Use Laravel's validator if available
        if (function_exists('validator')) {
            $rules = $this->validationRules();
            if (!empty($rules)) {
                $validator = validator($args, $rules);
                if ($validator->fails()) {
                    throw new \InvalidArgumentException('Validation failed: ' . $validator->errors()->first());
                }
            }
        }

        return $args;
    }

    /**
     * Define validation rules for arguments.
     */
    protected function validationRules(): array
    {
        return [];
    }

    /**
     * Create a prompt message.
     */
    protected function createMessage(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => [
                'type' => 'text',
                'text' => $content,
            ]
        ];
    }

    /**
     * Create a system message.
     */
    protected function systemMessage(string $content): array
    {
        return $this->createMessage('system', $content);
    }

    /**
     * Create a user message.
     */
    protected function userMessage(string $content): array
    {
        return $this->createMessage('user', $content);
    }

    /**
     * Create an assistant message.
     */
    protected function assistantMessage(string $content): array
    {
        return $this->createMessage('assistant', $content);
    }

    /**
     * Create a prompt response with multiple messages.
     */
    protected function createPromptResponse(array $messages, ?string $description = null): array
    {
        $response = [
            'messages' => $messages,
        ];

        if ($description !== null) {
            $response['description'] = $description;
        }

        return $response;
    }

    /**
     * Create a simple text prompt response.
     */
    protected function textPrompt(string $text, ?string $description = null): array
    {
        return $this->createPromptResponse([
            $this->userMessage($text)
        ], $description);
    }

    /**
     * Create a system prompt with user input.
     */
    protected function systemPrompt(string $systemPrompt, string $userInput, ?string $description = null): array
    {
        return $this->createPromptResponse([
            $this->systemMessage($systemPrompt),
            $this->userMessage($userInput)
        ], $description);
    }

    /**
     * Create a conversation prompt.
     */
    protected function conversationPrompt(array $conversation, ?string $description = null): array
    {
        return $this->createPromptResponse($conversation, $description);
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
     * Interpolate variables in a template string.
     */
    protected function interpolate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", (string) $value, $template);
        }

        return $template;
    }

    /**
     * Load a prompt template from file.
     */
    protected function loadTemplate(string $path, array $variables = []): string
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Template file not found: {$path}");
        }

        $template = file_get_contents($path);

        if (!empty($variables)) {
            $template = $this->interpolate($template, $variables);
        }

        return $template;
    }

    /**
     * Format text with proper line breaks and spacing.
     */
    protected function formatText(string $text): string
    {
        // Remove excessive whitespace while preserving intentional formatting
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Create a code block.
     */
    protected function codeBlock(string $code, ?string $language = null): string
    {
        $lang = $language ? $language : '';
        return "```{$lang}\n{$code}\n```";
    }

    /**
     * Create a markdown list.
     */
    protected function markdownList(array $items, bool $numbered = false): string
    {
        $list = '';
        $counter = 1;

        foreach ($items as $item) {
            if ($numbered) {
                $list .= "{$counter}. {$item}\n";
                $counter++;
            } else {
                $list .= "- {$item}\n";
            }
        }

        return trim($list);
    }

    /**
     * Log prompt execution.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger()->log($level, "[MCP Prompt: {$this->name()}] {$message}", $context);
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
            cache()->put("mcp:prompt:{$key}", $value, $ttl);
        }
    }

    /**
     * Get a cached value.
     */
    protected function getCached(string $key, mixed $default = null): mixed
    {
        if (function_exists('cache')) {
            return cache()->get("mcp:prompt:{$key}", $default);
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
     * Register the prompt with a server.
     */
    public function register(LaravelMcpServer $server): void
    {
        $server->addPrompt($this->name(), [$this, 'handle'], [
            'description' => $this->description(),
            'arguments' => $this->arguments(),
        ]);
    }

    /**
     * Get prompt metadata.
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'arguments' => $this->arguments(),
            'requiresAuth' => $this->requiresAuth(),
            'requiredScopes' => $this->requiredScopes(),
            'class' => static::class,
        ];
    }
}
