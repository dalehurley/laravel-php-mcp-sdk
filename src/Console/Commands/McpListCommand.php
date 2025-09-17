<?php

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use MCP\Laravel\Laravel\McpManager;

/**
 * Artisan command for listing MCP components and status.
 */
class McpListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:list
                            {--servers : List only servers}
                            {--clients : List only clients}
                            {--tools : List tools from all servers}
                            {--resources : List resources from all servers}
                            {--prompts : List prompts from all servers}
                            {--status : Include detailed status information}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all MCP servers, clients, and components';

    protected McpManager $mcpManager;

    public function __construct(McpManager $mcpManager)
    {
        parent::__construct();
        $this->mcpManager = $mcpManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $data = $this->gatherData();

            if ($this->option('json')) {
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->displayData($data);
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Gather all the data to display.
     */
    protected function gatherData(): array
    {
        $data = [];

        if (!$this->option('clients') && !$this->option('tools') && !$this->option('resources') && !$this->option('prompts')) {
            $data['servers'] = $this->mcpManager->listServers();
        }

        if (!$this->option('servers') && !$this->option('tools') && !$this->option('resources') && !$this->option('prompts')) {
            $data['clients'] = $this->mcpManager->listClients();
        }

        if ($this->option('servers') || (!$this->hasSpecificOption())) {
            $data['servers'] = $this->mcpManager->listServers();
        }

        if ($this->option('clients')) {
            $data['clients'] = $this->mcpManager->listClients();
        }

        if ($this->option('tools')) {
            $data['tools'] = $this->gatherAllTools();
        }

        if ($this->option('resources')) {
            $data['resources'] = $this->gatherAllResources();
        }

        if ($this->option('prompts')) {
            $data['prompts'] = $this->gatherAllPrompts();
        }

        if ($this->option('status')) {
            $data['system_status'] = $this->mcpManager->getSystemStatus();
        }

        return $data;
    }

    /**
     * Display the gathered data.
     */
    protected function displayData(array $data): void
    {
        $this->info("MCP System Overview");
        $this->line("");

        if (isset($data['servers'])) {
            $this->displayServers($data['servers']);
        }

        if (isset($data['clients'])) {
            $this->displayClients($data['clients']);
        }

        if (isset($data['tools'])) {
            $this->displayTools($data['tools']);
        }

        if (isset($data['resources'])) {
            $this->displayResources($data['resources']);
        }

        if (isset($data['prompts'])) {
            $this->displayPrompts($data['prompts']);
        }

        if (isset($data['system_status'])) {
            $this->displaySystemStatus($data['system_status']);
        }
    }

    /**
     * Display servers information.
     */
    protected function displayServers(array $servers): void
    {
        $this->info("📡 MCP Servers ({" . count($servers) . "})");
        $this->line("");

        if (empty($servers)) {
            $this->warn("  No servers configured.");
            $this->line("");
            return;
        }

        $headers = ['Name', 'Status', 'Transport', 'Version', 'Tools', 'Resources', 'Prompts'];
        $rows = [];

        foreach ($servers as $name => $server) {
            $status = $server['running'] ? '🟢 Running' : '🔴 Stopped';

            $toolsCount = 0;
            $resourcesCount = 0;
            $promptsCount = 0;

            if ($server['running']) {
                try {
                    $serverStatus = $this->mcpManager->servers()->getStatus($name);
                    $toolsCount = $serverStatus['tools_count'] ?? 0;
                    $resourcesCount = $serverStatus['resources_count'] ?? 0;
                    $promptsCount = $serverStatus['prompts_count'] ?? 0;
                } catch (\Exception $e) {
                    // Ignore errors getting detailed status
                }
            }

            $rows[] = [
                $name,
                $status,
                $server['transport'],
                $server['version'],
                $toolsCount,
                $resourcesCount,
                $promptsCount,
            ];
        }

        $this->table($headers, $rows);
        $this->line("");
    }

    /**
     * Display clients information.
     */
    protected function displayClients(array $clients): void
    {
        $this->info("📱 MCP Clients (" . count($clients) . ")");
        $this->line("");

        if (empty($clients)) {
            $this->warn("  No clients configured.");
            $this->line("");
            return;
        }

        $headers = ['Name', 'Status', 'Server', 'Version', 'Requests', 'Errors'];
        $rows = [];

        foreach ($clients as $name => $client) {
            $status = $client['connected'] ? '🟢 Connected' : '🔴 Disconnected';
            $serverUrl = $client['server_url'] ?? 'N/A';

            $requestCount = 0;
            $errorCount = 0;

            if ($client['connected']) {
                try {
                    $clientStatus = $this->mcpManager->clients()->getStatus($name);
                    $requestCount = $clientStatus['request_count'] ?? 0;
                    $errorCount = $clientStatus['error_count'] ?? 0;
                } catch (\Exception $e) {
                    // Ignore errors getting detailed status
                }
            }

            $rows[] = [
                $name,
                $status,
                $serverUrl,
                $client['version'] ?? 'N/A',
                $requestCount,
                $errorCount,
            ];
        }

        $this->table($headers, $rows);
        $this->line("");
    }

    /**
     * Display tools information.
     */
    protected function displayTools(array $tools): void
    {
        $this->info("🔧 Available Tools (" . array_sum(array_map('count', $tools)) . ")");
        $this->line("");

        if (empty($tools)) {
            $this->warn("  No tools available.");
            $this->line("");
            return;
        }

        foreach ($tools as $serverName => $serverTools) {
            if (empty($serverTools)) {
                continue;
            }

            $this->line("  <comment>Server: {$serverName}</comment>");
            foreach ($serverTools as $toolName => $tool) {
                $this->line("    🔧 {$toolName}");
                if (isset($tool['schema']['description'])) {
                    $this->line("       " . $tool['schema']['description']);
                }
            }
            $this->line("");
        }
    }

    /**
     * Display resources information.
     */
    protected function displayResources(array $resources): void
    {
        $this->info("📄 Available Resources (" . array_sum(array_map('count', $resources)) . ")");
        $this->line("");

        if (empty($resources)) {
            $this->warn("  No resources available.");
            $this->line("");
            return;
        }

        foreach ($resources as $serverName => $serverResources) {
            if (empty($serverResources)) {
                continue;
            }

            $this->line("  <comment>Server: {$serverName}</comment>");
            foreach ($serverResources as $uri => $resource) {
                $this->line("    📄 {$uri}");
                if (isset($resource['metadata']['description'])) {
                    $this->line("       " . $resource['metadata']['description']);
                }
            }
            $this->line("");
        }
    }

    /**
     * Display prompts information.
     */
    protected function displayPrompts(array $prompts): void
    {
        $this->info("💬 Available Prompts (" . array_sum(array_map('count', $prompts)) . ")");
        $this->line("");

        if (empty($prompts)) {
            $this->warn("  No prompts available.");
            $this->line("");
            return;
        }

        foreach ($prompts as $serverName => $serverPrompts) {
            if (empty($serverPrompts)) {
                continue;
            }

            $this->line("  <comment>Server: {$serverName}</comment>");
            foreach ($serverPrompts as $promptName => $prompt) {
                $this->line("    💬 {$promptName}");
                if (isset($prompt['schema']['description'])) {
                    $this->line("       " . $prompt['schema']['description']);
                }
            }
            $this->line("");
        }
    }

    /**
     * Display system status.
     */
    protected function displaySystemStatus(array $status): void
    {
        $this->info("📊 System Status");
        $this->line("");

        $this->line("  Active Connections: {$status['active_connections']}");

        $this->line("  Configuration:");
        $this->line("    Default Server: {$status['configuration']['default_server']}");
        $this->line("    Default Client: {$status['configuration']['default_client']}");

        $this->line("  Enabled Transports:");
        foreach ($status['configuration']['transports_enabled'] as $transport => $enabled) {
            $icon = $enabled ? '✅' : '❌';
            $this->line("    {$icon} {$transport}");
        }

        $this->line("  Enabled Features:");
        foreach ($status['configuration']['features_enabled'] as $feature => $enabled) {
            $icon = $enabled ? '✅' : '❌';
            $this->line("    {$icon} {$feature}");
        }

        $this->line("");
    }

    /**
     * Gather all tools from all servers.
     */
    protected function gatherAllTools(): array
    {
        $allTools = [];
        $servers = $this->mcpManager->listServers();

        foreach ($servers as $serverName => $server) {
            try {
                // Get tools from server instance regardless of running status
                // This allows discovery to work even when servers aren't started
                $tools = $this->mcpManager->servers()->getTools($serverName);
                $allTools[$serverName] = $tools;
            } catch (\Exception $e) {
                $allTools[$serverName] = [];
            }
        }

        return $allTools;
    }

    /**
     * Gather all resources from all servers.
     */
    protected function gatherAllResources(): array
    {
        $allResources = [];
        $servers = $this->mcpManager->listServers();

        foreach ($servers as $serverName => $server) {
            try {
                // Get resources from server instance regardless of running status
                $resources = $this->mcpManager->servers()->getResources($serverName);
                $allResources[$serverName] = $resources;
            } catch (\Exception $e) {
                $allResources[$serverName] = [];
            }
        }

        return $allResources;
    }

    /**
     * Gather all prompts from all servers.
     */
    protected function gatherAllPrompts(): array
    {
        $allPrompts = [];
        $servers = $this->mcpManager->listServers();

        foreach ($servers as $serverName => $server) {
            try {
                // Get prompts from server instance regardless of running status
                $prompts = $this->mcpManager->servers()->getPrompts($serverName);
                $allPrompts[$serverName] = $prompts;
            } catch (\Exception $e) {
                $allPrompts[$serverName] = [];
            }
        }

        return $allPrompts;
    }

    /**
     * Check if any specific option is set.
     */
    protected function hasSpecificOption(): bool
    {
        return $this->option('servers') ||
            $this->option('clients') ||
            $this->option('tools') ||
            $this->option('resources') ||
            $this->option('prompts');
    }
}
