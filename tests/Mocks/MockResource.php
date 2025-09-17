<?php

namespace MCP\Laravel\Tests\Mocks;

use MCP\Laravel\Laravel\LaravelResource;

/**
 * Mock resource for testing purposes.
 */
class MockResource extends LaravelResource
{
    protected string $resourceUri;
    protected bool $shouldFail;
    protected array $mockContent;

    public function __construct(string $uri = 'mock://resource', bool $shouldFail = false, array $mockContent = [])
    {
        $this->resourceUri = $uri;
        $this->shouldFail = $shouldFail;
        $this->mockContent = $mockContent ?: ['data' => 'mock content'];
    }

    public function uri(): string
    {
        return $this->resourceUri;
    }

    public function description(): string
    {
        return 'A mock resource for testing purposes';
    }

    public function read(string $uri): array
    {
        if ($this->shouldFail) {
            return $this->errorResponse('Mock resource failure');
        }

        // Handle URI templates
        if (str_contains($this->resourceUri, '{')) {
            $variables = $this->extractUriVariables($uri);
            $content = array_merge($this->mockContent, ['variables' => $variables]);
        } else {
            $content = $this->mockContent;
        }

        // Return different content types based on URI
        if (str_contains($uri, 'json')) {
            return $this->jsonContent($content);
        }

        if (str_contains($uri, 'image')) {
            return $this->imageContent(base64_encode('mock image data'), 'image/png');
        }

        return $this->textContent(json_encode($content));
    }

    public function requiresAuth(): bool
    {
        return str_contains($this->resourceUri, 'secure');
    }

    public function requiredScopes(): array
    {
        return $this->requiresAuth() ? ['test:resource'] : [];
    }

    public function metadata(): array
    {
        return array_merge(parent::metadata(), [
            'test' => true,
            'mock' => true,
        ]);
    }

    protected function mimeType(): string
    {
        if (str_contains($this->resourceUri, 'json')) {
            return 'application/json';
        }

        if (str_contains($this->resourceUri, 'image')) {
            return 'image/png';
        }

        return 'text/plain';
    }
}
