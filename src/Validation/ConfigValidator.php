<?php

namespace MCP\Laravel\Validation;

use Illuminate\Support\Facades\Validator;
use MCP\Laravel\Exceptions\McpException;

/**
 * Validates MCP configuration at runtime.
 */
class ConfigValidator
{
    /**
     * Validate the complete MCP configuration.
     */
    public static function validate(): void
    {
        $config = config('mcp');

        if (!$config) {
            throw new McpException('MCP configuration not found. Please publish the configuration file.');
        }

        static::validateBasicStructure($config);
        static::validateServers($config['servers'] ?? []);
        static::validateClients($config['clients'] ?? []);
        static::validateTransports($config['transports'] ?? []);
    }

    /**
     * Validate basic configuration structure.
     */
    protected static function validateBasicStructure(array $config): void
    {
        $rules = [
            'default_server' => 'required|string',
            'default_client' => 'required|string',
            'servers' => 'required|array|min:1',
            'clients' => 'required|array|min:1',
        ];

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            throw new McpException(
                'Invalid MCP configuration structure: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate server configurations.
     */
    protected static function validateServers(array $servers): void
    {
        foreach ($servers as $name => $server) {
            $rules = [
                'name' => 'required|string',
                'version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
                'transport' => 'required|in:stdio,http,websocket',
                'capabilities' => 'array',
                'auth.enabled' => 'boolean',
            ];

            $validator = Validator::make($server, $rules);

            if ($validator->fails()) {
                throw new McpException(
                    "Invalid server configuration for '{$name}': " . $validator->errors()->first()
                );
            }

            // Transport-specific validation
            static::validateTransportConfig($name, $server['transport'], $server);
        }
    }

    /**
     * Validate client configurations.
     */
    protected static function validateClients(array $clients): void
    {
        foreach ($clients as $name => $client) {
            $rules = [
                'name' => 'required|string',
                'version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
            ];

            $validator = Validator::make($client, $rules);

            if ($validator->fails()) {
                throw new McpException(
                    "Invalid client configuration for '{$name}': " . $validator->errors()->first()
                );
            }
        }
    }

    /**
     * Validate transport configurations.
     */
    protected static function validateTransports(array $transports): void
    {
        foreach (['stdio', 'http', 'websocket'] as $transport) {
            if (!isset($transports[$transport])) {
                continue;
            }

            $config = $transports[$transport];

            switch ($transport) {
                case 'http':
                    static::validateHttpTransport($config);
                    break;
                case 'websocket':
                    static::validateWebSocketTransport($config);
                    break;
                case 'stdio':
                    static::validateStdioTransport($config);
                    break;
            }
        }
    }

    /**
     * Validate HTTP transport configuration.
     */
    protected static function validateHttpTransport(array $config): void
    {
        $rules = [
            'enabled' => 'boolean',
            'host' => 'required_if:enabled,true|ip',
            'port' => 'required_if:enabled,true|integer|min:1|max:65535',
            'security.cors_enabled' => 'boolean',
            'security.allowed_origins' => 'array',
            'security.rate_limiting' => 'string|regex:/^\d+,\d+$/',
        ];

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            throw new McpException(
                'Invalid HTTP transport configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate WebSocket transport configuration.
     */
    protected static function validateWebSocketTransport(array $config): void
    {
        $rules = [
            'enabled' => 'boolean',
            'host' => 'required_if:enabled,true|ip',
            'port' => 'required_if:enabled,true|integer|min:1|max:65535',
        ];

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            throw new McpException(
                'Invalid WebSocket transport configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate STDIO transport configuration.
     */
    protected static function validateStdioTransport(array $config): void
    {
        $rules = [
            'enabled' => 'boolean',
        ];

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            throw new McpException(
                'Invalid STDIO transport configuration: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Validate transport-specific server configuration.
     */
    protected static function validateTransportConfig(string $serverName, string $transport, array $config): void
    {
        switch ($transport) {
            case 'http':
                if (!isset($config['port']) && !config('mcp.transports.http.port')) {
                    throw new McpException(
                        "Server '{$serverName}' uses HTTP transport but no port is configured"
                    );
                }
                break;
            case 'websocket':
                if (!isset($config['port']) && !config('mcp.transports.websocket.port')) {
                    throw new McpException(
                        "Server '{$serverName}' uses WebSocket transport but no port is configured"
                    );
                }
                break;
        }
    }

    /**
     * Get validation summary for debugging.
     */
    public static function getSummary(): array
    {
        $config = config('mcp');

        return [
            'servers_count' => count($config['servers'] ?? []),
            'clients_count' => count($config['clients'] ?? []),
            'transports_enabled' => array_filter([
                'stdio' => $config['transports']['stdio']['enabled'] ?? false,
                'http' => $config['transports']['http']['enabled'] ?? false,
                'websocket' => $config['transports']['websocket']['enabled'] ?? false,
            ]),
            'auth_enabled' => collect($config['servers'] ?? [])
                ->pluck('auth.enabled')
                ->filter()
                ->count() > 0,
        ];
    }
}
