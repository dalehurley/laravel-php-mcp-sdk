<?php

namespace MCP\Laravel\Tests\Unit;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Laravel\LaravelMcpClient;
use MCP\Laravel\Exceptions\McpException;
use Amp\Future\UnhandledFutureError;

/**
 * Test the UnhandledFutureError fix for connection closed scenarios.
 */
class ConnectionErrorHandlingTest extends TestCase
{
    public function test_safe_await_method_exists_and_handles_errors(): void
    {
        // Create a minimal client for testing
        $container = $this->createMock(\Illuminate\Contracts\Container\Container::class);
        $config = ['transport' => 'http'];

        // Use partial mock to avoid constructor dependencies
        $client = $this->getMockBuilder(LaravelMcpClient::class)
            ->setConstructorArgs([$container, 'test', $config])
            ->onlyMethods(['initializeClient'])
            ->getMock();

        // Use reflection to verify the safeAwait method exists
        $reflection = new \ReflectionClass($client);
        $this->assertTrue($reflection->hasMethod('safeAwait'));

        $method = $reflection->getMethod('safeAwait');
        $this->assertTrue($method->isProtected());

        // Verify the method signature expects a Future parameter
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('future', $parameters[0]->getName());

        // Verify return type is mixed
        $returnType = $method->getReturnType();
        $this->assertEquals('mixed', (string) $returnType);
    }

    public function test_handle_exception_updates_connection_status(): void
    {
        $container = $this->createMock(\Illuminate\Contracts\Container\Container::class);
        $config = ['transport' => 'http'];

        $client = $this->getMockBuilder(LaravelMcpClient::class)
            ->setConstructorArgs([$container, 'test', $config])
            ->onlyMethods(['initializeClient'])
            ->getMock();

        // Set client as connected using reflection
        $reflection = new \ReflectionClass($client);
        $connectedProperty = $reflection->getProperty('connected');
        $connectedProperty->setAccessible(true);
        $connectedProperty->setValue($client, true);

        // Verify initially connected
        $this->assertTrue($client->isConnected());

        // Test handleException method
        $method = $reflection->getMethod('handleException');
        $method->setAccessible(true);

        $exception = new \Exception('MCP error -32000: Connection closed');

        try {
            $method->invoke($client, $exception, 'test operation');
            $this->fail('Expected McpException');
        } catch (McpException $e) {
            // Should be disconnected after connection error
            $this->assertFalse($client->isConnected());
            $this->assertStringContainsString('Failed to test operation', $e->getMessage());
        }
    }

    public function test_handle_exception_preserves_connection_for_non_connection_errors(): void
    {
        $container = $this->createMock(\Illuminate\Contracts\Container\Container::class);
        $config = ['transport' => 'http'];

        $client = $this->getMockBuilder(LaravelMcpClient::class)
            ->setConstructorArgs([$container, 'test', $config])
            ->onlyMethods(['initializeClient'])
            ->getMock();

        // Set client as connected using reflection
        $reflection = new \ReflectionClass($client);
        $connectedProperty = $reflection->getProperty('connected');
        $connectedProperty->setAccessible(true);
        $connectedProperty->setValue($client, true);

        // Test handleException method with non-connection error
        $method = $reflection->getMethod('handleException');
        $method->setAccessible(true);

        $exception = new \Exception('Invalid parameters');

        try {
            $method->invoke($client, $exception, 'call tool');
            $this->fail('Expected McpException');
        } catch (McpException $e) {
            // Should still be connected for non-connection errors
            $this->assertTrue($client->isConnected());
            $this->assertStringContainsString('Failed to call tool', $e->getMessage());
            $this->assertStringContainsString('Invalid parameters', $e->getMessage());
        }
    }

    public function test_connection_closed_error_detection(): void
    {
        $container = $this->createMock(\Illuminate\Contracts\Container\Container::class);
        $config = ['transport' => 'http'];

        $client = $this->getMockBuilder(LaravelMcpClient::class)
            ->setConstructorArgs([$container, 'test', $config])
            ->onlyMethods(['initializeClient'])
            ->getMock();

        // Set client as connected
        $reflection = new \ReflectionClass($client);
        $connectedProperty = $reflection->getProperty('connected');
        $connectedProperty->setAccessible(true);
        $connectedProperty->setValue($client, true);

        $method = $reflection->getMethod('handleException');
        $method->setAccessible(true);

        // Test various connection closed error messages
        $connectionErrors = [
            'MCP error -32000: Connection closed',
            'Connection closed unexpectedly',
            'Network connection lost',
        ];

        foreach ($connectionErrors as $errorMessage) {
            // Reset connection status
            $connectedProperty->setValue($client, true);

            $exception = new \Exception($errorMessage);

            try {
                $method->invoke($client, $exception, 'test');
                $this->fail("Expected McpException for error: {$errorMessage}");
            } catch (McpException $e) {
                if (str_contains($errorMessage, 'Connection closed') || str_contains($errorMessage, 'MCP error -32000')) {
                    // Should be disconnected for connection errors
                    $this->assertFalse($client->isConnected(), "Client should be disconnected for: {$errorMessage}");
                } else {
                    // Should remain connected for other errors
                    $this->assertTrue($client->isConnected(), "Client should remain connected for: {$errorMessage}");
                }
            }
        }
    }
}
