<?php

namespace MCP\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MCP\Laravel\Providers\McpServiceProvider;

/**
 * Base test case for Laravel MCP SDK tests.
 */
abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConfig();
        $this->setUpDatabase();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Mcp' => \MCP\Laravel\Facades\Mcp::class,
            'McpServer' => \MCP\Laravel\Facades\McpServer::class,
            'McpClient' => \MCP\Laravel\Facades\McpClient::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    /**
     * Set up MCP configuration for testing.
     */
    protected function setUpConfig(): void
    {
        config([
            'mcp.default_server' => 'test',
            'mcp.default_client' => 'test',
            'mcp.servers.test' => [
                'name' => 'Test Server',
                'version' => '1.0.0',
                'transport' => 'stdio',
                'capabilities' => [
                    'experimental' => [],
                    'sampling' => [],
                    'roots' => ['listChanged' => true],
                    'logging' => [],
                ],
                'tools' => [
                    'discover' => [],
                    'auto_register' => false,
                ],
                'resources' => [
                    'discover' => [],
                    'auto_register' => false,
                ],
                'prompts' => [
                    'discover' => [],
                    'auto_register' => false,
                ],
            ],
            'mcp.clients.test' => [
                'name' => 'Test Client',
                'version' => '1.0.0',
                'capabilities' => [
                    'experimental' => [],
                    'sampling' => [],
                    'roots' => ['listChanged' => true],
                ],
                'timeout' => 5000,
            ],
            'mcp.authorization.enabled' => false,
            'mcp.development.debug' => true,
            'mcp.events.enabled' => true,
            'mcp.cache.enabled' => true,
        ]);
    }

    /**
     * Set up database for testing.
     */
    protected function setUpDatabase(): void
    {
        // Create any necessary tables here if needed
    }

    /**
     * Create a mock tool for testing.
     */
    protected function createMockTool(string $name = 'test-tool'): \MCP\Laravel\Tests\Mocks\MockTool
    {
        return new \MCP\Laravel\Tests\Mocks\MockTool($name);
    }

    /**
     * Create a mock resource for testing.
     */
    protected function createMockResource(string $uri = 'test://resource'): \MCP\Laravel\Tests\Mocks\MockResource
    {
        return new \MCP\Laravel\Tests\Mocks\MockResource($uri);
    }

    /**
     * Create a mock prompt for testing.
     */
    protected function createMockPrompt(string $name = 'test-prompt'): \MCP\Laravel\Tests\Mocks\MockPrompt
    {
        return new \MCP\Laravel\Tests\Mocks\MockPrompt($name);
    }

    /**
     * Assert that an array has the expected structure.
     */
    protected function assertArrayStructure(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey($key, $actual);
                $this->assertIsArray($actual[$key]);
                $this->assertArrayStructure($value, $actual[$key]);
            } else {
                $this->assertArrayHasKey($key, $actual);
                if ($value !== '*') {
                    $this->assertEquals($value, $actual[$key]);
                }
            }
        }
    }

    /**
     * Assert that a response has MCP structure.
     */
    protected function assertMcpResponse(array $response): void
    {
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        $this->assertNotEmpty($response['content']);

        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertContains($content['type'], ['text', 'image', 'resource']);
        }
    }

    /**
     * Assert that an error response has the correct structure.
     */
    protected function assertMcpError(array $response): void
    {
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);
    }
}
