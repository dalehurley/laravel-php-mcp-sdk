<?php

namespace MCP\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Event fired when a UI action is received from an MCP-UI widget.
 *
 * This event is dispatched whenever a UI widget sends an action back
 * to the server via the postMessage API. It can be used to:
 * - Log UI interactions
 * - Trigger additional processing
 * - Broadcast widget updates to other clients
 * - Implement custom action handlers
 */
class UiActionReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $type Action type: 'tool', 'notification', 'prompt', 'link'
     * @param array $payload The action payload data
     * @param string|null $serverName The server handling the action
     * @param string|null $userId The user ID if authenticated
     * @param string|null $widgetUri The URI of the widget that sent the action
     */
    public function __construct(
        public string $type,
        public array $payload,
        public ?string $serverName = null,
        public ?string $userId = null,
        public ?string $widgetUri = null
    ) {}

    /**
     * Check if this is a tool action.
     */
    public function isTool(): bool
    {
        return $this->type === 'tool';
    }

    /**
     * Check if this is a notification action.
     */
    public function isNotification(): bool
    {
        return $this->type === 'notification';
    }

    /**
     * Check if this is a prompt action.
     */
    public function isPrompt(): bool
    {
        return $this->type === 'prompt';
    }

    /**
     * Check if this is a link action.
     */
    public function isLink(): bool
    {
        return $this->type === 'link';
    }

    /**
     * Get the tool name if this is a tool action.
     */
    public function getToolName(): ?string
    {
        return $this->payload['name'] ?? null;
    }

    /**
     * Get the tool arguments if this is a tool action.
     */
    public function getToolArguments(): array
    {
        return $this->payload['arguments'] ?? [];
    }

    /**
     * Get the notification message if this is a notification action.
     */
    public function getNotificationMessage(): ?string
    {
        return $this->payload['message'] ?? null;
    }

    /**
     * Get the notification level if this is a notification action.
     */
    public function getNotificationLevel(): string
    {
        return $this->payload['level'] ?? 'info';
    }

    /**
     * Get the link URL if this is a link action.
     */
    public function getLinkUrl(): ?string
    {
        return $this->payload['url'] ?? null;
    }

    /**
     * Get the prompt name if this is a prompt action.
     */
    public function getPromptName(): ?string
    {
        return $this->payload['name'] ?? null;
    }

    /**
     * Get the prompt arguments if this is a prompt action.
     */
    public function getPromptArguments(): array
    {
        return $this->payload['arguments'] ?? [];
    }
}

