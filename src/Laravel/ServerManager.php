<?php

namespace MCP\Laravel\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use MCP\Laravel\Exceptions\McpException;
use MCP\Laravel\Exceptions\ServerNotFoundException;

/**
 * Manages multiple MCP server instances.
 * 
 * Provides functionality to create, configure, start, stop, and manage
 * multiple MCP servers with different transports and capabilities.
 */
class ServerManager
{
    protected Container $app;
    protected McpManager $mcpManager;
    protected Collection $servers;
    protected array $runningServers = [];

    public function __construct(Container $app, McpManager $mcpManager)
    {
        $this->app = $app;
        $this->mcpManager = $mcpManager;
        $this->servers = new Collection();
    }

    /**
     * Get a server instance by name.
     */
    public function get(?string $name = null): LaravelMcpServer
    {
        $name = $name ?? config('mcp.default_server', 'main');

        if (!$this->servers->has($name)) {
            $this->servers->put($name, $this->createServer($name));
        }

        return $this->servers->get($name);
    }

    /**
     * Create a new server instance.
     */
    public function create(string $name, array $config = []): LaravelMcpServer
    {
        $serverConfig = array_merge(
            config("mcp.servers.{$name}", []),
            $config
        );

        if (empty($serverConfig)) {
            throw new McpException("Server configuration not found for: {$name}");
        }

        $server = new LaravelMcpServer($this->app, $name, $serverConfig);
        $this->servers->put($name, $server);

        return $server;
    }

    /**
     * Start a server with the specified transport.
     */
    public function start(?string $name = null, ?string $transport = null): void
    {
        $server = $this->get($name);
        $serverName = $name ?? config('mcp.default_server', 'main');

        $server->start($transport);
        $this->runningServers[$serverName] = [
            'started_at' => now(),
            'transport' => $transport ?? $server->getTransport(),
            'pid' => $server->getProcessId(),
        ];

        $this->mcpManager->registerConnection('server', $serverName, [
            'transport' => $server->getTransport(),
            'capabilities' => $server->getCapabilities(),
            'status' => 'running',
        ]);
    }

    /**
     * Stop a server.
     */
    public function stop(?string $name = null): void
    {
        $serverName = $name ?? config('mcp.default_server', 'main');

        if ($this->servers->has($serverName)) {
            $server = $this->servers->get($serverName);
            $server->stop();
            unset($this->runningServers[$serverName]);
            $this->mcpManager->unregisterConnection('server', $serverName);
        }
    }

    /**
     * Check if a server exists.
     */
    public function exists(string $name): bool
    {
        return config("mcp.servers.{$name}") !== null;
    }

    /**
     * Check if a server is running.
     */
    public function isRunning(?string $name = null): bool
    {
        $serverName = $name ?? config('mcp.default_server', 'main');

        if (!isset($this->runningServers[$serverName])) {
            return false;
        }

        if ($this->servers->has($serverName)) {
            return $this->servers->get($serverName)->isRunning();
        }

        return false;
    }

    /**
     * List all configured servers.
     */
    public function list(): array
    {
        $servers = [];
        $serverConfigs = config('mcp.servers', []);

        foreach ($serverConfigs as $name => $config) {
            $servers[$name] = [
                'name' => $name,
                'display_name' => $config['name'] ?? $name,
                'version' => $config['version'] ?? '1.0.0',
                'transport' => $config['transport'] ?? 'stdio',
                'capabilities' => $config['capabilities'] ?? [],
                'running' => $this->isRunning($name),
                'started_at' => $this->runningServers[$name]['started_at'] ?? null,
            ];
        }

        return $servers;
    }

    /**
     * Get server status.
     */
    public function getStatus(?string $name = null): array
    {
        $serverName = $name ?? config('mcp.default_server', 'main');

        if (!$this->exists($serverName)) {
            throw new ServerNotFoundException("Server not found: {$serverName}");
        }

        $status = [
            'name' => $serverName,
            'running' => $this->isRunning($serverName),
            'configuration' => config("mcp.servers.{$serverName}", []),
        ];

        if ($this->servers->has($serverName)) {
            $server = $this->servers->get($serverName);
            $status = array_merge($status, [
                'transport' => $server->getTransport(),
                'capabilities' => $server->getCapabilities(),
                'tools_count' => count($server->getTools()),
                'resources_count' => count($server->getResources()),
                'prompts_count' => count($server->getPrompts()),
                'process_id' => $server->getProcessId(),
                'memory_usage' => $server->getMemoryUsage(),
                'uptime' => $server->getUptime(),
            ]);
        }

        if (isset($this->runningServers[$serverName])) {
            $status['started_at'] = $this->runningServers[$serverName]['started_at'];
            $status['runtime_transport'] = $this->runningServers[$serverName]['transport'];
        }

        return $status;
    }

    /**
     * Add a tool to a specific server.
     */
    public function addTool(string $serverName, string $toolName, callable $handler, array $schema = []): void
    {
        $server = $this->get($serverName);
        $server->addTool($toolName, $handler, $schema);
    }

    /**
     * Add a resource to a specific server.
     */
    public function addResource(string $serverName, string $uri, callable $handler, array $metadata = []): void
    {
        $server = $this->get($serverName);
        $server->addResource($uri, $handler, $metadata);
    }

    /**
     * Add a prompt to a specific server.
     */
    public function addPrompt(string $serverName, string $promptName, callable $handler, array $schema = []): void
    {
        $server = $this->get($serverName);
        $server->addPrompt($promptName, $handler, $schema);
    }

    /**
     * Remove a tool from a specific server.
     */
    public function removeTool(string $serverName, string $toolName): void
    {
        $server = $this->get($serverName);
        $server->removeTool($toolName);
    }

    /**
     * Remove a resource from a specific server.
     */
    public function removeResource(string $serverName, string $uri): void
    {
        $server = $this->get($serverName);
        $server->removeResource($uri);
    }

    /**
     * Remove a prompt from a specific server.
     */
    public function removePrompt(string $serverName, string $promptName): void
    {
        $server = $this->get($serverName);
        $server->removePrompt($promptName);
    }

    /**
     * Get tools from a specific server.
     */
    public function getTools(?string $serverName = null): array
    {
        $server = $this->get($serverName);
        return $server->getTools();
    }

    /**
     * Get resources from a specific server.
     */
    public function getResources(?string $serverName = null): array
    {
        $server = $this->get($serverName);
        return $server->getResources();
    }

    /**
     * Get prompts from a specific server.
     */
    public function getPrompts(?string $serverName = null): array
    {
        $server = $this->get($serverName);
        return $server->getPrompts();
    }

    /**
     * Get capabilities from a specific server.
     */
    public function getCapabilities(?string $serverName = null): array
    {
        $server = $this->get($serverName);
        return $server->getCapabilities();
    }

    /**
     * Set capabilities for a specific server.
     */
    public function setCapabilities(string $serverName, array $capabilities): void
    {
        $server = $this->get($serverName);
        $server->setCapabilities($capabilities);
    }

    /**
     * Discover components in specified directories.
     */
    public function discover(?string $serverName = null, array $directories = []): void
    {
        $server = $this->get($serverName);
        $server->discover($directories);
    }

    /**
     * Register multiple components at once.
     */
    public function registerBatch(string $serverName, array $components): void
    {
        $server = $this->get($serverName);
        $server->registerBatch($components);
    }

    /**
     * Create a server instance.
     */
    protected function createServer(string $name): LaravelMcpServer
    {
        $config = config("mcp.servers.{$name}");

        if (!$config) {
            throw new McpException("Server configuration not found for: {$name}");
        }

        return new LaravelMcpServer($this->app, $name, $config);
    }

    /**
     * Get all running servers.
     */
    public function getRunningServers(): array
    {
        return $this->runningServers;
    }

    /**
     * Stop all running servers.
     */
    public function stopAll(): void
    {
        foreach (array_keys($this->runningServers) as $serverName) {
            $this->stop($serverName);
        }
    }

    /**
     * Restart a server.
     */
    public function restart(?string $name = null): void
    {
        $serverName = $name ?? config('mcp.default_server', 'main');
        $transport = null;

        if (isset($this->runningServers[$serverName])) {
            $transport = $this->runningServers[$serverName]['transport'];
            $this->stop($serverName);
        }

        $this->start($serverName, $transport);
    }
}
