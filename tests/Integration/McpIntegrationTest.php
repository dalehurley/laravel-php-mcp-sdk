<?php

namespace MCP\Laravel\Tests\Integration;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Tests\Mocks\MockTool;
use MCP\Laravel\Tests\Mocks\MockResource;
use MCP\Laravel\Tests\Mocks\MockPrompt;
use MCP\Laravel\Facades\Mcp;
use MCP\Laravel\Facades\McpServer;
use MCP\Laravel\Facades\McpClient;

/**
 * Integration tests for the complete MCP system.
 */
class McpIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test components
        $this->setupTestComponents();
    }

    protected function setupTestComponents(): void
    {
        // Create and register test components
        $tool = new MockTool('integration-tool');
        $resource = new MockResource('integration://resource');
        $prompt = new MockPrompt('integration-prompt');

        McpServer::addTool('test', 'integration-tool', [$tool, 'handle'], [
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
        ]);

        McpServer::addResource('test', 'integration://resource', [$resource, 'read'], [
            'description' => $resource->description(),
        ]);

        McpServer::addPrompt('test', 'integration-prompt', [$prompt, 'handle'], [
            'description' => $prompt->description(),
            'arguments' => $prompt->arguments(),
        ]);
    }

    public function test_complete_server_lifecycle(): void
    {
        // Test server creation and status
        $server = McpServer::get('test');
        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpServer::class, $server);

        // Test server status
        $status = McpServer::getStatus('test');
        $this->assertIsArray($status);
        $this->assertArrayHasKey('name', $status);
        $this->assertEquals('test', $status['name']);

        // Test server capabilities
        $capabilities = McpServer::getCapabilities('test');
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('experimental', $capabilities);

        // Test component listings
        $tools = McpServer::getTools('test');
        $this->assertArrayHasKey('integration-tool', $tools);

        $resources = McpServer::getResources('test');
        $this->assertArrayHasKey('integration://resource', $resources);

        $prompts = McpServer::getPrompts('test');
        $this->assertArrayHasKey('integration-prompt', $prompts);
    }

    public function test_complete_client_lifecycle(): void
    {
        // Test client creation
        $client = McpClient::get('test');
        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpClient::class, $client);

        // Test client status
        $status = McpClient::getStatus('test');
        $this->assertIsArray($status);
        $this->assertArrayHasKey('name', $status);
        $this->assertEquals('test', $status['name']);

        // Test client capabilities
        $capabilities = McpClient::getCapabilities('test');
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('experimental', $capabilities);
    }

    public function test_tool_execution_flow(): void
    {
        // Get the tool directly and test execution
        $tools = McpServer::getTools('test');
        $tool = $tools['integration-tool'];

        $this->assertArrayHasKey('handler', $tool);
        $this->assertIsCallable($tool['handler']);

        // Execute the tool
        $result = call_user_func($tool['handler'], [
            'input' => 'integration test',
            'return_text' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertStringContainsString('integration test', $result['content'][0]['text']);
    }

    public function test_resource_reading_flow(): void
    {
        // Get the resource directly and test reading
        $resources = McpServer::getResources('test');
        $resource = $resources['integration://resource'];

        $this->assertArrayHasKey('handler', $resource);
        $this->assertIsCallable($resource['handler']);

        // Read the resource
        $result = call_user_func($resource['handler'], 'integration://resource');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('contents', $result);
        $this->assertIsArray($result['contents']);
        $this->assertNotEmpty($result['contents']);
    }

    public function test_prompt_generation_flow(): void
    {
        // Get the prompt directly and test generation
        $prompts = McpServer::getPrompts('test');
        $prompt = $prompts['integration-prompt'];

        $this->assertArrayHasKey('handler', $prompt);
        $this->assertIsCallable($prompt['handler']);

        // Generate the prompt
        $result = call_user_func($prompt['handler'], [
            'topic' => 'integration testing',
            'style' => 'technical',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
        $this->assertNotEmpty($result['messages']);
    }

    public function test_system_status_integration(): void
    {
        $systemStatus = Mcp::getSystemStatus();

        $this->assertIsArray($systemStatus);
        $this->assertArrayHasKey('servers', $systemStatus);
        $this->assertArrayHasKey('clients', $systemStatus);
        $this->assertArrayHasKey('configuration', $systemStatus);

        // Test server information
        $servers = $systemStatus['servers'];
        $this->assertArrayHasKey('test', $servers);

        $testServer = $servers['test'];
        $this->assertEquals('Test Server', $testServer['display_name']);
        $this->assertEquals('stdio', $testServer['transport']);

        // Test client information
        $clients = $systemStatus['clients'];
        $this->assertArrayHasKey('test', $clients);

        $testClient = $clients['test'];
        $this->assertEquals('Test Client', $testClient['display_name']);
    }

    public function test_health_check_integration(): void
    {
        $health = Mcp::healthCheck();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);

        $this->assertContains($health['status'], ['healthy', 'degraded', 'unhealthy']);
        $this->assertIsArray($health['checks']);
    }

    public function test_multiple_servers_integration(): void
    {
        // Create additional server configuration
        config([
            'mcp.servers.secondary' => [
                'name' => 'Secondary Server',
                'version' => '1.0.0',
                'transport' => 'http',
                'capabilities' => [
                    'experimental' => [],
                    'tools' => [],
                ],
            ],
        ]);

        // Test that we can get multiple servers
        $primaryServer = McpServer::get('test');
        $secondaryServer = McpServer::get('secondary');

        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpServer::class, $primaryServer);
        $this->assertInstanceOf(\MCP\Laravel\Laravel\LaravelMcpServer::class, $secondaryServer);

        // Test that they have different configurations
        $primaryStatus = McpServer::getStatus('test');
        $secondaryStatus = McpServer::getStatus('secondary');

        $this->assertEquals('stdio', $primaryStatus['configuration']['transport']);
        $this->assertEquals('http', $secondaryStatus['configuration']['transport']);
    }

    public function test_component_registration_integration(): void
    {
        // Test batch registration
        $components = [
            'tools' => [
                'batch-tool-1' => [
                    'handler' => fn($params) => ['result' => 'batch-1'],
                    'schema' => ['description' => 'Batch tool 1'],
                ],
                'batch-tool-2' => [
                    'handler' => fn($params) => ['result' => 'batch-2'],
                    'schema' => ['description' => 'Batch tool 2'],
                ],
            ],
            'resources' => [
                'batch://resource-1' => [
                    'handler' => fn($uri) => ['content' => 'batch resource 1'],
                    'metadata' => ['description' => 'Batch resource 1'],
                ],
            ],
            'prompts' => [
                'batch-prompt-1' => [
                    'handler' => fn($args) => ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'batch prompt']]]],
                    'schema' => ['description' => 'Batch prompt 1'],
                ],
            ],
        ];

        McpServer::registerBatch('test', $components);

        // Verify all components were registered
        $tools = McpServer::getTools('test');
        $resources = McpServer::getResources('test');
        $prompts = McpServer::getPrompts('test');

        $this->assertArrayHasKey('batch-tool-1', $tools);
        $this->assertArrayHasKey('batch-tool-2', $tools);
        $this->assertArrayHasKey('batch://resource-1', $resources);
        $this->assertArrayHasKey('batch-prompt-1', $prompts);
    }

    public function test_error_handling_integration(): void
    {
        // Test with failing components
        $failingTool = new MockTool('failing-tool', true);
        $failingResource = new MockResource('failing://resource', true);
        $failingPrompt = new MockPrompt('failing-prompt', true);

        McpServer::addTool('test', 'failing-tool', [$failingTool, 'handle'], []);
        McpServer::addResource('test', 'failing://resource', [$failingResource, 'read'], []);
        McpServer::addPrompt('test', 'failing-prompt', [$failingPrompt, 'handle'], []);

        // Test that errors are handled gracefully
        $tools = McpServer::getTools('test');
        $this->assertArrayHasKey('failing-tool', $tools);

        // Execute failing tool
        $result = call_user_func($tools['failing-tool']['handler'], ['input' => 'test']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Mock tool failure', $result['error']['message']);
    }

    public function test_configuration_integration(): void
    {
        // Test that configuration is properly loaded and accessible
        $this->assertEquals('test', config('mcp.default_server'));
        $this->assertEquals('test', config('mcp.default_client'));

        $serverConfig = config('mcp.servers.test');
        $this->assertIsArray($serverConfig);
        $this->assertEquals('Test Server', $serverConfig['name']);
        $this->assertEquals('stdio', $serverConfig['transport']);

        $clientConfig = config('mcp.clients.test');
        $this->assertIsArray($clientConfig);
        $this->assertEquals('Test Client', $clientConfig['name']);
    }

    public function test_facade_integration(): void
    {
        // Test that all facades are working
        $this->assertInstanceOf(\MCP\Laravel\Laravel\McpManager::class, Mcp::getFacadeRoot());
        $this->assertInstanceOf(\MCP\Laravel\Laravel\ServerManager::class, McpServer::getFacadeRoot());
        $this->assertInstanceOf(\MCP\Laravel\Laravel\ClientManager::class, McpClient::getFacadeRoot());

        // Test facade method calls
        $servers = Mcp::listServers();
        $this->assertIsArray($servers);
        $this->assertArrayHasKey('test', $servers);

        $clients = Mcp::listClients();
        $this->assertIsArray($clients);
        $this->assertArrayHasKey('test', $clients);
    }
}
