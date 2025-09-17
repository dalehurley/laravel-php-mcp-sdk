<?php

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use MCP\Laravel\Laravel\McpManager;

/**
 * Artisan command for testing MCP setup and connections.
 */
class McpTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:test
                            {--server= : Test specific server}
                            {--client= : Test specific client}
                            {--url= : Test connection to specific URL}
                            {--transport= : Transport to use for testing}
                            {--health : Run health check on all components}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MCP configuration and connections';

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
        $this->info("🧪 MCP Testing Suite");
        $this->line("");

        $passed = 0;
        $failed = 0;

        try {
            if ($this->option('health')) {
                [$p, $f] = $this->runHealthCheck();
                $passed += $p;
                $failed += $f;
            } elseif ($this->option('server')) {
                [$p, $f] = $this->testServer($this->option('server'));
                $passed += $p;
                $failed += $f;
            } elseif ($this->option('client')) {
                [$p, $f] = $this->testClient($this->option('client'));
                $passed += $p;
                $failed += $f;
            } elseif ($this->option('url')) {
                [$p, $f] = $this->testConnection($this->option('url'));
                $passed += $p;
                $failed += $f;
            } else {
                [$p, $f] = $this->runFullTest();
                $passed += $p;
                $failed += $f;
            }

            $this->displaySummary($passed, $failed);

            return $failed > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error("Test failed with exception: {$e->getMessage()}");
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Run full test suite.
     */
    protected function runFullTest(): array
    {
        $passed = 0;
        $failed = 0;

        // Test configuration
        [$p, $f] = $this->testConfiguration();
        $passed += $p;
        $failed += $f;

        // Test servers
        [$p, $f] = $this->testAllServers();
        $passed += $p;
        $failed += $f;

        // Test clients
        [$p, $f] = $this->testAllClients();
        $passed += $p;
        $failed += $f;

        return [$passed, $failed];
    }

    /**
     * Run health check.
     */
    protected function runHealthCheck(): array
    {
        $this->info("🏥 Running Health Check");
        $this->line("");

        $health = $this->mcpManager->healthCheck();
        $passed = 0;
        $failed = 0;

        $this->line("Overall Status: " . $this->getStatusIcon($health['status']) . " {$health['status']}");
        $this->line("");

        foreach ($health['checks'] as $component => $check) {
            $status = $check['status'];
            $icon = $this->getStatusIcon($status);

            $this->line("{$icon} {$component}: {$status}");

            if ($status === 'healthy') {
                $passed++;
            } else {
                $failed++;
                if (isset($check['error']) && $this->getOutput()->isVerbose()) {
                    $this->line("   Error: {$check['error']}");
                }
            }
        }

        $this->line("");
        return [$passed, $failed];
    }

    /**
     * Test configuration.
     */
    protected function testConfiguration(): array
    {
        $this->info("⚙️  Testing Configuration");
        $passed = 0;
        $failed = 0;

        // Test basic config
        if (config('mcp')) {
            $this->line("✅ MCP configuration loaded");
            $passed++;
        } else {
            $this->line("❌ MCP configuration not found");
            $failed++;
        }

        // Test server configurations
        $servers = config('mcp.servers', []);
        if (!empty($servers)) {
            $this->line("✅ Server configurations found (" . count($servers) . ")");
            $passed++;
        } else {
            $this->line("⚠️  No server configurations found");
        }

        // Test client configurations
        $clients = config('mcp.clients', []);
        if (!empty($clients)) {
            $this->line("✅ Client configurations found (" . count($clients) . ")");
            $passed++;
        } else {
            $this->line("⚠️  No client configurations found");
        }

        // Test transport configurations
        $transports = config('mcp.transports', []);
        foreach (['stdio', 'http', 'websocket'] as $transport) {
            if (isset($transports[$transport]) && $transports[$transport]['enabled']) {
                $this->line("✅ {$transport} transport enabled");
                $passed++;
            } else {
                $this->line("⚠️  {$transport} transport disabled");
            }
        }

        $this->line("");
        return [$passed, $failed];
    }

    /**
     * Test all servers.
     */
    protected function testAllServers(): array
    {
        $this->info("📡 Testing Servers");
        $passed = 0;
        $failed = 0;

        $servers = $this->mcpManager->listServers();

        if (empty($servers)) {
            $this->line("⚠️  No servers configured to test");
            $this->line("");
            return [$passed, $failed];
        }

        foreach ($servers as $serverName => $server) {
            [$p, $f] = $this->testServer($serverName);
            $passed += $p;
            $failed += $f;
        }

        $this->line("");
        return [$passed, $failed];
    }

    /**
     * Test a specific server.
     */
    protected function testServer(string $serverName): array
    {
        $passed = 0;
        $failed = 0;

        if (!$this->mcpManager->servers()->exists($serverName)) {
            $this->line("❌ Server '{$serverName}' not configured");
            return [$passed, $failed + 1];
        }

        try {
            $server = $this->mcpManager->servers()->get($serverName);
            $this->line("✅ Server '{$serverName}' instance created");
            $passed++;

            $status = $this->mcpManager->servers()->getStatus($serverName);
            if ($status['running']) {
                $this->line("✅ Server '{$serverName}' is running");
                $passed++;

                // Test server capabilities
                if (!empty($status['capabilities'])) {
                    $this->line("✅ Server '{$serverName}' has capabilities");
                    $passed++;
                } else {
                    $this->line("⚠️  Server '{$serverName}' has no capabilities");
                }

                // Test components
                $toolsCount = $status['tools_count'] ?? 0;
                $resourcesCount = $status['resources_count'] ?? 0;
                $promptsCount = $status['prompts_count'] ?? 0;

                $this->line("ℹ️  Server '{$serverName}' has {$toolsCount} tools, {$resourcesCount} resources, {$promptsCount} prompts");
            } else {
                $this->line("⚠️  Server '{$serverName}' is not running");
            }
        } catch (\Exception $e) {
            $this->line("❌ Server '{$serverName}' test failed: {$e->getMessage()}");
            $failed++;
        }

        return [$passed, $failed];
    }

    /**
     * Test all clients.
     */
    protected function testAllClients(): array
    {
        $this->info("📱 Testing Clients");
        $passed = 0;
        $failed = 0;

        $clients = $this->mcpManager->listClients();

        if (empty($clients)) {
            $this->line("⚠️  No clients configured to test");
            $this->line("");
            return [$passed, $failed];
        }

        foreach ($clients as $clientName => $client) {
            [$p, $f] = $this->testClient($clientName);
            $passed += $p;
            $failed += $f;
        }

        $this->line("");
        return [$passed, $failed];
    }

    /**
     * Test a specific client.
     */
    protected function testClient(string $clientName): array
    {
        $passed = 0;
        $failed = 0;

        if (!$this->mcpManager->clients()->exists($clientName)) {
            $this->line("❌ Client '{$clientName}' not configured");
            return [$passed, $failed + 1];
        }

        try {
            $client = $this->mcpManager->clients()->get($clientName);
            $this->line("✅ Client '{$clientName}' instance created");
            $passed++;

            $status = $this->mcpManager->clients()->getStatus($clientName);
            if ($status['connected']) {
                $this->line("✅ Client '{$clientName}' is connected");
                $passed++;

                // Test client capabilities
                if (!empty($status['capabilities'])) {
                    $this->line("✅ Client '{$clientName}' has capabilities");
                    $passed++;
                } else {
                    $this->line("⚠️  Client '{$clientName}' has no capabilities");
                }

                // Test connection info
                $requestCount = $status['request_count'] ?? 0;
                $errorCount = $status['error_count'] ?? 0;

                $this->line("ℹ️  Client '{$clientName}' has made {$requestCount} requests with {$errorCount} errors");

                if ($errorCount > 0 && $requestCount > 0) {
                    $errorRate = ($errorCount / $requestCount) * 100;
                    if ($errorRate > 10) {
                        $this->line("⚠️  High error rate: " . round($errorRate, 1) . "%");
                    }
                }
            } else {
                $this->line("⚠️  Client '{$clientName}' is not connected");
            }
        } catch (\Exception $e) {
            $this->line("❌ Client '{$clientName}' test failed: {$e->getMessage()}");
            $failed++;
        }

        return [$passed, $failed];
    }

    /**
     * Test connection to a specific URL.
     */
    protected function testConnection(string $url): array
    {
        $this->info("🔗 Testing Connection to {$url}");
        $passed = 0;
        $failed = 0;

        try {
            $transport = $this->option('transport');
            $result = $this->mcpManager->clients()->testConnection($url, $transport);

            if ($result['success']) {
                $this->line("✅ Connection successful");
                $this->line("   Transport: {$result['transport']}");
                $this->line("   Response Time: " . round($result['response_time'], 2) . "ms");

                if (!empty($result['capabilities'])) {
                    $this->line("   Server Capabilities: " . implode(', ', array_keys($result['capabilities'])));
                }

                $passed++;
            } else {
                $this->line("❌ Connection failed: {$result['error']}");
                $failed++;
            }
        } catch (\Exception $e) {
            $this->line("❌ Connection test failed: {$e->getMessage()}");
            $failed++;
        }

        $this->line("");
        return [$passed, $failed];
    }

    /**
     * Display test summary.
     */
    protected function displaySummary(int $passed, int $failed): void
    {
        $this->line("📊 Test Summary");
        $this->line("================");
        $this->line("✅ Passed: {$passed}");
        $this->line("❌ Failed: {$failed}");
        $this->line("📈 Total:  " . ($passed + $failed));

        if ($failed === 0) {
            $this->info("🎉 All tests passed!");
        } else {
            $this->error("💥 {$failed} test(s) failed");
        }

        $this->line("");
    }

    /**
     * Get status icon.
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy', 'running', 'connected' => '✅',
            'degraded', 'stopped', 'disconnected' => '⚠️',
            'error', 'failed' => '❌',
            default => 'ℹ️',
        };
    }
}
