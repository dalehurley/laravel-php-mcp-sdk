<?php

namespace MCP\Laravel\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use MCP\Laravel\Exceptions\McpException;
use MCP\Laravel\Exceptions\ClientNotFoundException;

/**
 * Manages multiple MCP client instances.
 * 
 * Provides functionality to create, configure, connect, disconnect, and manage
 * multiple MCP clients connecting to different servers with various transports.
 */
class ClientManager
{
    protected Container $app;
    protected McpManager $mcpManager;
    protected Collection $clients;
    protected array $connectedClients = [];

    public function __construct(Container $app, McpManager $mcpManager)
    {
        $this->app = $app;
        $this->mcpManager = $mcpManager;
        $this->clients = new Collection();
    }

    /**
     * Get a client instance by name.
     */
    public function get(?string $name = null): LaravelMcpClient
    {
        $name = $name ?? config('mcp.default_client', 'main');

        if (!$this->clients->has($name)) {
            $this->clients->put($name, $this->createClient($name));
        }

        return $this->clients->get($name);
    }

    /**
     * Create a new client instance.
     */
    public function create(string $name, array $config = []): LaravelMcpClient
    {
        $clientConfig = array_merge(
            config("mcp.clients.{$name}", []),
            $config
        );

        if (empty($clientConfig)) {
            throw new McpException("Client configuration not found for: {$name}");
        }

        $client = new LaravelMcpClient($this->app, $name, $clientConfig);
        $this->clients->put($name, $client);

        return $client;
    }

    /**
     * Connect a client to a server.
     */
    public function connect(string $name, string $serverUrl, ?string $transport = null, array $options = []): void
    {
        $client = $this->get($name);

        $client->connect($serverUrl, $transport, $options);
        $this->connectedClients[$name] = [
            'connected_at' => now(),
            'server_url' => $serverUrl,
            'transport' => $transport ?? 'auto',
            'options' => $options,
        ];

        $this->mcpManager->registerConnection('client', $name, [
            'server_url' => $serverUrl,
            'transport' => $client->getTransport(),
            'capabilities' => $client->getCapabilities(),
            'status' => 'connected',
        ]);
    }

    /**
     * Disconnect a client.
     */
    public function disconnect(?string $name = null): void
    {
        $clientName = $name ?? config('mcp.default_client', 'main');

        if ($this->clients->has($clientName)) {
            $client = $this->clients->get($clientName);
            $client->disconnect();
            unset($this->connectedClients[$clientName]);
            $this->mcpManager->unregisterConnection('client', $clientName);
        }
    }

    /**
     * Check if a client exists.
     */
    public function exists(string $name): bool
    {
        return config("mcp.clients.{$name}") !== null;
    }

    /**
     * Check if a client is connected.
     */
    public function isConnected(?string $name = null): bool
    {
        $clientName = $name ?? config('mcp.default_client', 'main');

        if (!isset($this->connectedClients[$clientName])) {
            return false;
        }

        if ($this->clients->has($clientName)) {
            return $this->clients->get($clientName)->isConnected();
        }

        return false;
    }

    /**
     * List all configured clients.
     */
    public function list(): array
    {
        $clients = [];
        $clientConfigs = config('mcp.clients', []);

        foreach ($clientConfigs as $name => $config) {
            $clients[$name] = [
                'name' => $name,
                'display_name' => $config['name'] ?? $name,
                'version' => $config['version'] ?? '1.0.0',
                'capabilities' => $config['capabilities'] ?? [],
                'connected' => $this->isConnected($name),
                'connected_at' => $this->connectedClients[$name]['connected_at'] ?? null,
                'server_url' => $this->connectedClients[$name]['server_url'] ?? null,
            ];
        }

        return $clients;
    }

    /**
     * Get client status.
     */
    public function getStatus(?string $name = null): array
    {
        $clientName = $name ?? config('mcp.default_client', 'main');

        if (!$this->exists($clientName)) {
            throw new ClientNotFoundException("Client not found: {$clientName}");
        }

        $status = [
            'name' => $clientName,
            'connected' => $this->isConnected($clientName),
            'configuration' => config("mcp.clients.{$clientName}", []),
        ];

        if ($this->clients->has($clientName)) {
            $client = $this->clients->get($clientName);
            $status = array_merge($status, [
                'transport' => $client->getTransport(),
                'capabilities' => $client->getCapabilities(),
                'server_capabilities' => $client->getServerCapabilities(),
                'connection_info' => $client->getConnectionInfo(),
                'last_ping' => $client->getLastPing(),
                'request_count' => $client->getRequestCount(),
                'error_count' => $client->getErrorCount(),
            ]);
        }

        if (isset($this->connectedClients[$clientName])) {
            $status['connected_at'] = $this->connectedClients[$clientName]['connected_at'];
            $status['server_url'] = $this->connectedClients[$clientName]['server_url'];
            $status['runtime_transport'] = $this->connectedClients[$clientName]['transport'];
        }

        return $status;
    }

    /**
     * Call a tool on a specific client.
     */
    public function callTool(string $clientName, string $toolName, array $params = []): mixed
    {
        $client = $this->get($clientName);
        return $client->callTool($toolName, $params);
    }

    /**
     * Read a resource from a specific client.
     */
    public function readResource(string $clientName, string $uri): mixed
    {
        $client = $this->get($clientName);
        return $client->readResource($uri);
    }

    /**
     * Get a prompt from a specific client.
     */
    public function getPrompt(string $clientName, string $promptName, array $args = []): mixed
    {
        $client = $this->get($clientName);
        return $client->getPrompt($promptName, $args);
    }

    /**
     * List available tools from a specific client.
     */
    public function listTools(?string $clientName = null): array
    {
        $client = $this->get($clientName);
        return $client->listTools();
    }

    /**
     * List available resources from a specific client.
     */
    public function listResources(?string $clientName = null): array
    {
        $client = $this->get($clientName);
        return $client->listResources();
    }

    /**
     * List available prompts from a specific client.
     */
    public function listPrompts(?string $clientName = null): array
    {
        $client = $this->get($clientName);
        return $client->listPrompts();
    }

    /**
     * Get roots from a specific client.
     */
    public function getRoots(?string $clientName = null): array
    {
        $client = $this->get($clientName);
        return $client->getRoots();
    }

    /**
     * List root contents from a specific client.
     */
    public function listRootContents(string $clientName, string $uri): array
    {
        $client = $this->get($clientName);
        return $client->listRootContents($uri);
    }

    /**
     * Create sampling request on a specific client.
     */
    public function createSampling(string $clientName, array $request): mixed
    {
        $client = $this->get($clientName);
        return $client->createSampling($request);
    }

    /**
     * Send elicitation request on a specific client.
     */
    public function sendElicitation(string $clientName, array $request): mixed
    {
        $client = $this->get($clientName);
        return $client->sendElicitation($request);
    }

    /**
     * Get capabilities from a specific client.
     */
    public function getCapabilities(?string $clientName = null): array
    {
        $client = $this->get($clientName);
        return $client->getCapabilities();
    }

    /**
     * Ping a specific client.
     */
    public function ping(?string $clientName = null): void
    {
        $client = $this->get($clientName);
        $client->ping();
    }

    /**
     * Complete text using a specific client.
     */
    public function completeText(string $clientName, string $text, array $options = []): mixed
    {
        $client = $this->get($clientName);
        return $client->completeText($text, $options);
    }

    /**
     * Cancel an operation on a specific client.
     */
    public function cancelOperation(string $clientName, string $operationId): void
    {
        $client = $this->get($clientName);
        $client->cancelOperation($operationId);
    }

    /**
     * Get progress of an operation on a specific client.
     */
    public function getProgress(string $clientName, string $operationId): array
    {
        $client = $this->get($clientName);
        return $client->getProgress($operationId);
    }

    /**
     * Create a client instance.
     */
    protected function createClient(string $name): LaravelMcpClient
    {
        $config = config("mcp.clients.{$name}");

        if (!$config) {
            throw new McpException("Client configuration not found for: {$name}");
        }

        return new LaravelMcpClient($this->app, $name, $config);
    }

    /**
     * Get all connected clients.
     */
    public function getConnectedClients(): array
    {
        return $this->connectedClients;
    }

    /**
     * Disconnect all clients.
     */
    public function disconnectAll(): void
    {
        foreach (array_keys($this->connectedClients) as $clientName) {
            $this->disconnect($clientName);
        }
    }

    /**
     * Reconnect a client.
     */
    public function reconnect(?string $name = null): void
    {
        $clientName = $name ?? config('mcp.default_client', 'main');

        if (isset($this->connectedClients[$clientName])) {
            $connectionInfo = $this->connectedClients[$clientName];
            $this->disconnect($clientName);
            $this->connect(
                $clientName,
                $connectionInfo['server_url'],
                $connectionInfo['transport'],
                $connectionInfo['options']
            );
        }
    }

    /**
     * Test connection to a server.
     */
    public function testConnection(string $serverUrl, ?string $transport = null): array
    {
        $testClient = new LaravelMcpClient($this->app, 'test-' . uniqid(), [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'timeout' => 5000,
        ]);

        try {
            $testClient->connect($serverUrl, $transport);

            $result = [
                'success' => true,
                'server_url' => $serverUrl,
                'transport' => $testClient->getTransport(),
                'capabilities' => $testClient->getServerCapabilities(),
                'response_time' => $testClient->getLastResponseTime(),
            ];

            $testClient->disconnect();
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'server_url' => $serverUrl,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
}
