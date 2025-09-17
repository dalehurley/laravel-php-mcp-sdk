<?php

namespace MCP\Laravel\Tests\Unit;

use MCP\Laravel\Laravel\LaravelMcpClient;
use MCP\Laravel\Tests\TestCase;
use MCP\Types\Capabilities\ClientCapabilities;
use Illuminate\Container\Container;

class LaravelMcpClientCapabilitiesTest extends TestCase
{
    private Container $mockApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApp = new class extends Container {
            public function make($abstract, array $parameters = [])
            {
                if ($abstract === 'log') {
                    return new class {
                        public function channel($channel)
                        {
                            return new class {
                                public function info($message, $context = []) {}
                                public function error($message, $context = []) {}
                            };
                        }
                    };
                }

                return parent::make($abstract, $parameters);
            }
        };
    }

    public function test_prepare_capability_converts_empty_array_to_null()
    {
        $config = [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'capabilities' => ['experimental' => [], 'sampling' => [], 'roots' => ['listChanged' => true]],
        ];

        $client = new LaravelMcpClient($this->mockApp, 'test', $config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('prepareCapability');
        $method->setAccessible(true);

        // Test empty array (the problematic case)
        $result = $method->invoke($client, []);
        $this->assertNull($result, 'Empty array should be converted to null');
    }

    public function test_prepare_capability_preserves_null()
    {
        $config = [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'capabilities' => [],
        ];

        $client = new LaravelMcpClient($this->mockApp, 'test', $config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('prepareCapability');
        $method->setAccessible(true);

        $result = $method->invoke($client, null);
        $this->assertNull($result, 'Null should remain null');
    }

    public function test_prepare_capability_converts_empty_object_to_null()
    {
        $config = [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'capabilities' => [],
        ];

        $client = new LaravelMcpClient($this->mockApp, 'test', $config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('prepareCapability');
        $method->setAccessible(true);

        $result = $method->invoke($client, (object)[]);
        $this->assertNull($result, 'Empty object should be converted to null');
    }

    public function test_prepare_capability_preserves_non_empty_array()
    {
        $config = [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'capabilities' => [],
        ];

        $client = new LaravelMcpClient($this->mockApp, 'test', $config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('prepareCapability');
        $method->setAccessible(true);

        $input = ['feature' => true];
        $result = $method->invoke($client, $input);
        $this->assertEquals($input, $result, 'Non-empty array should be preserved');
    }

    public function test_prepare_capability_converts_non_empty_object_to_array()
    {
        $config = [
            'name' => 'Test Client',
            'version' => '1.0.0',
            'capabilities' => [],
        ];

        $client = new LaravelMcpClient($this->mockApp, 'test', $config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('prepareCapability');
        $method->setAccessible(true);

        $input = (object)['feature' => true];
        $result = $method->invoke($client, $input);
        $this->assertEquals(['feature' => true], $result, 'Non-empty object should be converted to array');
    }

    public function test_client_initialization_with_empty_capabilities()
    {
        $config = [
            'name' => 'PGA MCP Client',
            'version' => '1.0.0',
            'capabilities' => [
                'experimental' => [],  // This was causing validation errors
                'sampling' => [],      // This was causing validation errors
                'roots' => ['listChanged' => true],
            ],
        ];

        // This should not throw any exceptions
        $client = new LaravelMcpClient($this->mockApp, 'pga', $config);

        $this->assertInstanceOf(LaravelMcpClient::class, $client);

        // Get the underlying MCP client to verify capabilities
        $mcpClient = $client->getClient();
        $this->assertNotNull($mcpClient);

        // The client should be properly initialized without errors
        $this->assertTrue(true, 'Client initialization successful with empty capabilities');
    }

    public function test_capabilities_serialization_omits_empty_values()
    {
        // Create capabilities with the fixed approach (null for empty)
        $capabilities = new ClientCapabilities(
            experimental: null,  // Fixed: null instead of empty array
            sampling: null,      // Fixed: null instead of empty array
            roots: ['listChanged' => true]
        );

        $serialized = $capabilities->jsonSerialize();

        // Verify that empty capabilities are omitted
        $this->assertArrayNotHasKey('experimental', $serialized, 'Empty experimental capability should be omitted');
        $this->assertArrayNotHasKey('sampling', $serialized, 'Empty sampling capability should be omitted');
        $this->assertArrayHasKey('roots', $serialized, 'Non-empty roots capability should be present');
        $this->assertEquals(['listChanged' => true], $serialized['roots']);
    }
}
