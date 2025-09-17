<?php

namespace MCP\Laravel\Transport;

/**
 * Manager for WebSocket transport operations.
 */
class WebSocketTransportManager
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('mcp.transports.websocket', []);
    }

    /**
     * Create a new WebSocket transport instance.
     */
    public function create(array $options = []): array
    {
        return array_merge([
            'type' => 'websocket',
            'enabled' => $this->config['enabled'] ?? false,
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 3001,
            'heartbeat_interval' => $this->config['heartbeat_interval'] ?? 30,
            'max_connections' => $this->config['max_connections'] ?? 1000,
        ], $options);
    }

    /**
     * Check if WebSocket transport is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Get transport configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
