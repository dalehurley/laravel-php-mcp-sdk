<?php

namespace MCP\Laravel\Tests\Mocks;

use MCP\Laravel\Laravel\LaravelTool;

/**
 * Mock tool for testing purposes.
 */
class MockTool extends LaravelTool
{
    protected string $toolName;
    protected bool $shouldFail;
    protected array $mockResponse;

    public function __construct(string $name = 'mock-tool', bool $shouldFail = false, array $mockResponse = [])
    {
        $this->toolName = $name;
        $this->shouldFail = $shouldFail;
        $this->mockResponse = $mockResponse ?: ['result' => 'success'];
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return 'A mock tool for testing purposes';
    }

    protected function properties(): array
    {
        return [
            'input' => [
                'type' => 'string',
                'description' => 'Test input parameter',
            ],
            'number' => [
                'type' => 'number',
                'description' => 'Test number parameter',
            ],
        ];
    }

    protected function required(): array
    {
        return ['input'];
    }

    public function handle(array $params): array
    {
        if ($this->shouldFail) {
            return $this->errorResponse('Mock tool failure');
        }

        $response = array_merge($this->mockResponse, ['params' => $params]);

        if (isset($params['return_text']) && $params['return_text']) {
            return $this->textContent("Mock tool executed with: " . json_encode($params));
        }

        return $response;
    }

    public function requiresAuth(): bool
    {
        return $this->toolName === 'auth-required-tool';
    }

    public function requiredScopes(): array
    {
        return $this->requiresAuth() ? ['test:tool'] : [];
    }

    protected function validationRules(): array
    {
        return [
            'input' => 'required|string|max:255',
            'number' => 'nullable|numeric|min:0',
        ];
    }
}
