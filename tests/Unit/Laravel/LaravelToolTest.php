<?php

namespace MCP\Laravel\Tests\Unit\Laravel;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Tests\Mocks\MockTool;

/**
 * Test cases for LaravelTool base class.
 */
class LaravelToolTest extends TestCase
{
    protected MockTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new MockTool();
    }

    public function test_tool_has_required_methods(): void
    {
        $this->assertEquals('mock-tool', $this->tool->name());
        $this->assertEquals('A mock tool for testing purposes', $this->tool->description());
        $this->assertIsArray($this->tool->inputSchema());
    }

    public function test_input_schema_structure(): void
    {
        $schema = $this->tool->inputSchema();

        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        $this->assertArrayHasKey('input', $schema['properties']);
        $this->assertArrayHasKey('number', $schema['properties']);
        $this->assertContains('input', $schema['required']);
    }

    public function test_successful_tool_execution(): void
    {
        $params = ['input' => 'test value'];
        $result = $this->tool->handle($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertEquals($params, $result['params']);
    }

    public function test_tool_can_return_text_content(): void
    {
        $params = ['input' => 'test', 'return_text' => true];
        $result = $this->tool->handle($params);

        $this->assertMcpResponse($result);
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertStringContainsString('Mock tool executed', $result['content'][0]['text']);
    }

    public function test_tool_failure_handling(): void
    {
        $failingTool = new MockTool('failing-tool', true);
        $result = $failingTool->handle(['input' => 'test']);

        $this->assertMcpError($result);
        $this->assertEquals('Mock tool failure', $result['error']['message']);
    }

    public function test_auth_requirements(): void
    {
        $regularTool = new MockTool('regular-tool');
        $authTool = new MockTool('auth-required-tool');

        $this->assertFalse($regularTool->requiresAuth());
        $this->assertEmpty($regularTool->requiredScopes());

        $this->assertTrue($authTool->requiresAuth());
        $this->assertEquals(['test:tool'], $authTool->requiredScopes());
    }

    public function test_tool_metadata(): void
    {
        $metadata = $this->tool->getMetadata();

        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('inputSchema', $metadata);
        $this->assertArrayHasKey('requiresAuth', $metadata);
        $this->assertArrayHasKey('requiredScopes', $metadata);
        $this->assertArrayHasKey('class', $metadata);

        $this->assertEquals('mock-tool', $metadata['name']);
        $this->assertEquals(MockTool::class, $metadata['class']);
    }

    public function test_text_content_helper(): void
    {
        $tool = new class extends MockTool {
            public function testTextContent(string $text): array
            {
                return $this->textContent($text);
            }
        };

        $result = $tool->testTextContent('Hello, world!');

        $this->assertMcpResponse($result);
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertEquals('Hello, world!', $result['content'][0]['text']);
    }

    public function test_error_response_helper(): void
    {
        $tool = new class extends MockTool {
            public function testErrorResponse(string $message, int $code = -1): array
            {
                return $this->errorResponse($message, $code);
            }
        };

        $result = $tool->testErrorResponse('Test error', 500);

        $this->assertMcpError($result);
        $this->assertEquals('Test error', $result['error']['message']);
        $this->assertEquals(500, $result['error']['code']);
    }

    public function test_success_response_helper(): void
    {
        $tool = new class extends MockTool {
            public function testSuccessResponse(mixed $data = null): array
            {
                return $this->successResponse($data);
            }
        };

        // Test with no data
        $result = $tool->testSuccessResponse();
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        // Test with string data (should return text content)
        $result = $tool->testSuccessResponse('Success message');
        $this->assertMcpResponse($result);
        $this->assertEquals('Success message', $result['content'][0]['text']);

        // Test with array data
        $data = ['key' => 'value'];
        $result = $tool->testSuccessResponse($data);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals($data, $result['data']);
    }

    public function test_image_content_helper(): void
    {
        $tool = new class extends MockTool {
            public function testImageContent(string $data, string $mimeType): array
            {
                return $this->imageContent($data, $mimeType);
            }
        };

        $result = $tool->testImageContent('base64data', 'image/png');

        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('image', $result['content'][0]['type']);
        $this->assertEquals('base64data', $result['content'][0]['data']);
        $this->assertEquals('image/png', $result['content'][0]['mimeType']);
    }
}
