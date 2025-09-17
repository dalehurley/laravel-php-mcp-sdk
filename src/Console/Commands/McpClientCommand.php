<?php

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use MCP\Laravel\Laravel\ClientManager;
use MCP\Laravel\Exceptions\McpException;

/**
 * Artisan command for managing MCP clients.
 */
class McpClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:client
                            {action : The action to perform (connect, disconnect, call-tool, read-resource, get-prompt, list-tools, list-resources, list-prompts, ping, status)}
                            {client? : The client name (optional, uses default if not specified)}
                            {server-url? : The server URL to connect to}
                            {--transport= : The transport to use (auto, stdio, http, websocket)}
                            {--headers= : JSON headers for connection (e.g., {"Authorization":"Bearer ..."})}
                            {--tool= : The tool name to call}
                            {--resource= : The resource URI to read}
                            {--prompt= : The prompt name to get}
                            {--params= : JSON parameters for tool/prompt calls}
                            {--args= : JSON arguments for prompt calls}
                            {--timeout= : Connection timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage MCP clients (connect, disconnect, call tools, read resources, etc.)';

    protected ClientManager $clientManager;

    public function __construct(ClientManager $clientManager)
    {
        parent::__construct();
        $this->clientManager = $clientManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $clientName = $this->argument('client');

        try {
            return match ($action) {
                'connect' => $this->connectClient($clientName),
                'disconnect' => $this->disconnectClient($clientName),
                'call-tool' => $this->callTool($clientName),
                'read-resource' => $this->readResource($clientName),
                'get-prompt' => $this->getPrompt($clientName),
                'list-tools' => $this->listTools($clientName),
                'list-resources' => $this->listResources($clientName),
                'list-prompts' => $this->listPrompts($clientName),
                'ping' => $this->pingServer($clientName),
                'status' => $this->showStatus($clientName),
                default => $this->error("Unknown action: {$action}") ?: 1,
            };
        } catch (McpException $e) {
            $this->error("MCP Error: {$e->getMessage()}");
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Connect a client to a server.
     */
    protected function connectClient(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');
        $serverUrl = $this->argument('server-url');

        if (!$serverUrl) {
            $this->error("Server URL is required for connect action.");
            return 1;
        }

        if (!$this->clientManager->exists($clientName)) {
            $this->error("Client '{$clientName}' is not configured.");
            return 1;
        }

        if ($this->clientManager->isConnected($clientName)) {
            $this->warn("Client '{$clientName}' is already connected.");
            return 0;
        }

        $transport = $this->option('transport');
        $timeout = $this->option('timeout');
        $headersJson = $this->option('headers');

        $options = [];
        if ($timeout) {
            $options['timeout'] = (int) $timeout;
        }
        if ($headersJson) {
            try {
                $options['headers'] = json_decode($headersJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error("Invalid JSON in --headers: {$e->getMessage()}");
                return 1;
            }
        }

        $this->info("Connecting MCP client '{$clientName}' to {$serverUrl}...");

        if ($this->getOutput()->isVerbose()) {
            $config = config("mcp.clients.{$clientName}");
            $this->line("Client Configuration:");
            $this->line("  Name: {$config['name']}");
            $this->line("  Version: {$config['version']}");
            $this->line("  Transport: " . ($transport ?? 'auto'));
        }

        $this->clientManager->connect($clientName, $serverUrl, $transport, $options);

        $status = $this->clientManager->getStatus($clientName);

        $this->info("✅ Client '{$clientName}' connected successfully!");
        $this->displayClientInfo($status);

        return 0;
    }

    /**
     * Disconnect a client.
     */
    protected function disconnectClient(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');

        if (!$this->clientManager->exists($clientName)) {
            $this->error("Client '{$clientName}' is not configured.");
            return 1;
        }

        if (!$this->clientManager->isConnected($clientName)) {
            $this->warn("Client '{$clientName}' is not connected.");
            return 0;
        }

        $this->info("Disconnecting MCP client '{$clientName}'...");

        $this->clientManager->disconnect($clientName);

        $this->info("✅ Client '{$clientName}' disconnected successfully!");

        return 0;
    }

    /**
     * Call a tool on the server.
     */
    protected function callTool(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');
        $toolName = $this->option('tool');

        if (!$toolName) {
            $this->error("Tool name is required. Use --tool option.");
            return 1;
        }

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $paramsJson = $this->option('params') ?? '{}';

        try {
            $params = json_decode($paramsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error("Invalid JSON in --params: {$e->getMessage()}");
            return 1;
        }

        $this->info("Calling tool '{$toolName}' on server...");

        if ($this->getOutput()->isVerbose()) {
            $this->line("Parameters: " . json_encode($params, JSON_PRETTY_PRINT));
        }

        $result = $this->clientManager->callTool($clientName, $toolName, $params);

        $this->info("✅ Tool call completed!");
        $this->displayResult($result);
        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * Read a resource from the server.
     */
    protected function readResource(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');
        $resourceUri = $this->option('resource');

        if (!$resourceUri) {
            $this->error("Resource URI is required. Use --resource option.");
            return 1;
        }

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $this->info("Reading resource '{$resourceUri}' from server...");

        $result = $this->clientManager->readResource($clientName, $resourceUri);

        $this->info("✅ Resource read completed!");
        $this->displayResult($result);
        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * Get a prompt from the server.
     */
    protected function getPrompt(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');
        $promptName = $this->option('prompt');

        if (!$promptName) {
            $this->error("Prompt name is required. Use --prompt option.");
            return 1;
        }

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $argsJson = $this->option('args') ?? '{}';

        try {
            $args = json_decode($argsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error("Invalid JSON in --args: {$e->getMessage()}");
            return 1;
        }

        $this->info("Getting prompt '{$promptName}' from server...");

        if ($this->getOutput()->isVerbose()) {
            $this->line("Arguments: " . json_encode($args, JSON_PRETTY_PRINT));
        }

        $result = $this->clientManager->getPrompt($clientName, $promptName, $args);

        $this->info("✅ Prompt retrieved successfully!");
        $this->displayResult($result);
        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * List available tools.
     */
    protected function listTools(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $tools = $this->clientManager->listTools($clientName);

        $this->info("Available Tools:");
        $this->line("");

        if (empty($tools['tools'] ?? [])) {
            $this->warn("No tools available.");
            return 0;
        }

        foreach ($tools['tools'] as $tool) {
            $this->line("🔧 <info>{$tool['name']}</info>");
            $this->line("   Description: {$tool['description']}");

            if ($this->getOutput()->isVerbose() && !empty($tool['inputSchema'])) {
                $this->line("   Input Schema: " . json_encode($tool['inputSchema'], JSON_PRETTY_PRINT));
            }

            $this->line("");
        }

        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * List available resources.
     */
    protected function listResources(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $resources = $this->clientManager->listResources($clientName);

        $this->info("Available Resources:");
        $this->line("");

        if (empty($resources['resources'] ?? [])) {
            $this->warn("No resources available.");
            return 0;
        }

        foreach ($resources['resources'] as $resource) {
            $this->line("📄 <info>{$resource['uri']}</info>");
            $this->line("   Name: {$resource['name']}");
            $this->line("   Description: {$resource['description']}");

            if (isset($resource['mimeType'])) {
                $this->line("   MIME Type: {$resource['mimeType']}");
            }

            $this->line("");
        }

        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * List available prompts.
     */
    protected function listPrompts(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $prompts = $this->clientManager->listPrompts($clientName);

        $this->info("Available Prompts:");
        $this->line("");

        if (empty($prompts['prompts'] ?? [])) {
            $this->warn("No prompts available.");
            return 0;
        }

        foreach ($prompts['prompts'] as $prompt) {
            $this->line("💬 <info>{$prompt['name']}</info>");
            $this->line("   Description: {$prompt['description']}");

            if ($this->getOutput()->isVerbose() && !empty($prompt['arguments'])) {
                $this->line("   Arguments: " . json_encode($prompt['arguments'], JSON_PRETTY_PRINT));
            }

            $this->line("");
        }

        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * Ping the server.
     */
    protected function pingServer(?string $clientName): int
    {
        $clientName = $clientName ?? config('mcp.default_client', 'main');

        try {
            $ctx = $this->ensureConnected($clientName);
        } catch (\RuntimeException $e) {
            return 1;
        }

        $this->info("Pinging server...");

        $startTime = microtime(true);
        $this->clientManager->ping($clientName);
        $responseTime = (microtime(true) - $startTime) * 1000;

        $this->info("✅ Ping successful! Response time: " . round($responseTime, 2) . "ms");
        if ($ctx['ephemeral']) {
            $this->clientManager->disconnect($clientName);
        }
        return 0;
    }

    /**
     * Show client status.
     */
    protected function showStatus(?string $clientName): int
    {
        if ($clientName) {
            return $this->showSingleClientStatus($clientName);
        }

        return $this->showAllClientsStatus();
    }

    /**
     * Show status for a single client.
     */
    protected function showSingleClientStatus(string $clientName): int
    {
        if (!$this->clientManager->exists($clientName)) {
            $this->error("Client '{$clientName}' is not configured.");
            return 1;
        }

        $status = $this->clientManager->getStatus($clientName);

        $this->info("Client Status: {$clientName}");
        $this->displayClientInfo($status);

        return 0;
    }

    /**
     * Show status for all clients.
     */
    protected function showAllClientsStatus(): int
    {
        $clients = $this->clientManager->list();

        if (empty($clients)) {
            $this->warn("No clients configured.");
            return 0;
        }

        $this->info("MCP Clients Status:");
        $this->line("");

        $headers = ['Name', 'Status', 'Server', 'Transport', 'Connected', 'Requests', 'Errors'];
        $rows = [];

        foreach ($clients as $name => $client) {
            $status = $client['connected'] ? '🟢 Connected' : '🔴 Disconnected';
            $connected = $client['connected_at'] ? $client['connected_at']->diffForHumans() : 'N/A';

            $details = $this->clientManager->getStatus($name);

            $rows[] = [
                $name,
                $status,
                $client['server_url'] ?? 'N/A',
                $details['transport'] ?? 'N/A',
                $connected,
                $details['request_count'] ?? 0,
                $details['error_count'] ?? 0,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * Display detailed client information.
     */
    protected function displayClientInfo(array $status): void
    {
        $this->line("");
        $this->line("📱 <info>{$status['name']}</info>");
        $this->line("   Status: " . ($status['connected'] ? '🟢 Connected' : '🔴 Disconnected'));

        if ($status['connected']) {
            $this->line("   Server: {$status['server_url']}");
            $this->line("   Transport: {$status['transport']}");

            if (isset($status['uptime'])) {
                $this->line("   Connected: " . $this->formatUptime($status['uptime']));
            }

            $this->line("   Requests: {$status['request_count']}");
            $this->line("   Errors: {$status['error_count']}");

            if (isset($status['last_response_time'])) {
                $this->line("   Last Response Time: " . round($status['last_response_time'] * 1000, 2) . "ms");
            }

            if ($this->getOutput()->isVerbose() && !empty($status['server_capabilities'])) {
                $this->line("   Server Capabilities:");
                foreach ($status['server_capabilities'] as $capability => $config) {
                    $this->line("     - {$capability}");
                }
            }
        }

        $this->line("");
    }

    /**
     * Display result data.
     */
    protected function displayResult(mixed $result): void
    {
        $this->line("");
        $this->line("Result:");
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("");
    }

    /**
     * Format uptime in human-readable format.
     */
    protected function formatUptime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds ago', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds ago', $minutes, $seconds);
        } else {
            return sprintf('%ds ago', $seconds);
        }
    }

    /**
     * Ensure client is connected; connect ephemerally if server-url provided.
     */
    protected function ensureConnected(string $clientName): array
    {
        $ephemeral = false;
        $serverUrl = $this->argument('server-url');
        if (!$this->clientManager->isConnected($clientName)) {
            if ($serverUrl) {
                $transport = $this->option('transport');
                $timeout = $this->option('timeout');
                $headersJson = $this->option('headers');
                $options = [];
                if ($timeout) $options['timeout'] = (int) $timeout;
                if ($headersJson) {
                    try {
                        $options['headers'] = json_decode($headersJson, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        $this->error("Invalid JSON in --headers: {$e->getMessage()}");
                        throw $e;
                    }
                }
                $this->clientManager->connect($clientName, $serverUrl, $transport, $options);
                $ephemeral = true;
            } else {
                $this->error("Client '{$clientName}' is not connected.");
                throw new \RuntimeException('Not connected');
            }
        }
        return ['ephemeral' => $ephemeral];
    }
}
