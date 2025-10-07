# Laravel MCP SDK Troubleshooting Guide

## Common Connection Issues

### Error: "Client [name] is not connected to any server"

This error occurs when attempting to use an MCP client that hasn't been properly connected to a server. This guide explains the causes and solutions.

#### Symptoms

You receive an error message like:

```json
{ "error": "Client example is not connected to any server" }
```

#### Root Causes

1. **Client not connected** - The `connect()` method was never called
2. **Connection failed** - The connection attempt failed but error was not caught
3. **Connection dropped** - The connection was established but has since been lost
4. **Race condition** (Fixed in v0.1.4) - Connection reported as successful before fully established

#### Solutions

##### 1. Ensure Connection Before Use

Always call `connect()` before using any client methods:

```php
use MCP\Laravel\Facades\McpClient;

// Connect to server first
McpClient::connect('example', 'http://mcp.example.com');

// Then use client methods
$tools = McpClient::listTools('example');
```

##### 2. Check Configuration

Verify your client is configured in `config/mcp.php`:

```php
'clients' => [
    'example' => [
        'name' => 'example Client',
        'version' => '1.0.0',
        'capabilities' => [
            'experimental' => [],
            'sampling' => [],
            'roots' => ['listChanged' => true],
        ],
        'timeout' => 30000,
    ],
],
```

##### 3. Verify Connection Status

Check if the client is connected:

```php
if (!McpClient::isConnected('example')) {
    // Client is not connected, attempt to connect
    try {
        McpClient::connect('example', $serverUrl, 'http');
    } catch (\Exception $e) {
        Log::error('Failed to connect MCP client', [
            'client' => 'example',
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

##### 4. Handle Connection Errors

Wrap connection and usage in try-catch blocks:

```php
try {
    // Connect to server
    McpClient::connect('example', 'http://mcp.example.com', 'http', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    // Use the client
    $result = McpClient::callTool('example', 'some-tool', ['param' => 'value']);

} catch (\MCP\Laravel\Exceptions\ClientNotConnectedException $e) {
    // Handle not connected error
    Log::error('Client not connected', ['error' => $e->getMessage()]);

} catch (\MCP\Laravel\Exceptions\McpException $e) {
    // Handle MCP errors
    Log::error('MCP operation failed', ['error' => $e->getMessage()]);
}
```

##### 5. Use Connection Status Checks

Get detailed status information:

```php
$status = McpClient::getStatus('example');

// Check connection status
if (!$status['connected']) {
    // Attempt reconnection
    McpClient::reconnect('example');
}

// Log status information
Log::info('MCP Client Status', $status);
/*
Output:
[
    'name' => 'example',
    'connected' => true,
    'server_url' => 'http://mcp.example.com',
    'transport' => 'http',
    'connected_at' => '2025-10-07T10:30:00+00:00',
    'uptime' => 3600,
    'request_count' => 42,
    'error_count' => 0,
    'capabilities' => [...],
    'server_capabilities' => [...]
]
*/
```

##### 6. Test Connection

Test server connectivity before using:

```php
$result = McpClient::testConnection('http://mcp.example.com', 'http');

if ($result['success']) {
    Log::info('Server is reachable', [
        'transport' => $result['transport'],
        'response_time' => $result['response_time'],
        'capabilities' => $result['capabilities']
    ]);

    // Now connect for real use
    McpClient::connect('example', 'http://mcp.example.com');
} else {
    Log::error('Server not reachable', [
        'error' => $result['error'],
        'error_code' => $result['error_code']
    ]);
}
```

#### Best Practices

1. **Connection Management Pattern**

```php
class McpService
{
    protected function ensureConnected(string $clientName): void
    {
        if (!McpClient::isConnected($clientName)) {
            $config = config("mcp.clients.{$clientName}");
            $serverUrl = $config['server_url'] ?? throw new \Exception("No server URL configured");

            McpClient::connect($clientName, $serverUrl);
        }
    }

    public function callTool(string $clientName, string $tool, array $params): mixed
    {
        $this->ensureConnected($clientName);
        return McpClient::callTool($clientName, $tool, $params);
    }
}
```

2. **Service Provider Registration**

Register clients and connect them during application boot:

```php
// app/Providers/McpServiceProvider.php
public function boot()
{
    if (!app()->runningInConsole() || app()->runningUnitTests()) {
        $this->connectClients();
    }
}

protected function connectClients(): void
{
    $clients = config('mcp.auto_connect_clients', []);

    foreach ($clients as $clientName => $serverUrl) {
        try {
            McpClient::connect($clientName, $serverUrl);
            Log::info("MCP client connected", ['client' => $clientName]);
        } catch (\Exception $e) {
            Log::warning("Failed to connect MCP client", [
                'client' => $clientName,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

3. **Middleware for HTTP Requests**

Ensure connection in middleware:

```php
// app/Http/Middleware/EnsureMcpConnected.php
class EnsureMcpConnected
{
    public function handle(Request $request, Closure $next, string $clientName)
    {
        if (!McpClient::isConnected($clientName)) {
            $serverUrl = config("mcp.clients.{$clientName}.server_url");

            if (!$serverUrl) {
                abort(503, "MCP client {$clientName} not configured");
            }

            try {
                McpClient::connect($clientName, $serverUrl);
            } catch (\Exception $e) {
                Log::error("Failed to connect MCP client in middleware", [
                    'client' => $clientName,
                    'error' => $e->getMessage()
                ]);
                abort(503, "MCP service unavailable");
            }
        }

        return $next($request);
    }
}
```

4. **Health Check Endpoint**

Create a health check route:

```php
// routes/web.php
Route::get('/health/mcp', function () {
    $clients = config('mcp.clients');
    $health = [];

    foreach (array_keys($clients) as $clientName) {
        try {
            $status = McpClient::getStatus($clientName);
            $health[$clientName] = [
                'status' => $status['connected'] ? 'connected' : 'disconnected',
                'uptime' => $status['uptime'],
                'requests' => $status['request_count'],
                'errors' => $status['error_count'],
            ];
        } catch (\Exception $e) {
            $health[$clientName] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    return response()->json($health);
});
```

## Transport-Specific Issues

### STDIO Transport

**Issue**: STDIO connections fail silently

**Solution**: Ensure the command is executable and in PATH:

```php
McpClient::connect('local', '/usr/local/bin/mcp-server', 'stdio', [
    'args' => ['--config', '/path/to/config.json'],
    'env' => ['DEBUG' => '1']
]);
```

### HTTP Transport

**Issue**: HTTP connections timeout

**Solution**: Increase timeout and verify server is running:

```php
// In config/mcp.php
'clients' => [
    'remote' => [
        'timeout' => 60000, // 60 seconds
    ],
],
```

### WebSocket Transport

**Issue**: WebSocket connections drop frequently

**Solution**: Enable heartbeat pinging:

```php
// In config/mcp.php
'utilities' => [
    'ping' => [
        'enabled' => true,
        'interval' => 30, // Ping every 30 seconds
    ],
],
```

## Debugging Connection Issues

Enable debug logging:

```php
// In config/mcp.php
'development' => [
    'debug' => true,
    'log_requests' => true,
    'log_responses' => true,
],

'utilities' => [
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
        'channel' => 'mcp',
    ],
],
```

Then check logs:

```bash
tail -f storage/logs/laravel.log | grep MCP
```

## Recent Fixes (v0.1.4+)

The following issues have been fixed in recent versions:

- **STDIO race condition** - STDIO connections now properly await before marking as connected
- **Connection verification** - Server capabilities are fetched to verify connection before use
- **Future handling** - `listRootContents()` and other methods properly await futures

If you're experiencing connection issues, ensure you're running the latest version:

```bash
composer update dalehurley/laravel-php-mcp-sdk
```

## Getting Help

If you continue to experience issues:

1. Check the [GitHub Issues](https://github.com/dalehurley/laravel-php-mcp-sdk/issues)
2. Enable debug logging and review logs
3. Test connection independently using `testConnection()` method
4. Verify server is running and accessible
5. Check firewall and network settings

For additional support, open an issue with:

- Laravel version
- PHP version
- SDK version
- Full error message and stack trace
- Connection configuration (sanitized)
- Debug logs
