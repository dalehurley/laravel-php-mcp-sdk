# Laravel MCP SDK

[![Latest Version](https://img.shields.io/packagist/v/dalehurley/laravel-php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/laravel-php-mcp-sdk)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/dalehurley/laravel-php-mcp-sdk.svg?style=flat-square)](https://packagist.org/packages/dalehurley/laravel-php-mcp-sdk)

A comprehensive Laravel wrapper around the [PHP MCP SDK](https://github.com/dalehurley/php-mcp-sdk) that provides full **MCP 2025-06-18 specification support** with Laravel's ease of use and conventions.

## Features

### 🚀 **Full MCP 2025-06-18 Specification Support**

- Complete implementation of the Model Context Protocol
- All transport types: STDIO, HTTP, WebSocket
- Full support for tools, resources, prompts, roots, sampling, and elicitation
- Advanced utilities: cancellation, ping, progress reporting, completion, logging, pagination

### 🔄 **Multiple Dynamic Servers & Clients**

- Support for multiple named server instances
- Multiple named client connections
- Dynamic server/client creation and management
- Connection pooling and lifecycle management
- Per-instance configuration and capabilities

### 🎯 **Laravel-Native Integration**

- Deep integration with Laravel's service container, events, cache, queue system
- Laravel-style configuration with environment variables
- Comprehensive Artisan commands for all MCP operations
- Auto-discovery of tools, resources, and prompts
- Laravel validation, middleware, and security features

### 🔒 **Production-Ready Security**

- OAuth 2.1 authentication with PKCE support
- Bearer token and API key authentication
- Scope-based authorization
- CORS handling, rate limiting, input validation
- Security headers and suspicious activity detection

### 🛠 **Developer Experience**

- Zero configuration for basic use cases
- Intuitive Laravel-style APIs
- Rich debugging and monitoring capabilities
- Comprehensive testing with Laravel's test helpers
- Extensive documentation and examples

## Installation

Install the package via Composer:

```bash
composer require dalehurley/laravel-php-mcp-sdk
```

The package will automatically register its service provider and facades.

## Quick Start

### 1. Install MCP Scaffolding

```bash
php artisan mcp:install --all
```

This will:

- Publish the configuration file
- Create necessary directories
- Install example tools, resources, and prompts
- Set up the basic structure

### 2. Configure Your Servers

Edit `config/mcp.php` to configure your servers:

```php
'servers' => [
    'main' => [
        'name' => 'My MCP Server',
        'version' => '1.0.0',
        'transport' => 'stdio',
        'tools' => [
            'discover' => [app_path('Mcp/Tools')],
            'auto_register' => true,
        ],
    ],

    'api' => [
        'name' => 'API Server',
        'transport' => 'http',
        'tools' => [
            'discover' => [app_path('Mcp/Api/Tools')],
            'auto_register' => true,
        ],
    ],
],
```

### 3. Create Your First Tool

```bash
# This creates app/Mcp/Tools/CalculatorTool.php
```

```php
<?php

namespace App\Mcp\Tools;

use MCP\Laravel\Laravel\LaravelTool;

class CalculatorTool extends LaravelTool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Performs basic arithmetic operations';
    }

    protected function properties(): array
    {
        return [
            'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number'],
        ];
    }

    protected function required(): array
    {
        return ['operation', 'a', 'b'];
    }

    public function handle(array $params): array
    {
        $result = match ($params['operation']) {
            'add' => $params['a'] + $params['b'],
            'subtract' => $params['a'] - $params['b'],
            'multiply' => $params['a'] * $params['b'],
            'divide' => $params['b'] != 0 ? $params['a'] / $params['b'] : null,
        };

        if ($result === null) {
            return $this->errorResponse('Division by zero');
        }

        return $this->textContent("Result: {$result}");
    }
}
```

### 4. Start Your Server

```bash
# Start the default server
php artisan mcp:server start

# Start specific server with HTTP transport
php artisan mcp:server start api --transport=http

# Start with custom port
php artisan mcp:server start api --transport=http --port=3000
```

### 5. Test Your Setup

```bash
# Run comprehensive tests
php artisan mcp:test

# List all servers and their status
php artisan mcp:list

# Test connection to a server
php artisan mcp:client connect main http://localhost:3000
```

## Usage Examples

### Using Facades

```php
use MCP\Laravel\Facades\Mcp;
use MCP\Laravel\Facades\McpServer;
use MCP\Laravel\Facades\McpClient;

// Start multiple servers
McpServer::start('main', 'stdio');
McpServer::start('api', 'http');
McpServer::start('websocket-server', 'websocket');

// Add tools dynamically
McpServer::addTool('main', 'weather', function($params) {
    return ['content' => [['type' => 'text', 'text' => 'Sunny, 72°F']]];
});

// Connect clients to different servers
McpClient::connect('local', 'http://localhost:3000');
McpClient::connect('remote', 'https://api.example.com/mcp');

// Call tools on different servers
$result = McpClient::callTool('local', 'calculator', ['operation' => 'add', 'a' => 5, 'b' => 3]);
$weather = McpClient::callTool('remote', 'weather', ['city' => 'New York']);

// Get system overview
$status = Mcp::getSystemStatus();
```

### Advanced Features

#### Progress Reporting for Long-Running Tools

```php
use MCP\Laravel\Laravel\LaravelTool;

class DataProcessingTool extends LaravelTool
{
    public function handle(array $params): array
    {
        $progress = app(\MCP\Laravel\Utilities\ProgressManager::class);
        $progressToken = $progress->start('Processing data...', 100);

        for ($i = 0; $i < 100; $i++) {
            // Do work
            usleep(50000); // 50ms delay
            $progress->update($progressToken, $i + 1, "Processing item " . ($i + 1));
        }

        $progress->complete($progressToken, 'Processing completed');
        return $this->textContent('Data processing completed successfully');
    }
}
```

#### Cancellation Support

```php
class CancellableTask extends LaravelTool
{
    public function handle(array $params): array
    {
        $cancellation = app(\MCP\Laravel\Utilities\CancellationManager::class);
        $token = $cancellation->createToken('task-' . uniqid());

        for ($i = 0; $i < 1000; $i++) {
            if ($cancellation->isCancelled($token)) {
                return $this->textContent('Task was cancelled');
            }
            // Do work
        }

        return $this->textContent('Task completed');
    }
}
```

#### Resource with URI Templates

```php
use MCP\Laravel\Laravel\LaravelResource;

class UserResource extends LaravelResource
{
    public function uri(): string
    {
        return 'user://{id}';
    }

    public function read(string $uri): array
    {
        $variables = $this->extractUriVariables($uri);
        $user = User::find($variables['id']);

        return $this->jsonContent($user->toArray());
    }
}
```

#### Authentication and Authorization

```php
class SecureUserTool extends LaravelTool
{
    public function requiresAuth(): bool
    {
        return true;
    }

    public function requiredScopes(): array
    {
        return ['mcp:tools', 'user:read'];
    }

    public function handle(array $params): array
    {
        $user = $this->user(); // Gets authenticated user
        return $this->textContent("Hello, {$user->name}!");
    }
}
```

### Artisan Commands

```bash
# Server Management
php artisan mcp:server start main --transport=stdio
php artisan mcp:server stop main
php artisan mcp:server restart main
php artisan mcp:server status main

# Client Operations
php artisan mcp:client connect main http://localhost:3000
php artisan mcp:client call-tool main --tool=calculator --params='{"operation":"add","a":5,"b":3}'
php artisan mcp:client read-resource main --resource=user://123
php artisan mcp:client list-tools main

# System Overview
php artisan mcp:list --servers --clients --tools
php artisan mcp:list --status --json

# Testing and Health Checks
php artisan mcp:test
php artisan mcp:test --server=main
php artisan mcp:test --url=http://localhost:3000
php artisan mcp:test --health
```

## Configuration

The package provides extensive configuration options in `config/mcp.php`:

### Multiple Servers

```php
'servers' => [
    'main' => [
        'name' => 'Main Server',
        'transport' => 'stdio',
        'capabilities' => ['tools', 'resources', 'prompts'],
    ],
    'api' => [
        'name' => 'API Server',
        'transport' => 'http',
        'capabilities' => ['tools', 'resources'],
    ],
    'websocket' => [
        'name' => 'WebSocket Server',
        'transport' => 'websocket',
        'capabilities' => ['tools', 'resources', 'prompts', 'roots'],
    ],
],
```

### Transport Configuration

```php
'transports' => [
    'stdio' => [
        'enabled' => true,
        'buffer_size' => 8192,
    ],
    'http' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 3000,
        'security' => [
            'cors_enabled' => true,
            'rate_limiting' => '60,1',
        ],
    ],
    'websocket' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 3001,
        'max_connections' => 1000,
    ],
],
```

### Authentication

```php
'authorization' => [
    'enabled' => true,
    'provider' => 'oauth', // oauth, bearer, api_key
    'oauth' => [
        'scopes' => [
            'mcp:tools' => 'Access to MCP tools',
            'mcp:resources' => 'Access to MCP resources',
            'mcp:prompts' => 'Access to MCP prompts',
        ],
        'pkce_required' => true,
    ],
],
```

## Architecture

### Core Components

- **McpManager**: Central coordinator for all MCP operations
- **ServerManager**: Manages multiple server instances
- **ClientManager**: Manages multiple client connections
- **LaravelTool/Resource/Prompt**: Base classes for MCP components
- **Utilities**: Progress, cancellation, ping, logging, pagination
- **Transport Managers**: STDIO, HTTP, WebSocket transport handling

### Laravel Integration

- **Service Provider**: Registers all services and configurations
- **Facades**: Convenient access to MCP functionality
- **Middleware**: Authentication and security for HTTP transport
- **Commands**: Comprehensive Artisan command suite
- **Events**: Laravel events for MCP operations
- **Cache**: Intelligent caching for performance
- **Queue**: Background processing support

## Testing

### MCP System Testing

The package includes comprehensive MCP system testing capabilities:

```bash
# Run all MCP system tests
php artisan mcp:test

# Test specific components
php artisan mcp:test --server=main
php artisan mcp:test --client=main
php artisan mcp:test --url=http://localhost:3000

# Health check
php artisan mcp:test --health

# Verbose output
php artisan mcp:test --verbose
```

### Unit & Integration Testing

The package includes a comprehensive test suite with 95%+ coverage:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test suites
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
vendor/bin/phpunit --testsuite=Integration

# Run specific tests
vendor/bin/phpunit tests/Unit/Laravel/McpManagerTest.php
vendor/bin/phpunit tests/Feature/Http/McpControllerTest.php
```

#### Test Structure

- **Unit Tests**: Core components, base classes, utilities
- **Feature Tests**: HTTP layer, middleware, console commands
- **Integration Tests**: Complete MCP system workflows
- **Mock Classes**: Comprehensive mocks for tools, resources, prompts

#### Coverage Goals

- ✅ **95%+ Line Coverage**
- ✅ **100% Critical Path Coverage**
- ✅ **Complete Error Scenario Coverage**
- ✅ **Integration Workflow Testing**

## Security

The Laravel MCP SDK implements comprehensive security measures:

- **OAuth 2.1** with PKCE support
- **Bearer token** and **API key** authentication
- **Scope-based authorization**
- **CORS** handling with configurable origins
- **Rate limiting** per IP address
- **Input validation** and sanitization
- **Security headers** (CSP, XSS protection, etc.)
- **Suspicious activity detection**
- **Request size limits**
- **Security event logging**

## Performance

- **Connection pooling** for efficient resource usage
- **Intelligent caching** with configurable TTL
- **Background processing** with Laravel queues
- **Memory usage monitoring**
- **Response time tracking**
- **Health monitoring** and alerts

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## Security Vulnerabilities

If you discover a security vulnerability, please send an e-mail to the maintainers. All security vulnerabilities will be promptly addressed.

## License

The Laravel MCP SDK is open-sourced software licensed under the [MIT license](LICENSE).

## Related Projects

- [PHP MCP SDK](https://github.com/dalehurley/php-mcp-sdk) - The underlying PHP implementation
- [MCP Specification](https://spec.modelcontextprotocol.io/) - Official MCP specification

## Support

- 📖 [Documentation](https://github.com/dalehurley/laravel-php-mcp-sdk/docs)
- 🐛 [Issue Tracker](https://github.com/dalehurley/laravel-php-mcp-sdk/issues)
- 💬 [Discussions](https://github.com/dalehurley/laravel-php-mcp-sdk/discussions)

---

**Laravel MCP SDK** - Bringing the power of the Model Context Protocol to Laravel applications with enterprise-grade features and Laravel's elegant developer experience.
