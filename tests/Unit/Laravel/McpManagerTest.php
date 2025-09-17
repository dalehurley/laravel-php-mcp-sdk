<?php

namespace MCP\Laravel\Tests\Unit\Laravel;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Laravel\McpManager;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Laravel\ClientManager;

/**
 * Test cases for McpManager.
 */
class McpManagerTest extends TestCase
{
    protected McpManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(McpManager::class);
    }

    public function test_can_get_server_manager(): void
    {
        $serverManager = $this->manager->servers();

        $this->assertInstanceOf(ServerManager::class, $serverManager);
    }

    public function test_can_get_client_manager(): void
    {
        $clientManager = $this->manager->clients();

        $this->assertInstanceOf(ClientManager::class, $clientManager);
    }

    public function test_can_get_default_server(): void
    {
        $server = $this->manager->server();

        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpServer::class, $server);
    }

    public function test_can_get_named_server(): void
    {
        $server = $this->manager->server('test');

        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpServer::class, $server);
    }

    public function test_can_get_default_client(): void
    {
        $client = $this->manager->client();

        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpClient::class, $client);
    }

    public function test_can_get_named_client(): void
    {
        $client = $this->manager->client('test');

        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpClient::class, $client);
    }

    public function test_can_list_servers(): void
    {
        $servers = $this->manager->listServers();

        $this->assertIsArray($servers);
        $this->assertArrayHasKey('test', $servers);
        $this->assertArrayHasKey('name', $servers['test']);
        $this->assertArrayHasKey('running', $servers['test']);
    }

    public function test_can_list_clients(): void
    {
        $clients = $this->manager->listClients();

        $this->assertIsArray($clients);
        $this->assertArrayHasKey('test', $clients);
        $this->assertArrayHasKey('name', $clients['test']);
        $this->assertArrayHasKey('connected', $clients['test']);
    }

    public function test_can_get_system_status(): void
    {
        $status = $this->manager->getSystemStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('servers', $status);
        $this->assertArrayHasKey('clients', $status);
        $this->assertArrayHasKey('configuration', $status);
        $this->assertArrayHasKey('active_connections', $status);
    }

    public function test_can_perform_health_check(): void
    {
        $health = $this->manager->healthCheck();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    public function test_can_register_connection(): void
    {
        $this->manager->registerConnection('server', 'test', [
            'transport' => 'stdio',
            'status' => 'running',
        ]);

        $connections = $this->manager->getActiveConnections();

        $this->assertCount(1, $connections);
        $this->assertTrue($connections->has('server:test'));
    }

    public function test_can_unregister_connection(): void
    {
        $this->manager->registerConnection('server', 'test', ['status' => 'running']);
        $this->manager->unregisterConnection('server', 'test');

        $connections = $this->manager->getActiveConnections();

        $this->assertCount(0, $connections);
        $this->assertFalse($connections->has('server:test'));
    }

    public function test_shutdown_handles_errors_gracefully(): void
    {
        // Register a connection
        $this->manager->registerConnection('server', 'test', ['status' => 'running']);

        // Shutdown should not throw exceptions
        $this->manager->shutdown();

        // Connections should be cleared
        $connections = $this->manager->getActiveConnections();
        $this->assertCount(0, $connections);
    }
}
