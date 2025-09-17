<?php

namespace MCP\Laravel\Laravel;

use Illuminate\Contracts\Container\Container;
use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Laravel\Exceptions\McpException;
use MCP\Laravel\Exceptions\ClientNotConnectedException;

/**
 * Laravel wrapper for MCP Client with full specification support.
 * 
 * Provides a Laravel-friendly interface to the underlying PHP MCP SDK
 * client implementation while maintaining access to all advanced features.
 */
class LaravelMcpClient
{
    protected Container $app;
    protected string $name;
    protected array $config;
    protected ?Client $client = null;
    protected bool $connected = false;
    protected ?string $transport = null;
    protected ?string $serverUrl = null;
    protected ?\DateTime $connectedAt = null;
    protected array $serverCapabilities = [];
    protected int $requestCount = 0;
    protected int $errorCount = 0;
    protected ?\DateTime $lastPing = null;
    protected ?float $lastResponseTime = null;

    public function __construct(Container $app, string $name, array $config)
    {
        $this->app = $app;
        $this->name = $name;
        $this->config = $config;

        $this->initializeClient();
    }

    /**
     * Prepare a capability value for the MCP client.
     * Converts empty arrays/objects to null to avoid sending them to the server.
     */
    protected function prepareCapability($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value) && empty($value)) {
            return null; // Don't send empty capabilities
        }

        return is_array($value) ? $value : null;
    }

    /**
     * Initialize the MCP client instance.
     */
    protected function initializeClient(): void
    {
        $implementation = new Implementation(
            $this->config['name'] ?? $this->name,
            $this->config['version'] ?? '1.0.0'
        );

        $capabilities = new ClientCapabilities(
            experimental: $this->prepareCapability($this->config['capabilities']['experimental'] ?? null),
            sampling: $this->prepareCapability($this->config['capabilities']['sampling'] ?? null),
            roots: $this->prepareCapability($this->config['capabilities']['roots'] ?? ['listChanged' => true])
        );

        $options = new ClientOptions(
            capabilities: $capabilities
        );

        $this->client = new Client($implementation, $options);
    }

    /**
     * Get the underlying MCP client instance.
     */
    public function getClient(): Client
    {
        if (!$this->client) {
            $this->initializeClient();
        }

        return $this->client;
    }

    /**
     * Connect to an MCP server.
     */
    public function connect(string $serverUrl, ?string $transport = null, array $options = []): void
    {
        $this->serverUrl = $serverUrl;
        $this->transport = $transport ?? $this->detectTransport($serverUrl);

        $startTime = microtime(true);

        try {
            // Initialize transport based on type and connect
            switch ($this->transport) {
                case 'stdio':
                    $this->connectStdio($serverUrl, $options);
                    break;
                case 'http':
                    $this->connectHttp($serverUrl, $options);
                    break;
                case 'websocket':
                    $this->connectWebSocket($serverUrl, $options);
                    break;
                default:
                    throw new McpException("Unsupported transport: {$this->transport}");
            }

            $this->connected = true;
            $this->connectedAt = new \DateTime();
            $this->lastResponseTime = microtime(true) - $startTime;

            // Get server capabilities after connection
            $this->fetchServerCapabilities();
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to connect to MCP server: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Disconnect from the MCP server.
     */
    public function disconnect(): void
    {
        if ($this->client && $this->connected) {
            // Graceful disconnect logic here
            $this->connected = false;
            $this->connectedAt = null;
            $this->serverCapabilities = [];
        }
    }

    /**
     * Check if the client is connected.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the current transport.
     */
    public function getTransport(): ?string
    {
        return $this->transport;
    }

    /**
     * Get connection information.
     */
    public function getConnectionInfo(): array
    {
        return [
            'server_url' => $this->serverUrl,
            'transport' => $this->transport,
            'connected_at' => $this->connectedAt?->format('c'),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get uptime in seconds.
     */
    public function getUptime(): ?int
    {
        if (!$this->connectedAt) {
            return null;
        }

        return (new \DateTime())->getTimestamp() - $this->connectedAt->getTimestamp();
    }

    /**
     * Call a tool on the server.
     */
    public function callTool(string $toolName, array $params = []): mixed
    {
        $this->ensureConnected();

        $startTime = microtime(true);

        try {
            $request = new \MCP\Types\Requests\CallToolRequest([
                'name' => $toolName,
                'arguments' => $params
            ]);
            $future = $this->client->callTool($request);
            $result = $future->await();
            $this->requestCount++;
            $this->lastResponseTime = microtime(true) - $startTime;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to call tool {$toolName}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Read a resource from the server.
     */
    public function readResource(string $uri): mixed
    {
        $this->ensureConnected();

        $startTime = microtime(true);

        try {
            $request = new \MCP\Types\Requests\ReadResourceRequest($uri);
            $future = $this->client->readResource($request);
            $result = $future->await();
            $this->requestCount++;
            $this->lastResponseTime = microtime(true) - $startTime;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to read resource {$uri}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a prompt from the server.
     */
    public function getPrompt(string $promptName, array $args = []): mixed
    {
        $this->ensureConnected();

        $startTime = microtime(true);

        try {
            $request = new \MCP\Types\Requests\GetPromptRequest($promptName, $args);
            $future = $this->client->getPrompt($request);
            $result = $future->await();
            $this->requestCount++;
            $this->lastResponseTime = microtime(true) - $startTime;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to get prompt {$promptName}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * List available tools.
     */
    public function listTools(): array
    {
        $this->ensureConnected();

        try {
            $request = new \MCP\Types\Requests\ListToolsRequest();
            $future = $this->client->listTools($request);
            $result = $future->await();
            $this->requestCount++;
            return $result->getTools();
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to list tools: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * List available resources.
     */
    public function listResources(): array
    {
        $this->ensureConnected();

        try {
            $request = new \MCP\Types\Requests\ListResourcesRequest();
            $future = $this->client->listResources($request);
            $result = $future->await();
            $this->requestCount++;
            return $result->getResources();
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to list resources: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * List available prompts.
     */
    public function listPrompts(): array
    {
        $this->ensureConnected();

        try {
            $request = new \MCP\Types\Requests\ListPromptsRequest();
            $future = $this->client->listPrompts($request);
            $result = $future->await();
            $this->requestCount++;
            return $result->getPrompts();
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to list prompts: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get roots (for hierarchical resource navigation).
     */
    public function getRoots(): array
    {
        $this->ensureConnected();

        try {
            // Roots are managed by the roots manager
            $rootsManager = app(\MCP\Laravel\Features\RootsManager::class);
            $result = $rootsManager->getRoots();
            $this->requestCount++;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to get roots: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * List contents of a root.
     */
    public function listRootContents(string $uri): array
    {
        $this->ensureConnected();

        try {
            // Use the appropriate MCP client method for listing root contents
            $result = $this->client->listResources();
            $this->requestCount++;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to list root contents for {$uri}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create sampling request.
     */
    public function createSampling(array $request): mixed
    {
        $this->ensureConnected();

        try {
            // Sampling is managed by the sampling manager
            $samplingManager = app(\MCP\Laravel\Features\SamplingManager::class);
            $result = $samplingManager->createSamplingRequest($request);
            $this->requestCount++;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to create sampling: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send elicitation request.
     */
    public function sendElicitation(array $request): mixed
    {
        $this->ensureConnected();

        try {
            // Elicitation is managed by the elicitation manager
            $elicitationManager = app(\MCP\Laravel\Features\ElicitationManager::class);
            $id = $elicitationManager->createElicitation($request['prompt'] ?? '', $request);
            $this->requestCount++;
            return ['elicitation_id' => $id];
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to send elicitation: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get client capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->config['capabilities'] ?? [];
    }

    /**
     * Get server capabilities.
     */
    public function getServerCapabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Ping the server.
     */
    public function ping(): void
    {
        $this->ensureConnected();

        try {
            $future = $this->client->ping();
            $future->await();
            $this->lastPing = new \DateTime();
            $this->requestCount++;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to ping server: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Complete text using the server.
     */
    public function completeText(string $text, array $options = []): mixed
    {
        $this->ensureConnected();

        try {
            $request = new \MCP\Types\Requests\CompleteRequest(
                ref: ['type' => 'ref', 'name' => 'completion'],
                argument: ['name' => 'text', 'value' => $text]
            );
            $future = $this->client->complete($request);
            $result = $future->await();
            $this->requestCount++;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to complete text: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cancel an operation.
     */
    public function cancelOperation(string $operationId): void
    {
        $this->ensureConnected();

        try {
            // Cancellation is managed by the cancellation manager
            $cancellationManager = app(\MCP\Laravel\Utilities\CancellationManager::class);
            $cancellationManager->cancelByOperationId($operationId, 'Cancelled by user');
            $this->requestCount++;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to cancel operation {$operationId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get progress of an operation.
     */
    public function getProgress(string $operationId): array
    {
        $this->ensureConnected();

        try {
            // Progress is managed by the progress manager
            $progressManager = app(\MCP\Laravel\Utilities\ProgressManager::class);
            $token = new \MCP\Types\ProgressToken($operationId);
            $result = $progressManager->getProgress($token) ?? [];
            $this->requestCount++;
            return $result;
        } catch (\Exception $e) {
            $this->errorCount++;
            throw new McpException("Failed to get progress for {$operationId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get client status.
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->name,
            'connected' => $this->connected,
            'server_url' => $this->serverUrl,
            'transport' => $this->transport,
            'connected_at' => $this->connectedAt?->format('c'),
            'uptime' => $this->getUptime(),
            'request_count' => $this->requestCount,
            'error_count' => $this->errorCount,
            'last_ping' => $this->lastPing?->format('c'),
            'last_response_time' => $this->lastResponseTime,
            'capabilities' => $this->getCapabilities(),
            'server_capabilities' => $this->serverCapabilities,
        ];
    }

    /**
     * Get last ping time.
     */
    public function getLastPing(): ?\DateTime
    {
        return $this->lastPing;
    }

    /**
     * Get request count.
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * Get error count.
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Get last response time.
     */
    public function getLastResponseTime(): ?float
    {
        return $this->lastResponseTime;
    }

    /**
     * Ensure the client is connected.
     */
    protected function ensureConnected(): void
    {
        if (!$this->connected) {
            throw new ClientNotConnectedException("Client {$this->name} is not connected to any server");
        }
    }

    /**
     * Detect transport type from server URL.
     */
    protected function detectTransport(string $serverUrl): string
    {
        if (str_starts_with($serverUrl, 'http://') || str_starts_with($serverUrl, 'https://')) {
            return 'http';
        }

        if (str_starts_with($serverUrl, 'ws://') || str_starts_with($serverUrl, 'wss://')) {
            return 'websocket';
        }

        return 'stdio';
    }

    /**
     * Connect using STDIO transport.
     */
    protected function connectStdio(string $serverUrl, array $options): void
    {
        // For STDIO, serverUrl should be a command to execute
        $serverParams = new \MCP\Client\Transport\StdioServerParameters(
            command: $serverUrl,
            args: $options['args'] ?? [],
            env: $options['env'] ?? null
        );

        $transport = new \MCP\Client\Transport\StdioClientTransport($serverParams);

        // Connect the client to the transport
        \Amp\async(function () use ($transport) {
            $this->client->connect($transport);
        });
    }

    /**
     * Connect using HTTP transport.
     */
    protected function connectHttp(string $serverUrl, array $options): void
    {
        $transportOptions = new \MCP\Client\Transport\StreamableHttpClientTransportOptions(
            headers: $options['headers'] ?? []
        );

        $transport = new \MCP\Client\Transport\StreamableHttpClientTransport($serverUrl, $transportOptions);

        // Connect the client to the transport with await
        \Amp\async(function () use ($transport) {
            $this->client->connect($transport);
        })->await();
    }

    /**
     * Connect using WebSocket transport.
     */
    protected function connectWebSocket(string $serverUrl, array $options): void
    {
        $transportOptions = new \MCP\Client\Transport\WebSocketClientTransportOptions(
            headers: $options['headers'] ?? []
        );

        $transport = new \MCP\Client\Transport\WebSocketClientTransport($serverUrl, $transportOptions);

        // Connect the client to the transport with await
        \Amp\async(function () use ($transport) {
            $this->client->connect($transport);
        })->await();
    }

    /**
     * Fetch server capabilities after connection.
     */
    protected function fetchServerCapabilities(): void
    {
        try {
            // Fetch the server's capabilities during the initialization handshake
            $serverCapabilities = $this->client->getServerCapabilities();
            $this->serverCapabilities = $serverCapabilities ? $serverCapabilities->jsonSerialize() : [];
        } catch (\Exception $e) {
            // Log warning but don't fail the connection
            logger()->warning("Failed to fetch server capabilities: " . $e->getMessage());
            $this->serverCapabilities = [];
        }
    }
}
