<?php

namespace MCP\Laravel\Tests\Feature\Http;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Tests\Mocks\MockTool;
use MCP\Laravel\Tests\Mocks\MockResource;
use MCP\Laravel\Tests\Mocks\MockPrompt;

/**
 * Feature tests for MCP HTTP controller.
 */
class McpControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable HTTP transport for testing
        config([
            'mcp.transports.http.enabled' => true,
            'mcp.servers.test.transport' => 'http',
            'mcp.authorization.enabled' => false, // Disable auth for basic tests
        ]);

        // Register test components
        $this->registerTestComponents();
    }

    protected function registerTestComponents(): void
    {
        $serverManager = app(\MCP\Laravel\Laravel\ServerManager::class);

        // Mock server as running
        $this->mockServerAsRunning($serverManager);

        // Add test tool
        $tool = new MockTool('test-tool');
        $serverManager->addTool('test', 'test-tool', [$tool, 'handle'], [
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
        ]);

        // Add test resource
        $resource = new MockResource('test://resource');
        $serverManager->addResource('test', 'test://resource', [$resource, 'read'], [
            'description' => $resource->description(),
        ]);

        // Add test prompt
        $prompt = new MockPrompt('test-prompt');
        $serverManager->addPrompt('test', 'test-prompt', [$prompt, 'handle'], [
            'description' => $prompt->description(),
            'arguments' => $prompt->arguments(),
        ]);
    }

    protected function mockServerAsRunning(object $serverManager): void
    {
        // Use reflection to set the server as running for testing
        $reflection = new \ReflectionClass($serverManager);
        if ($reflection->hasProperty('runningServers')) {
            $property = $reflection->getProperty('runningServers');
            $property->setAccessible(true);
            $property->setValue($serverManager, [
                'test' => [
                    'started_at' => now(),
                    'transport' => 'http',
                    'pid' => getmypid(),
                ]
            ]);
        }
    }

    public function test_can_get_server_capabilities(): void
    {
        $response = $this->getJson('/mcp/test/capabilities');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'capabilities',
                'serverInfo' => [
                    'name',
                    'version',
                ],
            ],
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertArrayHasKey('experimental', $data['result']['capabilities']);
    }

    public function test_can_list_tools(): void
    {
        $response = $this->getJson('/mcp/test/tools');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'tools' => [
                    '*' => [
                        'name',
                        'description',
                        'inputSchema',
                    ],
                ],
            ],
            'id',
        ]);

        $data = $response->json();
        $tools = collect($data['result']['tools']);
        $testTool = $tools->firstWhere('name', 'test-tool');

        $this->assertNotNull($testTool);
        $this->assertEquals('A mock tool for testing purposes', $testTool['description']);
    }

    public function test_can_list_resources(): void
    {
        $response = $this->getJson('/mcp/test/resources');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'resources' => [
                    '*' => [
                        'uri',
                        'name',
                        'description',
                        'mimeType',
                    ],
                ],
            ],
            'id',
        ]);

        $data = $response->json();
        $resources = collect($data['result']['resources']);
        $testResource = $resources->firstWhere('uri', 'test://resource');

        $this->assertNotNull($testResource);
        $this->assertEquals('A mock resource for testing purposes', $testResource['description']);
    }

    public function test_can_list_prompts(): void
    {
        $response = $this->getJson('/mcp/test/prompts');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'prompts' => [
                    '*' => [
                        'name',
                        'description',
                        'arguments',
                    ],
                ],
            ],
            'id',
        ]);

        $data = $response->json();
        $prompts = collect($data['result']['prompts']);
        $testPrompt = $prompts->firstWhere('name', 'test-prompt');

        $this->assertNotNull($testPrompt);
        $this->assertEquals('A mock prompt for testing purposes', $testPrompt['description']);
    }

    public function test_can_call_tool(): void
    {
        $response = $this->postJson('/mcp/test/tools/call', [
            'params' => [
                'name' => 'test-tool',
                'arguments' => [
                    'input' => 'test value',
                    'return_text' => true,
                ],
            ],
            'id' => 'test-123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result',
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test-123', $data['id']);
        $this->assertArrayHasKey('content', $data['result']);
    }

    public function test_can_read_resource(): void
    {
        $response = $this->postJson('/mcp/test/resources/read', [
            'params' => [
                'uri' => 'test://resource',
            ],
            'id' => 'test-456',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result',
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test-456', $data['id']);
        $this->assertArrayHasKey('contents', $data['result']);
    }

    public function test_can_get_prompt(): void
    {
        $response = $this->postJson('/mcp/test/prompts/get', [
            'params' => [
                'name' => 'test-prompt',
                'arguments' => [
                    'topic' => 'testing',
                    'style' => 'formal',
                ],
            ],
            'id' => 'test-789',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result',
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test-789', $data['id']);
        $this->assertArrayHasKey('messages', $data['result']);
    }

    public function test_can_ping_server(): void
    {
        $response = $this->getJson('/mcp/test/ping');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'pong',
            ],
            'id',
        ]);

        $data = $response->json();
        $this->assertTrue($data['result']['pong']);
    }

    public function test_can_get_server_status(): void
    {
        $response = $this->getJson('/mcp/test/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'name',
                'running',
                'configuration',
            ],
            'id',
        ]);
    }

    public function test_returns_404_for_nonexistent_server(): void
    {
        $response = $this->getJson('/mcp/nonexistent/capabilities');

        $response->assertNotFound();
        $response->assertJsonStructure([
            'jsonrpc',
            'error' => [
                'code',
                'message',
            ],
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('Server not found', $data['error']['message']);
    }

    public function test_returns_400_for_missing_tool_name(): void
    {
        $response = $this->postJson('/mcp/test/tools/call', [
            'params' => [
                'arguments' => ['input' => 'test'],
            ],
        ]);

        $response->assertBadRequest();
        $response->assertJsonStructure([
            'jsonrpc',
            'error' => [
                'code',
                'message',
            ],
            'id',
        ]);

        $data = $response->json();
        $this->assertEquals('Tool name is required', $data['error']['message']);
    }

    public function test_returns_404_for_nonexistent_tool(): void
    {
        $response = $this->postJson('/mcp/test/tools/call', [
            'params' => [
                'name' => 'nonexistent-tool',
                'arguments' => ['input' => 'test'],
            ],
        ]);

        $response->assertNotFound();
        $data = $response->json();
        $this->assertStringContainsString('not found', $data['error']['message']);
    }

    public function test_can_handle_json_rpc_requests(): void
    {
        $response = $this->postJson('/mcp/test', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 'rpc-123',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('rpc-123', $data['id']);
        $this->assertArrayHasKey('tools', $data['result']);
    }

    public function test_can_handle_initialization_request(): void
    {
        $response = $this->postJson('/mcp/test', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'clientInfo' => [
                    'name' => 'Test Client',
                    'version' => '1.0.0',
                ],
                'capabilities' => [
                    'roots' => ['listChanged' => true],
                ],
            ],
            'id' => 'init-123',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('init-123', $data['id']);
        $this->assertEquals('2025-06-18', $data['result']['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $data['result']);
        $this->assertArrayHasKey('serverInfo', $data['result']);
    }

    public function test_returns_405_for_unsupported_method(): void
    {
        $response = $this->postJson('/mcp/test', [
            'jsonrpc' => '2.0',
            'method' => 'unsupported/method',
            'id' => 'unsupported-123',
        ]);

        $response->assertStatus(405);
        $data = $response->json();

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('unsupported-123', $data['id']);
        $this->assertStringContainsString('not supported', $data['error']['message']);
    }
}
