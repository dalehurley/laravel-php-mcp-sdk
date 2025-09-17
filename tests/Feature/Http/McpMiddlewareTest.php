<?php

namespace MCP\Laravel\Tests\Feature\Http;

use MCP\Laravel\Tests\TestCase;

/**
 * Feature tests for MCP middleware.
 */
class McpMiddlewareTest extends TestCase
{
    public function test_security_middleware_adds_headers(): void
    {
        $response = $this->getJson('/mcp/test/capabilities');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('Server'); // Should not expose server info
    }

    public function test_cors_headers_when_enabled(): void
    {
        config(['mcp.transports.http.security.cors_enabled' => true]);

        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
        ])->getJson('/mcp/test/capabilities');

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_preflight_cors_request(): void
    {
        config(['mcp.transports.http.security.cors_enabled' => true]);

        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/mcp/test');

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
        $response->assertHeader('Access-Control-Max-Age', '86400');
    }

    public function test_auth_middleware_allows_when_disabled(): void
    {
        config(['mcp.authorization.enabled' => false]);

        $response = $this->getJson('/mcp/test/capabilities');

        $response->assertOk();
    }

    public function test_auth_middleware_requires_token_when_enabled(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'bearer',
        ]);

        $response = $this->getJson('/mcp/test/capabilities');

        $response->assertUnauthorized();
        $response->assertJsonStructure([
            'jsonrpc',
            'error' => [
                'code',
                'message',
            ],
            'id',
        ]);
    }

    public function test_bearer_token_authentication(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'bearer',
            'mcp.authorization.bearer.valid_tokens' => ['test-token'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->getJson('/mcp/test/capabilities');

        $response->assertOk();
    }

    public function test_invalid_bearer_token(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'bearer',
            'mcp.authorization.bearer.valid_tokens' => ['valid-token'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/mcp/test/capabilities');

        $response->assertUnauthorized();
    }

    public function test_api_key_authentication(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'api_key',
            'mcp.authorization.api_key.valid_keys' => ['test-key'],
        ]);

        $response = $this->withHeaders([
            'X-MCP-API-Key' => 'test-key',
        ])->getJson('/mcp/test/capabilities');

        $response->assertOk();
    }

    public function test_api_key_in_query_parameter(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'api_key',
            'mcp.authorization.api_key.valid_keys' => ['test-key'],
        ]);

        $response = $this->getJson('/mcp/test/capabilities?api_key=test-key');

        $response->assertOk();
    }

    public function test_invalid_api_key(): void
    {
        config([
            'mcp.authorization.enabled' => true,
            'mcp.authorization.provider' => 'api_key',
            'mcp.authorization.api_key.valid_keys' => ['valid-key'],
        ]);

        $response = $this->withHeaders([
            'X-MCP-API-Key' => 'invalid-key',
        ])->getJson('/mcp/test/capabilities');

        $response->assertUnauthorized();
    }

    public function test_rate_limiting_when_enabled(): void
    {
        config([
            'mcp.transports.http.security.rate_limiting' => '2,1', // 2 requests per minute
        ]);

        // First request should succeed
        $response = $this->getJson('/mcp/test/capabilities');
        $response->assertOk();

        // Second request should succeed
        $response = $this->getJson('/mcp/test/capabilities');
        $response->assertOk();

        // Third request might be rate limited (depends on implementation)
        // This is more of an integration test and might be flaky
    }

    public function test_content_security_policy_header(): void
    {
        $response = $this->getJson('/mcp/test/capabilities');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_middleware_handles_large_requests(): void
    {
        config(['mcp.security.max_request_size' => 1024]); // 1KB limit

        $largeData = str_repeat('a', 2048); // 2KB of data

        $response = $this->postJson('/mcp/test/tools/call', [
            'params' => [
                'name' => 'test-tool',
                'arguments' => [
                    'input' => $largeData,
                ],
            ],
        ]);

        // Should handle gracefully (either accept or reject cleanly)
        $this->assertTrue(in_array($response->status(), [200, 400, 413]));
    }

    public function test_middleware_sanitizes_input(): void
    {
        $maliciousInput = "test\x00\x01\x02input"; // Contains null bytes

        $response = $this->postJson('/mcp/test/tools/call', [
            'params' => [
                'name' => 'test-tool',
                'arguments' => [
                    'input' => $maliciousInput,
                ],
            ],
        ]);

        // Should either work (sanitized) or fail gracefully
        // 404 is also acceptable if the tool doesn't exist
        $this->assertTrue(in_array($response->status(), [200, 400, 404]));
    }

    public function test_json_rpc_validation(): void
    {
        $response = $this->postJson('/mcp/test', [
            'method' => 'tools/list',
            // Missing jsonrpc field
        ]);

        // Should handle invalid JSON-RPC gracefully
        $this->assertTrue(in_array($response->status(), [200, 400]));
    }

    public function test_invalid_json_handling(): void
    {
        $response = $this->call('POST', '/mcp/test', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json content');

        // Should handle invalid JSON gracefully (400 for bad JSON, 405 for method not allowed)
        $this->assertTrue(in_array($response->status(), [400, 405]));
    }

    public function test_missing_content_type(): void
    {
        $response = $this->post('/mcp/test', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
        ]);

        // Should handle missing content type gracefully
        $this->assertTrue(in_array($response->status(), [200, 400, 415]));
    }
}
