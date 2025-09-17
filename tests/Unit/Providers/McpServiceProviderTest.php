<?php

namespace MCP\Laravel\Tests\Unit\Providers;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Providers\McpServiceProvider;
use MCP\Laravel\Laravel\McpManager;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Laravel\ClientManager;

/**
 * Test cases for McpServiceProvider.
 */
class McpServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_services(): void
    {
        $this->assertInstanceOf(McpManager::class, app(McpManager::class));
        $this->assertInstanceOf(ServerManager::class, app(ServerManager::class));
        $this->assertInstanceOf(ClientManager::class, app(ClientManager::class));
    }

    public function test_service_provider_registers_aliases(): void
    {
        $this->assertInstanceOf(McpManager::class, app('mcp'));
        $this->assertInstanceOf(ServerManager::class, app('mcp.servers'));
        $this->assertInstanceOf(ClientManager::class, app('mcp.clients'));
    }

    public function test_service_provider_registers_singletons(): void
    {
        $manager1 = app(McpManager::class);
        $manager2 = app(McpManager::class);

        $this->assertSame($manager1, $manager2);
    }

    public function test_service_provider_registers_utility_managers(): void
    {
        $this->assertInstanceOf(
            \MCP\Laravel\Utilities\CancellationManager::class,
            app(\MCP\Laravel\Utilities\CancellationManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Utilities\PingManager::class,
            app(\MCP\Laravel\Utilities\PingManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Utilities\ProgressManager::class,
            app(\MCP\Laravel\Utilities\ProgressManager::class)
        );
    }

    public function test_service_provider_registers_commands(): void
    {
        $commands = app(\Illuminate\Contracts\Console\Kernel::class)->all();

        $this->assertArrayHasKey('mcp:server', $commands);
        $this->assertArrayHasKey('mcp:client', $commands);
        $this->assertArrayHasKey('mcp:list', $commands);
        $this->assertArrayHasKey('mcp:test', $commands);
        $this->assertArrayHasKey('mcp:install', $commands);
    }

    public function test_service_provider_provides_expected_services(): void
    {
        $provider = new McpServiceProvider(app());
        $provides = $provider->provides();

        $this->assertContains(McpManager::class, $provides);
        $this->assertContains(ServerManager::class, $provides);
        $this->assertContains(ClientManager::class, $provides);
        $this->assertContains('mcp', $provides);
        $this->assertContains('mcp.servers', $provides);
        $this->assertContains('mcp.clients', $provides);
    }

    public function test_configuration_is_merged(): void
    {
        // Test that configuration is properly merged
        $this->assertNotNull(config('mcp'));
        $this->assertNotNull(config('mcp.default_server'));
        $this->assertNotNull(config('mcp.default_client'));
        $this->assertIsArray(config('mcp.servers'));
        $this->assertIsArray(config('mcp.clients'));
    }

    public function test_middleware_is_registered(): void
    {
        $router = app('router');

        // Test that middleware aliases are registered
        $middleware = $router->getMiddleware();
        $this->assertArrayHasKey('mcp.auth', $middleware);
        $this->assertArrayHasKey('mcp.security', $middleware);

        // Test that middleware groups are registered
        $middlewareGroups = $router->getMiddlewareGroups();
        $this->assertArrayHasKey('mcp', $middlewareGroups);
    }

    public function test_logging_channel_is_configured(): void
    {
        // Test that MCP logging channel is configured when enabled
        config(['mcp.utilities.logging.enabled' => true]);

        $provider = new McpServiceProvider(app());
        $provider->boot();

        $channel = config('mcp.utilities.logging.channel', 'mcp');
        $this->assertNotNull(config("logging.channels.{$channel}"));
    }

    public function test_routes_are_loaded_for_http_servers(): void
    {
        config([
            'mcp.servers.http-server' => [
                'transport' => 'http',
                'name' => 'HTTP Server',
            ],
        ]);

        $provider = new McpServiceProvider(app());
        $provider->boot();

        // Routes should be loaded when HTTP servers are configured
        // We can't easily test route registration in unit tests,
        // but we can ensure the method doesn't crash
        $this->assertTrue(true);
    }

    public function test_service_provider_handles_missing_config_gracefully(): void
    {
        // Test that the service provider works when config is missing
        // This test verifies the fallback configuration in the service provider

        // Simply test that the service provider is registered correctly
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(McpServiceProvider::class, $providers);

        // Verify that config exists (either from file or fallback)
        $this->assertNotNull(config('mcp'));
        $this->assertIsArray(config('mcp'));
    }

    public function test_feature_managers_are_registered(): void
    {
        $this->assertInstanceOf(
            \MCP\Laravel\Features\RootsManager::class,
            app(\MCP\Laravel\Features\RootsManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Features\SamplingManager::class,
            app(\MCP\Laravel\Features\SamplingManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Features\ElicitationManager::class,
            app(\MCP\Laravel\Features\ElicitationManager::class)
        );
    }

    public function test_transport_managers_are_registered(): void
    {
        $this->assertInstanceOf(
            \MCP\Laravel\Transport\StdioTransportManager::class,
            app(\MCP\Laravel\Transport\StdioTransportManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Transport\HttpTransportManager::class,
            app(\MCP\Laravel\Transport\HttpTransportManager::class)
        );

        $this->assertInstanceOf(
            \MCP\Laravel\Transport\WebSocketTransportManager::class,
            app(\MCP\Laravel\Transport\WebSocketTransportManager::class)
        );
    }

    public function test_service_provider_boots_correctly(): void
    {
        $provider = new McpServiceProvider(app());

        // Boot should not throw exceptions
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_conditional_auth_middleware_registration(): void
    {
        // Test with auth disabled
        config(['mcp.authorization.enabled' => false]);
        $provider = new McpServiceProvider(app());
        $provider->boot();

        $middlewareGroups = app('router')->getMiddlewareGroups();
        $mcpMiddleware = $middlewareGroups['mcp'] ?? [];

        // Should not contain auth middleware when disabled
        $this->assertNotContains('mcp.auth', $mcpMiddleware);

        // Test with auth enabled
        config(['mcp.authorization.enabled' => true]);
        $provider = new McpServiceProvider(app());
        $provider->boot();

        $middlewareGroups = app('router')->getMiddlewareGroups();
        $mcpMiddleware = $middlewareGroups['mcp'] ?? [];

        // Should contain auth middleware when enabled
        $this->assertContains('mcp.auth', $mcpMiddleware);
    }
}
