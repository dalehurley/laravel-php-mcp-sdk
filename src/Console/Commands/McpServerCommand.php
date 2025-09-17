<?php

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Exceptions\McpException;

/**
 * Artisan command for managing MCP servers.
 */
class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:server
                            {action : The action to perform (start, stop, restart, status, list)}
                            {server? : The server name (optional, uses default if not specified)}
                            {--transport= : The transport to use (stdio, http, websocket)}
                            {--port= : The port to use for HTTP/WebSocket transport}
                            {--host= : The host to bind to for HTTP/WebSocket transport}
                            {--daemon : Run the server as a daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage MCP servers (start, stop, restart, status, list)';

    protected ServerManager $serverManager;

    public function __construct(ServerManager $serverManager)
    {
        parent::__construct();
        $this->serverManager = $serverManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $serverName = $this->argument('server');

        try {
            return match ($action) {
                'start' => $this->startServer($serverName),
                'stop' => $this->stopServer($serverName),
                'restart' => $this->restartServer($serverName),
                'status' => $this->showStatus($serverName),
                'list' => $this->listServers(),
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
     * Start a server.
     */
    protected function startServer(?string $serverName): int
    {
        $serverName = $serverName ?? config('mcp.default_server', 'main');

        if (!$this->serverManager->exists($serverName)) {
            $this->error("Server '{$serverName}' is not configured.");
            return 1;
        }

        if ($this->serverManager->isRunning($serverName)) {
            $this->warn("Server '{$serverName}' is already running.");
            return 0;
        }

        $transport = $this->option('transport');

        $this->info("Starting MCP server '{$serverName}'...");

        if ($this->getOutput()->isVerbose()) {
            $config = config("mcp.servers.{$serverName}");
            $this->line("Configuration:");
            $this->line("  Name: {$config['name']}");
            $this->line("  Version: {$config['version']}");
            $this->line("  Transport: " . ($transport ?? $config['transport']));
        }

        $this->serverManager->start($serverName, $transport);

        $status = $this->serverManager->getStatus($serverName);

        $this->info("✅ Server '{$serverName}' started successfully!");
        $this->displayServerInfo($status);

        if ($this->option('daemon')) {
            $this->info("Running in daemon mode. Press Ctrl+C to stop.");
            // Keep the process running
            while ($this->serverManager->isRunning($serverName)) {
                sleep(1);
            }
        }

        return 0;
    }

    /**
     * Stop a server.
     */
    protected function stopServer(?string $serverName): int
    {
        $serverName = $serverName ?? config('mcp.default_server', 'main');

        if (!$this->serverManager->exists($serverName)) {
            $this->error("Server '{$serverName}' is not configured.");
            return 1;
        }

        if (!$this->serverManager->isRunning($serverName)) {
            $this->warn("Server '{$serverName}' is not running.");
            return 0;
        }

        $this->info("Stopping MCP server '{$serverName}'...");

        $this->serverManager->stop($serverName);

        $this->info("✅ Server '{$serverName}' stopped successfully!");

        return 0;
    }

    /**
     * Restart a server.
     */
    protected function restartServer(?string $serverName): int
    {
        $serverName = $serverName ?? config('mcp.default_server', 'main');

        $this->info("Restarting MCP server '{$serverName}'...");

        if ($this->serverManager->isRunning($serverName)) {
            $this->stopServer($serverName);
        }

        return $this->startServer($serverName);
    }

    /**
     * Show server status.
     */
    protected function showStatus(?string $serverName): int
    {
        if ($serverName) {
            return $this->showSingleServerStatus($serverName);
        }

        return $this->showAllServersStatus();
    }

    /**
     * Show status for a single server.
     */
    protected function showSingleServerStatus(string $serverName): int
    {
        if (!$this->serverManager->exists($serverName)) {
            $this->error("Server '{$serverName}' is not configured.");
            return 1;
        }

        $status = $this->serverManager->getStatus($serverName);

        $this->info("Server Status: {$serverName}");
        $this->displayServerInfo($status);

        return 0;
    }

    /**
     * Show status for all servers.
     */
    protected function showAllServersStatus(): int
    {
        $servers = $this->serverManager->list();

        if (empty($servers)) {
            $this->warn("No servers configured.");
            return 0;
        }

        $this->info("MCP Servers Status:");
        $this->line("");

        $headers = ['Name', 'Status', 'Transport', 'Uptime', 'Tools', 'Resources', 'Prompts'];
        $rows = [];

        foreach ($servers as $name => $server) {
            $status = $server['running'] ? '🟢 Running' : '🔴 Stopped';
            $uptime = $server['started_at'] ? $server['started_at']->diffForHumans() : 'N/A';

            $details = $this->serverManager->getStatus($name);

            $rows[] = [
                $name,
                $status,
                $server['transport'],
                $uptime,
                $details['tools_count'] ?? 0,
                $details['resources_count'] ?? 0,
                $details['prompts_count'] ?? 0,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * List all configured servers.
     */
    protected function listServers(): int
    {
        $servers = $this->serverManager->list();

        if (empty($servers)) {
            $this->warn("No servers configured.");
            return 0;
        }

        $this->info("Configured MCP Servers:");
        $this->line("");

        foreach ($servers as $name => $server) {
            $this->line("📡 <info>{$name}</info>");
            $this->line("   Name: {$server['display_name']}");
            $this->line("   Version: {$server['version']}");
            $this->line("   Transport: {$server['transport']}");
            $this->line("   Status: " . ($server['running'] ? '🟢 Running' : '🔴 Stopped'));

            if ($server['running'] && $server['started_at']) {
                $this->line("   Uptime: " . $server['started_at']->diffForHumans());
            }

            $this->line("");
        }

        return 0;
    }

    /**
     * Display detailed server information.
     */
    protected function displayServerInfo(array $status): void
    {
        $this->line("");
        $this->line("📡 <info>{$status['name']}</info>");
        $this->line("   Status: " . ($status['running'] ? '🟢 Running' : '🔴 Stopped'));

        if ($status['running']) {
            $this->line("   Transport: {$status['transport']}");

            if (isset($status['process_id'])) {
                $this->line("   Process ID: {$status['process_id']}");
            }

            if (isset($status['uptime'])) {
                $this->line("   Uptime: " . $this->formatUptime($status['uptime']));
            }

            if (isset($status['memory_usage'])) {
                $this->line("   Memory: " . $this->formatBytes($status['memory_usage']));
            }

            $this->line("   Tools: {$status['tools_count']}");
            $this->line("   Resources: {$status['resources_count']}");
            $this->line("   Prompts: {$status['prompts_count']}");

            if ($this->getOutput()->isVerbose() && !empty($status['capabilities'])) {
                $this->line("   Capabilities:");
                foreach ($status['capabilities'] as $capability => $config) {
                    $this->line("     - {$capability}");
                }
            }
        }

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
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Format bytes in human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
