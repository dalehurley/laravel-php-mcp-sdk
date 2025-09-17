<?php

namespace MCP\Laravel\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use MCP\Server\McpServer;
use MCP\Types\Implementation;
use MCP\Server\ServerOptions;
use MCP\Types\Capabilities\ServerCapabilities;
use MCP\Laravel\Exceptions\McpException;
use MCP\Laravel\Contracts\McpToolInterface;
use MCP\Laravel\Contracts\McpResourceInterface;
use MCP\Laravel\Contracts\McpPromptInterface;

// HTTP Server imports for transport
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket;
use Psr\Log\NullLogger;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\DefaultErrorHandler;
use MCP\Server\Transport\StreamableHttpServerTransport;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

/**
 * Laravel wrapper for MCP Server with full specification support.
 * 
 * Provides a Laravel-friendly interface to the underlying PHP MCP SDK
 * server implementation while maintaining access to all advanced features.
 */
class LaravelMcpServer
{
    protected Container $app;
    protected string $name;
    protected array $config;
    protected ?McpServer $server = null;
    protected Collection $registeredTools;
    protected Collection $registeredResources;
    protected Collection $registeredPrompts;
    protected bool $running = false;
    protected ?string $transport = null;
    protected ?int $processId = null;
    protected ?\DateTime $startedAt = null;
    protected ?\Amp\Http\Server\SocketHttpServer $httpServer = null;

    public function __construct(Container $app, string $name, array $config)
    {
        $this->app = $app;
        $this->name = $name;
        $this->config = $config;
        $this->registeredTools = new Collection();
        $this->registeredResources = new Collection();
        $this->registeredPrompts = new Collection();

        $this->initializeServer();
    }

    /**
     * Initialize the MCP server instance.
     */
    protected function initializeServer(): void
    {
        $implementation = new Implementation(
            $this->config['name'] ?? $this->name,
            $this->config['version'] ?? '1.0.0'
        );

        $capabilities = new ServerCapabilities(
            experimental: $this->config['capabilities']['experimental'] ?? [],
            logging: $this->config['capabilities']['logging'] ?? [],
            prompts: !empty($this->config['prompts']) ? ['listChanged' => true] : null,
            resources: !empty($this->config['resources']) ? ['subscribe' => true, 'listChanged' => true] : null,
            tools: !empty($this->config['tools']) ? [] : null
        );

        $options = new ServerOptions(
            capabilities: $capabilities
        );

        $this->server = new McpServer($implementation, $options);
        $this->discoverComponents();
    }

    /**
     * Get the underlying MCP server instance.
     */
    public function getServer(): McpServer
    {
        if (!$this->server) {
            $this->initializeServer();
        }

        return $this->server;
    }

    /**
     * Get the server name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Start the server with the specified transport.
     */
    public function start(?string $transport = null): void
    {
        $this->transport = $transport ?? $this->config['transport'] ?? 'stdio';

        // Initialize transport based on type
        switch ($this->transport) {
            case 'stdio':
                $this->startStdioTransport();
                break;
            case 'http':
                $this->startHttpTransport();
                break;
            case 'websocket':
                $this->startWebSocketTransport();
                break;
            default:
                throw new McpException("Unsupported transport: {$this->transport}");
        }

        $this->running = true;
        $this->startedAt = new \DateTime();
        $this->processId = getmypid();
    }

    /**
     * Stop the server.
     */
    public function stop(): void
    {
        if ($this->server) {
            // Graceful shutdown logic here
            $this->running = false;
            $this->startedAt = null;
            $this->processId = null;

            // Stop HTTP server if running
            if ($this->httpServer) {
                $this->httpServer->stop();
                $this->httpServer = null;
            }
        }
    }

    /**
     * Check if the server is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get the current transport.
     */
    public function getTransport(): ?string
    {
        return $this->transport;
    }

    /**
     * Get the process ID.
     */
    public function getProcessId(): ?int
    {
        return $this->processId;
    }

    /**
     * Get server uptime in seconds.
     */
    public function getUptime(): ?int
    {
        if (!$this->startedAt) {
            return null;
        }

        return (new \DateTime())->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Get memory usage in bytes.
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Add a tool to the server.
     */
    public function addTool(string $name, callable $handler, array $schema = []): void
    {
        $toolSchema = array_merge([
            'name' => $name,
            'description' => "Tool: {$name}",
            'inputSchema' => ['type' => 'object'],
        ], $schema);

        $this->server->registerTool($name, $toolSchema, $handler);

        $this->registeredTools->put($name, [
            'name' => $name,
            'schema' => $toolSchema,
            'handler' => $handler,
            'registered_at' => now(),
        ]);
    }

    /**
     * Add a resource to the server.
     */
    public function addResource(string $uri, callable $handler, array $metadata = []): void
    {
        $resourceMetadata = array_merge([
            'uri' => $uri,
            'name' => basename($uri),
            'description' => "Resource: {$uri}",
        ], $metadata);

        $this->server->registerResource(basename($uri), $uri, $resourceMetadata, $handler);

        $this->registeredResources->put($uri, [
            'uri' => $uri,
            'metadata' => $resourceMetadata,
            'handler' => $handler,
            'registered_at' => now(),
        ]);
    }

    /**
     * Add a prompt to the server.
     */
    public function addPrompt(string $name, callable $handler, array $schema = []): void
    {
        $promptSchema = array_merge([
            'name' => $name,
            'description' => "Prompt: {$name}",
        ], $schema);

        $this->server->registerPrompt($name, $promptSchema, $handler);

        $this->registeredPrompts->put($name, [
            'name' => $name,
            'schema' => $promptSchema,
            'handler' => $handler,
            'registered_at' => now(),
        ]);
    }

    /**
     * Remove a tool from the server.
     */
    public function removeTool(string $name): void
    {
        // Remove from our local registry
        $this->registeredTools->forget($name);

        // Note: PHP MCP SDK doesn't currently support removing registered tools
        // This would require server restart to take effect
        if (function_exists('logger')) {
            logger()->info("Tool '{$name}' removed from local registry. Server restart required for full removal.");
        }
    }

    /**
     * Remove a resource from the server.
     */
    public function removeResource(string $uri): void
    {
        // Remove from our local registry
        $this->registeredResources->forget($uri);

        // Note: PHP MCP SDK doesn't currently support removing registered resources
        // This would require server restart to take effect
        if (function_exists('logger')) {
            logger()->info("Resource '{$uri}' removed from local registry. Server restart required for full removal.");
        }
    }

    /**
     * Remove a prompt from the server.
     */
    public function removePrompt(string $name): void
    {
        // Remove from our local registry
        $this->registeredPrompts->forget($name);

        // Note: PHP MCP SDK doesn't currently support removing registered prompts
        // This would require server restart to take effect
        if (function_exists('logger')) {
            logger()->info("Prompt '{$name}' removed from local registry. Server restart required for full removal.");
        }
    }

    /**
     * Get all registered tools.
     */
    public function getTools(): array
    {
        return $this->registeredTools->toArray();
    }

    /**
     * Get all registered resources.
     */
    public function getResources(): array
    {
        return $this->registeredResources->toArray();
    }

    /**
     * Get all registered prompts.
     */
    public function getPrompts(): array
    {
        return $this->registeredPrompts->toArray();
    }

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->config['capabilities'] ?? [];
    }

    /**
     * Set server capabilities.
     */
    public function setCapabilities(array $capabilities): void
    {
        $this->config['capabilities'] = $capabilities;
        // Reinitialize server with new capabilities
        $this->initializeServer();
    }

    /**
     * Get server status.
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->name,
            'running' => $this->running,
            'transport' => $this->transport,
            'process_id' => $this->processId,
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'tools_count' => $this->registeredTools->count(),
            'resources_count' => $this->registeredResources->count(),
            'prompts_count' => $this->registeredPrompts->count(),
            'capabilities' => $this->getCapabilities(),
            'started_at' => $this->startedAt?->format('c'),
        ];
    }

    /**
     * Discover and register components automatically.
     */
    public function discover(array $directories = []): void
    {
        $discoverDirs = $directories ?: ($this->config['tools']['discover'] ?? []);

        foreach ($discoverDirs as $directory) {
            if (is_dir($directory)) {
                $this->discoverInDirectory($directory);
            }
        }
    }

    /**
     * Register multiple components at once.
     */
    public function registerBatch(array $components): void
    {
        foreach ($components as $type => $items) {
            switch ($type) {
                case 'tools':
                    foreach ($items as $name => $config) {
                        $this->addTool($name, $config['handler'], $config['schema'] ?? []);
                    }
                    break;
                case 'resources':
                    foreach ($items as $uri => $config) {
                        $this->addResource($uri, $config['handler'], $config['metadata'] ?? []);
                    }
                    break;
                case 'prompts':
                    foreach ($items as $name => $config) {
                        $this->addPrompt($name, $config['handler'], $config['schema'] ?? []);
                    }
                    break;
            }
        }
    }

    /**
     * Discover components in a directory.
     */
    protected function discoverInDirectory(string $directory): void
    {
        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $this->registerComponent($className);
            }
        }
    }

    /**
     * Register a component class.
     */
    protected function registerComponent(string $className): void
    {
        $instance = $this->app->make($className);

        if ($instance instanceof McpToolInterface) {
            $this->addTool(
                $instance->name(),
                [$instance, 'handle'],
                ['description' => $instance->description(), 'inputSchema' => $instance->inputSchema()]
            );
        } elseif ($instance instanceof McpResourceInterface) {
            $this->addResource(
                $instance->uri(),
                [$instance, 'read'],
                ['description' => $instance->description()]
            );
        } elseif ($instance instanceof McpPromptInterface) {
            $this->addPrompt(
                $instance->name(),
                [$instance, 'handle'],
                ['description' => $instance->description(), 'arguments' => $instance->arguments()]
            );
        }
    }

    /**
     * Extract class name from file.
     */
    protected function getClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        // Simple regex to extract namespace and class name
        if (
            preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches) &&
            preg_match('/class\s+(\w+)/', $content, $classMatches)
        ) {
            return $namespaceMatches[1] . '\\' . $classMatches[1];
        }

        return null;
    }

    /**
     * Start STDIO transport.
     */
    protected function startStdioTransport(): void
    {
        $transport = new \MCP\Server\Transport\StdioServerTransport();

        // Connect the server to the transport and await to keep it running
        \Amp\async(function () use ($transport) {
            $this->server->connect($transport)->await();
        })->await();
    }

    /**
     * Start HTTP transport.
     */
    protected function startHttpTransport(): void
    {
        $host = config('mcp.transports.http.host', '127.0.0.1');
        $port = config('mcp.transports.http.port', 3000);

        // Create transport options (without host/port which aren't supported)
        $options = new \MCP\Server\Transport\StreamableHttpServerTransportOptions(
            enableJsonResponse: true,
            allowedHosts: null,
            allowedOrigins: null,
            enableDnsRebindingProtection: false
        );

        $transport = new \MCP\Server\Transport\StreamableHttpServerTransport($options);

        // Create request handler wrapper
        $requestHandler = new class($transport) implements RequestHandler {
            private $transport;

            public function __construct(\MCP\Server\Transport\StreamableHttpServerTransport $transport)
            {
                $this->transport = $transport;
            }

            public function handleRequest(\Amp\Http\Server\Request $request): \Amp\Http\Server\Response
            {
                return $this->transport->handleRequest($request)->await();
            }
        };

        $errorHandler = new DefaultErrorHandler();

        $httpServer = SocketHttpServer::createForDirectAccess(new NullLogger());
        $httpServer->expose("{$host}:{$port}");

        // Log that we're about to start
        if ($this->app->bound('log')) {
            $this->app['log']->info("Starting HTTP server on {$host}:{$port}");
        }

        // Connect the MCP server to the transport first
        $this->server->connect($transport)->await();

        // Store the HTTP server instance for later management
        $this->httpServer = $httpServer;

        // Start the HTTP server - this will block and run the event loop
        $httpServer->start($requestHandler, $errorHandler);
    }

    /**
     * Start WebSocket transport.
     */
    protected function startWebSocketTransport(): void
    {
        $host = config('mcp.transports.websocket.host', '127.0.0.1');
        $port = config('mcp.transports.websocket.port', 3001);
        $maxConnections = config('mcp.transports.websocket.max_connections', 1000);

        $options = new \MCP\Server\Transport\WebSocketServerTransportOptions(
            host: $host,
            port: $port,
            maxConnections: $maxConnections
        );

        $transport = new \MCP\Server\Transport\WebSocketServerTransport($options);

        // Connect the server to the transport
        \Amp\async(function () use ($transport) {
            $this->server->connect($transport);
        });
    }

    /**
     * Discover components based on configuration.
     */
    protected function discoverComponents(): void
    {
        if ($this->config['tools']['auto_register'] ?? false) {
            $this->discover($this->config['tools']['discover'] ?? []);
        }

        if ($this->config['resources']['auto_register'] ?? false) {
            $resourceDirs = $this->config['resources']['discover'] ?? [];
            foreach ($resourceDirs as $directory) {
                if (is_dir($directory)) {
                    $this->discoverResourcesInDirectory($directory);
                }
            }
        }

        if ($this->config['prompts']['auto_register'] ?? false) {
            $promptDirs = $this->config['prompts']['discover'] ?? [];
            foreach ($promptDirs as $directory) {
                if (is_dir($directory)) {
                    $this->discoverPromptsInDirectory($directory);
                }
            }
        }
    }

    /**
     * Discover resources in a directory.
     */
    protected function discoverResourcesInDirectory(string $directory): void
    {
        $this->discoverInDirectory($directory);
    }

    /**
     * Discover prompts in a directory.
     */
    protected function discoverPromptsInDirectory(string $directory): void
    {
        $this->discoverInDirectory($directory);
    }
}
