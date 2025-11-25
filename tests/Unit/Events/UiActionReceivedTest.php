<?php

namespace MCP\Laravel\Tests\Unit\Events;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Events\UiActionReceived;

class UiActionReceivedTest extends TestCase
{
    public function test_tool_action_properties(): void
    {
        $event = new UiActionReceived(
            type: 'tool',
            payload: [
                'name' => 'test-tool',
                'arguments' => ['foo' => 'bar'],
            ],
            serverName: 'main',
            userId: '123',
            widgetUri: 'ui://widget/1'
        );

        $this->assertTrue($event->isTool());
        $this->assertFalse($event->isNotification());
        $this->assertFalse($event->isPrompt());
        $this->assertFalse($event->isLink());

        $this->assertEquals('test-tool', $event->getToolName());
        $this->assertEquals(['foo' => 'bar'], $event->getToolArguments());
        $this->assertEquals('main', $event->serverName);
        $this->assertEquals('123', $event->userId);
        $this->assertEquals('ui://widget/1', $event->widgetUri);
    }

    public function test_notification_action_properties(): void
    {
        $event = new UiActionReceived(
            type: 'notification',
            payload: [
                'message' => 'Test notification',
                'level' => 'warning',
            ]
        );

        $this->assertTrue($event->isNotification());
        $this->assertFalse($event->isTool());

        $this->assertEquals('Test notification', $event->getNotificationMessage());
        $this->assertEquals('warning', $event->getNotificationLevel());
    }

    public function test_notification_default_level(): void
    {
        $event = new UiActionReceived(
            type: 'notification',
            payload: [
                'message' => 'Test',
            ]
        );

        $this->assertEquals('info', $event->getNotificationLevel());
    }

    public function test_prompt_action_properties(): void
    {
        $event = new UiActionReceived(
            type: 'prompt',
            payload: [
                'name' => 'summarize',
                'arguments' => ['text' => 'Hello world'],
            ]
        );

        $this->assertTrue($event->isPrompt());
        $this->assertFalse($event->isTool());

        $this->assertEquals('summarize', $event->getPromptName());
        $this->assertEquals(['text' => 'Hello world'], $event->getPromptArguments());
    }

    public function test_link_action_properties(): void
    {
        $event = new UiActionReceived(
            type: 'link',
            payload: [
                'url' => 'https://example.com',
            ]
        );

        $this->assertTrue($event->isLink());
        $this->assertFalse($event->isTool());

        $this->assertEquals('https://example.com', $event->getLinkUrl());
    }

    public function test_null_getters_return_null_for_missing_values(): void
    {
        $event = new UiActionReceived(
            type: 'tool',
            payload: []
        );

        $this->assertNull($event->getToolName());
        $this->assertNull($event->getNotificationMessage());
        $this->assertNull($event->getLinkUrl());
        $this->assertNull($event->getPromptName());
    }

    public function test_empty_array_getters_return_empty_arrays(): void
    {
        $event = new UiActionReceived(
            type: 'tool',
            payload: []
        );

        $this->assertEquals([], $event->getToolArguments());
        $this->assertEquals([], $event->getPromptArguments());
    }

    public function test_nullable_properties(): void
    {
        $event = new UiActionReceived(
            type: 'notification',
            payload: ['message' => 'Test']
        );

        $this->assertNull($event->serverName);
        $this->assertNull($event->userId);
        $this->assertNull($event->widgetUri);
    }
}

