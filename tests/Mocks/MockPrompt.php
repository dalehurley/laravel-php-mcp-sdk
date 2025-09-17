<?php

namespace MCP\Laravel\Tests\Mocks;

use MCP\Laravel\Laravel\LaravelPrompt;

/**
 * Mock prompt for testing purposes.
 */
class MockPrompt extends LaravelPrompt
{
    protected string $promptName;
    protected bool $shouldFail;
    protected array $mockMessages;

    public function __construct(string $name = 'mock-prompt', bool $shouldFail = false, array $mockMessages = [])
    {
        $this->promptName = $name;
        $this->shouldFail = $shouldFail;
        $this->mockMessages = $mockMessages ?: [
            ['role' => 'system', 'content' => ['type' => 'text', 'text' => 'You are a helpful assistant.']],
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello, how can you help me?']],
        ];
    }

    public function name(): string
    {
        return $this->promptName;
    }

    public function description(): string
    {
        return 'A mock prompt for testing purposes';
    }

    protected function argumentSchema(): array
    {
        return [
            'topic' => [
                'type' => 'string',
                'description' => 'The topic to generate a prompt for',
            ],
            'style' => [
                'type' => 'string',
                'enum' => ['formal', 'casual', 'technical'],
                'description' => 'The style of the prompt',
                'default' => 'casual',
            ],
        ];
    }

    public function handle(array $args): array
    {
        if ($this->shouldFail) {
            return $this->errorResponse('Mock prompt failure');
        }

        $topic = $args['topic'] ?? 'general assistance';
        $style = $args['style'] ?? 'casual';

        $messages = $this->mockMessages;

        // Customize messages based on arguments
        if (count($messages) > 1) {
            $messages[1]['content']['text'] = $this->generateUserMessage($topic, $style);
        }

        return $this->createPromptResponse($messages, "Mock prompt for {$topic} in {$style} style");
    }

    public function requiresAuth(): bool
    {
        return $this->promptName === 'secure-prompt';
    }

    public function requiredScopes(): array
    {
        return $this->requiresAuth() ? ['test:prompt'] : [];
    }

    protected function validationRules(): array
    {
        return [
            'topic' => 'nullable|string|max:255',
            'style' => 'nullable|in:formal,casual,technical',
        ];
    }

    private function generateUserMessage(string $topic, string $style): string
    {
        $templates = [
            'formal' => "Please provide assistance regarding {$topic}.",
            'casual' => "Hey, can you help me with {$topic}?",
            'technical' => "I need technical guidance on {$topic}.",
        ];

        return $templates[$style] ?? $templates['casual'];
    }
}
