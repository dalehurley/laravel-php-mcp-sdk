<?php

namespace MCP\Laravel\Transport;

/**
 * Manager for STDIO transport operations.
 */
class StdioTransportManager
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('mcp.transports.stdio', []);
    }

    /**
     * Create a new STDIO transport instance.
     */
    public function create(array $options = []): array
    {
        return array_merge([
            'type' => 'stdio',
            'enabled' => $this->config['enabled'] ?? true,
            'buffer_size' => $this->config['buffer_size'] ?? 8192,
            'timeout' => $this->config['timeout'] ?? 30,
        ], $options);
    }

    /**
     * Check if STDIO transport is enabled.
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
