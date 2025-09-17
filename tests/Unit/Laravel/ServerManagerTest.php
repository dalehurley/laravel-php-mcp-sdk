<?php

namespace MCP\Laravel\Tests\Unit\Laravel;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Laravel\LaravelMcpServer;
use MCP\Laravel\Exceptions\McpException;

/**
 * Test cases for ServerManager.
 */
class ServerManagerTest extends TestCase
{
    protected ServerManager $serverManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverManager = app(ServerManager::class);
    }

    public function test_can_get_default_server(): void
    {
        $server = $this->serverManager->get();

        $this->assertInstanceOf(LaravelMcpServer::class, $server);
    }

    public function test_can_get_named_server(): void
    {
        $server = $this->serverManager->get('test');

        $this->assertInstanceOf(LaravelMcpServer::class, $server);
    }

    public function test_can_create_server_with_config(): void
    {
        $config = [
            'name' => 'Custom Server',
            'version' => '2.0.0',
            'transport' => 'http',
        ];

        $server = $this->serverManager->create('custom', $config);

        $this->assertInstanceOf(LaravelMcpServer::class, $server);
    }

    public function test_create_server_throws_exception_for_missing_config(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Server configuration not found for: nonexistent');

        $this->serverManager->create('nonexistent');
    }

    public function test_can_check_if_server_exists(): void
    {
        $this->assertTrue($this->serverManager->exists('test'));
        $this->assertFalse($this->serverManager->exists('nonexistent'));
    }

    public function test_can_list_servers(): void
    {
        $servers = $this->serverManager->list();

        $this->assertIsArray($servers);
        $this->assertArrayHasKey('test', $servers);

        $testServer = $servers['test'];
        $this->assertArrayHasKey('name', $testServer);
        $this->assertArrayHasKey('version', $testServer);
        $this->assertArrayHasKey('transport', $testServer);
        $this->assertArrayHasKey('running', $testServer);
    }

    public function test_can_get_server_status(): void
    {
        $status = $this->serverManager->getStatus('test');

        $this->assertIsArray($status);
        $this->assertArrayHasKey('name', $status);
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('configuration', $status);
    }

    public function test_get_status_throws_exception_for_nonexistent_server(): void
    {
        $this->expectException(\MCP\Laravel\Exceptions\ServerNotFoundException::class);

        $this->serverManager->getStatus('nonexistent');
    }

    public function test_can_add_tool_to_server(): void
    {
        $handler = function ($params) {
            return ['result' => 'test'];
        };

        $this->serverManager->addTool('test', 'test-tool', $handler, [
            'description' => 'Test tool',
        ]);

        $tools = $this->serverManager->getTools('test');
        $this->assertArrayHasKey('test-tool', $tools);
    }

    public function test_can_add_resource_to_server(): void
    {
        $handler = function ($uri) {
            return ['content' => 'test'];
        };

        $this->serverManager->addResource('test', 'test://resource', $handler, [
            'description' => 'Test resource',
        ]);

        $resources = $this->serverManager->getResources('test');
        $this->assertArrayHasKey('test://resource', $resources);
    }

    public function test_can_add_prompt_to_server(): void
    {
        $handler = function ($args) {
            return ['messages' => []];
        };

        $this->serverManager->addPrompt('test', 'test-prompt', $handler, [
            'description' => 'Test prompt',
        ]);

        $prompts = $this->serverManager->getPrompts('test');
        $this->assertArrayHasKey('test-prompt', $prompts);
    }

    public function test_can_get_capabilities(): void
    {
        $capabilities = $this->serverManager->getCapabilities('test');

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('experimental', $capabilities);
        $this->assertArrayHasKey('sampling', $capabilities);
        $this->assertArrayHasKey('roots', $capabilities);
        $this->assertArrayHasKey('logging', $capabilities);
    }

    public function test_can_set_capabilities(): void
    {
        $newCapabilities = [
            'experimental' => ['test' => true],
            'sampling' => [],
            'roots' => ['listChanged' => false],
            'logging' => [],
        ];

        $this->serverManager->setCapabilities('test', $newCapabilities);
        $capabilities = $this->serverManager->getCapabilities('test');

        $this->assertEquals($newCapabilities, $capabilities);
    }

    public function test_running_servers_tracking(): void
    {
        $runningServers = $this->serverManager->getRunningServers();
        $this->assertIsArray($runningServers);
        $this->assertEmpty($runningServers);
    }

    public function test_can_register_batch_components(): void
    {
        $components = [
            'tools' => [
                'batch-tool' => [
                    'handler' => fn($params) => ['result' => 'batch'],
                    'schema' => ['description' => 'Batch tool'],
                ],
            ],
            'resources' => [
                'batch://resource' => [
                    'handler' => fn($uri) => ['content' => 'batch'],
                    'metadata' => ['description' => 'Batch resource'],
                ],
            ],
            'prompts' => [
                'batch-prompt' => [
                    'handler' => fn($args) => ['messages' => []],
                    'schema' => ['description' => 'Batch prompt'],
                ],
            ],
        ];

        $this->serverManager->registerBatch('test', $components);

        $tools = $this->serverManager->getTools('test');
        $resources = $this->serverManager->getResources('test');
        $prompts = $this->serverManager->getPrompts('test');

        $this->assertArrayHasKey('batch-tool', $tools);
        $this->assertArrayHasKey('batch://resource', $resources);
        $this->assertArrayHasKey('batch-prompt', $prompts);
    }
}
