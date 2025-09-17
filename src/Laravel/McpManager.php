<?php

namespace MCP\Laravel\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use MCP\Laravel\Exceptions\McpException;

/**
 * Central manager for MCP operations in Laravel.
 * 
 * Coordinates multiple server and client instances, providing a unified
 * interface for managing MCP operations across the application.
 */
class McpManager
{
    protected Container $app;
    protected ServerManager $serverManager;
    protected ClientManager $clientManager;
    protected Collection $activeConnections;

    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->activeConnections = new Collection();
    }

    /**
     * Get the server manager instance.
     */
    public function servers(): ServerManager
    {
        if (!isset($this->serverManager)) {
            $this->serverManager = $this->app->make(ServerManager::class);
        }

        return $this->serverManager;
    }

    /**
     * Get the client manager instance.
     */
    public function clients(): ClientManager
    {
        if (!isset($this->clientManager)) {
            $this->clientManager = $this->app->make(ClientManager::class);
        }

        return $this->clientManager;
    }

    /**
     * Get a specific server instance.
     */
    public function server(?string $name = null): LaravelMcpServer
    {
        return $this->servers()->get($name);
    }

    /**
     * Get a specific client instance.
     */
    public function client(?string $name = null): LaravelMcpClient
    {
        return $this->clients()->get($name);
    }

    /**
     * Start a server with the specified transport.
     */
    public function startServer(?string $name = null, ?string $transport = null): void
    {
        $this->servers()->start($name, $transport);
    }

    /**
     * Connect a client to a server.
     */
    public function connectClient(string $name, string $serverUrl, ?string $transport = null): void
    {
        $this->clients()->connect($name, $serverUrl, $transport);
    }

    /**
     * List all configured servers.
     */
    public function listServers(): array
    {
        return $this->servers()->list();
    }

    /**
     * List all configured clients.
     */
    public function listClients(): array
    {
        return $this->clients()->list();
    }

    /**
     * Get server status.
     */
    public function getServerStatus(?string $name = null): array
    {
        return $this->servers()->getStatus($name);
    }

    /**
     * Get client status.
     */
    public function getClientStatus(?string $name = null): array
    {
        return $this->clients()->getStatus($name);
    }

    /**
     * Stop a server.
     */
    public function stopServer(?string $name = null): void
    {
        $this->servers()->stop($name);
    }

    /**
     * Disconnect a client.
     */
    public function disconnectClient(?string $name = null): void
    {
        $this->clients()->disconnect($name);
    }

    /**
     * Check if a server is running.
     */
    public function isServerRunning(?string $name = null): bool
    {
        return $this->servers()->isRunning($name);
    }

    /**
     * Check if a client is connected.
     */
    public function isClientConnected(?string $name = null): bool
    {
        return $this->clients()->isConnected($name);
    }

    /**
     * Get overall system status.
     */
    public function getSystemStatus(): array
    {
        return [
            'servers' => $this->servers()->list(),
            'clients' => $this->clients()->list(),
            'active_connections' => $this->activeConnections->count(),
            'configuration' => [
                'default_server' => config('mcp.default_server'),
                'default_client' => config('mcp.default_client'),
                'transports_enabled' => [
                    'stdio' => config('mcp.transports.stdio.enabled', true),
                    'http' => config('mcp.transports.http.enabled', true),
                    'websocket' => config('mcp.transports.websocket.enabled', false),
                ],
                'features_enabled' => [
                    'authorization' => config('mcp.authorization.enabled', false),
                    'caching' => config('mcp.cache.enabled', true),
                    'queue' => config('mcp.queue.enabled', false),
                    'events' => config('mcp.events.enabled', true),
                ],
            ],
        ];
    }

    /**
     * Perform health check on all MCP components.
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now()->toISOString(),
        ];

        // Check server health
        foreach ($this->servers()->list() as $serverName => $serverInfo) {
            try {
                $server = $this->servers()->get($serverName);
                $health['checks']["server_{$serverName}"] = [
                    'status' => $server->isRunning() ? 'healthy' : 'stopped',
                    'details' => $server->getStatus(),
                ];
            } catch (\Exception $e) {
                $health['checks']["server_{$serverName}"] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                $health['status'] = 'degraded';
            }
        }

        // Check client health
        foreach ($this->clients()->list() as $clientName => $clientInfo) {
            try {
                $client = $this->clients()->get($clientName);
                $health['checks']["client_{$clientName}"] = [
                    'status' => $client->isConnected() ? 'healthy' : 'disconnected',
                    'details' => $client->getStatus(),
                ];
            } catch (\Exception $e) {
                $health['checks']["client_{$clientName}"] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                $health['status'] = 'degraded';
            }
        }

        return $health;
    }

    /**
     * Shutdown all MCP components gracefully.
     */
    public function shutdown(): void
    {
        // Stop all servers
        foreach ($this->servers()->list() as $serverName => $serverInfo) {
            try {
                $this->servers()->stop($serverName);
            } catch (\Exception $e) {
                // Log error but continue shutdown
                logger()->warning("Failed to stop MCP server {$serverName}: " . $e->getMessage());
            }
        }

        // Disconnect all clients
        foreach ($this->clients()->list() as $clientName => $clientInfo) {
            try {
                $this->clients()->disconnect($clientName);
            } catch (\Exception $e) {
                // Log error but continue shutdown
                logger()->warning("Failed to disconnect MCP client {$clientName}: " . $e->getMessage());
            }
        }

        $this->activeConnections = new Collection();
    }

    /**
     * Register an active connection.
     */
    public function registerConnection(string $type, string $name, array $details): void
    {
        $this->activeConnections->put("{$type}:{$name}", [
            'type' => $type,
            'name' => $name,
            'details' => $details,
            'registered_at' => now(),
        ]);
    }

    /**
     * Unregister an active connection.
     */
    public function unregisterConnection(string $type, string $name): void
    {
        $this->activeConnections->forget("{$type}:{$name}");
    }

    /**
     * Get all active connections.
     */
    public function getActiveConnections(): Collection
    {
        return $this->activeConnections;
    }
}
