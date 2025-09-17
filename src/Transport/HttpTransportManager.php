<?php

namespace MCP\Laravel\Transport;

/**
 * Manager for HTTP transport operations.
 */
class HttpTransportManager
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('mcp.transports.http', []);
    }

    /**
     * Create a new HTTP transport instance.
     */
    public function create(array $options = []): array
    {
        return array_merge([
            'type' => 'http',
            'enabled' => $this->config['enabled'] ?? true,
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 3000,
            'routes' => $this->config['routes'] ?? [],
            'security' => $this->config['security'] ?? [],
        ], $options);
    }

    /**
     * Check if HTTP transport is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get transport configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
