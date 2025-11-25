<?php

namespace MCP\Laravel\Tests\Feature\Http;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Events\UiActionReceived;
use MCP\Laravel\Laravel\ServerManager;
use Illuminate\Support\Facades\Event;

class McpUiActionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure UI is enabled
        config(['mcp.ui.enabled' => true]);

        // Set up HTTP transport for test server
        config([
            'mcp.servers.test' => array_merge(
                config('mcp.servers.test'),
                ['transport' => 'http']
            ),
        ]);
    }

    public function test_ui_action_endpoint_requires_type(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'payload' => ['test' => 'data'],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_ui_action_endpoint_requires_payload(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'tool',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_ui_action_endpoint_validates_type(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'invalid',
            'payload' => ['test' => 'data'],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_notification_action_dispatches_event(): void
    {
        Event::fake([UiActionReceived::class]);

        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'notification',
            'payload' => [
                'message' => 'Test notification',
                'level' => 'info',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('type', 'notification');

        Event::assertDispatched(UiActionReceived::class, function ($event) {
            return $event->type === 'notification'
                && $event->payload['message'] === 'Test notification'
                && $event->isNotification();
        });
    }

    public function test_link_action_returns_url(): void
    {
        Event::fake([UiActionReceived::class]);

        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'link',
            'payload' => [
                'url' => 'https://example.com/test',
                'target' => '_blank',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('type', 'link');
        $response->assertJsonPath('url', 'https://example.com/test');
        $response->assertJsonPath('target', '_blank');

        Event::assertDispatched(UiActionReceived::class, function ($event) {
            return $event->type === 'link'
                && $event->isLink()
                && $event->getLinkUrl() === 'https://example.com/test';
        });
    }

    public function test_link_action_requires_valid_url(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'link',
            'payload' => [
                'url' => 'not-a-valid-url',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_tool_action_requires_tool_name(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'tool',
            'payload' => [
                'arguments' => ['foo' => 'bar'],
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_tool_action_returns_not_found_for_unknown_server(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'tool',
            'payload' => [
                'name' => 'test-tool',
                'arguments' => [],
            ],
            'server' => 'nonexistent-server',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_notification_action_requires_message(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'notification',
            'payload' => [
                'level' => 'info',
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_prompt_action_requires_name(): void
    {
        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'prompt',
            'payload' => [
                'arguments' => [],
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_ui_action_includes_widget_uri_in_event(): void
    {
        Event::fake([UiActionReceived::class]);

        $response = $this->postJson('/mcp/ui-action', [
            'type' => 'notification',
            'payload' => [
                'message' => 'Test',
            ],
            'widget_uri' => 'ui://test-widget/123',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(UiActionReceived::class, function ($event) {
            return $event->widgetUri === 'ui://test-widget/123';
        });
    }
}

