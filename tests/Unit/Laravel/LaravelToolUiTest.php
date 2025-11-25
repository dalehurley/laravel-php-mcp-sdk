<?php

namespace MCP\Laravel\Tests\Unit\Laravel;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Laravel\LaravelTool;

/**
 * Test class that exposes protected UI methods for testing.
 */
class TestUiTool extends LaravelTool
{
    public function name(): string
    {
        return 'test-ui-tool';
    }

    public function description(): string
    {
        return 'Test tool for UI helpers';
    }

    public function handle(array $params): array
    {
        return $this->textContent('Test result');
    }

    // Expose protected methods for testing
    public function testUiCard(array $options): array
    {
        return $this->uiCard($options);
    }

    public function testUiTable(string $title, array $headers, array $rows, array $options = []): array
    {
        return $this->uiTable($title, $headers, $rows, $options);
    }

    public function testUiStats(array $stats, array $options = []): array
    {
        return $this->uiStats($stats, $options);
    }

    public function testUiForm(array $fields, array $options = []): array
    {
        return $this->uiForm($fields, $options);
    }

    public function testUiHtml(string $uri, string $html): array
    {
        return $this->uiHtml($uri, $html);
    }

    public function testUiUrl(string $uri, string $url): array
    {
        return $this->uiUrl($uri, $url);
    }

    public function testUiRemoteDom(string $uri, string $url): array
    {
        return $this->uiRemoteDom($uri, $url);
    }

    public function testWithUi(string $text, array ...$uiResources): array
    {
        return $this->withUi($text, ...$uiResources);
    }

    public function testUiActionScript(string $action = 'tool', array $defaultData = []): string
    {
        return $this->uiActionScript($action, $defaultData);
    }
}

class LaravelToolUiTest extends TestCase
{
    protected TestUiTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new TestUiTool();
    }

    public function test_ui_card_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiCard([
            'title' => 'Test Card',
            'content' => 'Card content here',
            'footer' => 'Footer text',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertStringStartsWith('ui://', $result['resource']['uri']);
        $this->assertEquals('text/html', $result['resource']['mimeType']);
        $this->assertStringContainsString('Test Card', $result['resource']['text']);
        $this->assertStringContainsString('Card content here', $result['resource']['text']);
    }

    public function test_ui_table_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiTable(
            'Users',
            ['Name', 'Email', 'Role'],
            [
                ['John Doe', 'john@example.com', 'Admin'],
                ['Jane Smith', 'jane@example.com', 'User'],
            ]
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertStringStartsWith('ui://', $result['resource']['uri']);
        $this->assertStringContainsString('Users', $result['resource']['text']);
        $this->assertStringContainsString('John Doe', $result['resource']['text']);
        $this->assertStringContainsString('jane@example.com', $result['resource']['text']);
    }

    public function test_ui_stats_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiStats([
            ['label' => 'Users', 'value' => 1234, 'change' => '+5%'],
            ['label' => 'Revenue', 'value' => '$9,876', 'change' => '+12%'],
            ['label' => 'Orders', 'value' => 567],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertStringStartsWith('ui://', $result['resource']['uri']);
        $this->assertStringContainsString('Users', $result['resource']['text']);
        $this->assertStringContainsString('1234', $result['resource']['text']);
        $this->assertStringContainsString('Revenue', $result['resource']['text']);
    }

    public function test_ui_form_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiForm([
            ['name' => 'email', 'type' => 'email', 'label' => 'Email Address'],
            ['name' => 'password', 'type' => 'password', 'label' => 'Password'],
        ], [
            'title' => 'Login Form',
            'submitLabel' => 'Sign In',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertStringStartsWith('ui://', $result['resource']['uri']);
        $this->assertStringContainsString('Login Form', $result['resource']['text']);
        $this->assertStringContainsString('Email Address', $result['resource']['text']);
        $this->assertStringContainsString('Sign In', $result['resource']['text']);
    }

    public function test_ui_html_creates_valid_ui_resource(): void
    {
        $html = '<div class="custom"><h1>Hello World</h1></div>';
        $result = $this->tool->testUiHtml('ui://custom/hello', $html);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertEquals('ui://custom/hello', $result['resource']['uri']);
        $this->assertEquals('text/html', $result['resource']['mimeType']);
        $this->assertStringContainsString('Hello World', $result['resource']['text']);
    }

    public function test_ui_url_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiUrl(
            'ui://external/widget',
            'https://example.com/widget.html'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertEquals('ui://external/widget', $result['resource']['uri']);
        $this->assertEquals('text/uri-list', $result['resource']['mimeType']);
        $this->assertEquals('https://example.com/widget.html', $result['resource']['text']);
    }

    public function test_ui_remote_dom_creates_valid_ui_resource(): void
    {
        $result = $this->tool->testUiRemoteDom(
            'ui://dynamic/content',
            'https://api.example.com/content'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('resource', $result['type']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertEquals('ui://dynamic/content', $result['resource']['uri']);
        $this->assertEquals('text/x-remote-dom', $result['resource']['mimeType']);
    }

    public function test_with_ui_combines_text_and_ui_resources(): void
    {
        $card = $this->tool->testUiCard(['title' => 'Card', 'content' => 'Content']);
        $table = $this->tool->testUiTable('Table', ['Col'], [['Value']]);

        $result = $this->tool->testWithUi('Here are your results:', $card, $table);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertCount(3, $result['content']);

        // First should be text
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertEquals('Here are your results:', $result['content'][0]['text']);

        // Second and third should be UI resources
        $this->assertEquals('resource', $result['content'][1]['type']);
        $this->assertEquals('resource', $result['content'][2]['type']);
    }

    public function test_ui_action_script_returns_javascript(): void
    {
        $script = $this->tool->testUiActionScript('refresh', ['widget' => 'weather']);

        $this->assertIsString($script);
        $this->assertStringContainsString('<script>', $script);
        $this->assertStringContainsString('postMessage', $script);
        $this->assertStringContainsString('refresh', $script);
    }

    public function test_ui_card_with_actions(): void
    {
        $result = $this->tool->testUiCard([
            'title' => 'Interactive Card',
            'content' => 'Click a button',
            'actions' => [
                ['label' => 'Refresh', 'action' => 'refresh'],
                ['label' => 'Delete', 'action' => 'delete', 'data' => ['confirm' => true]],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertStringContainsString('Refresh', $result['resource']['text']);
        $this->assertStringContainsString('Delete', $result['resource']['text']);
    }

    public function test_ui_table_with_row_actions(): void
    {
        $result = $this->tool->testUiTable(
            'Users',
            ['Name', 'Email', 'Actions'],
            [
                ['John', 'john@test.com', '<button>Edit</button>'],
            ],
            [
                'actions' => [
                    ['label' => 'Export CSV', 'action' => 'export'],
                ],
            ]
        );

        $this->assertIsArray($result);
        $this->assertStringContainsString('Users', $result['resource']['text']);
        $this->assertStringContainsString('Export CSV', $result['resource']['text']);
    }
}

