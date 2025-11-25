<?php

use Illuminate\Support\Facades\Route;
use MCP\Laravel\Http\Controllers\McpController;
use MCP\Laravel\Http\Controllers\McpUiActionController;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| These routes handle HTTP transport for MCP servers. They provide
| REST-like endpoints for MCP protocol operations including tools,
| resources, prompts, and server management.
|
*/

$prefix = config('mcp.transports.http.routes.prefix', 'mcp');
$middleware = config('mcp.transports.http.routes.middleware', ['api', 'mcp.auth', 'mcp.security']);

Route::prefix($prefix)->middleware($middleware)->group(function () {

    // Server-specific routes
    Route::prefix('{server}')->group(function () {

        // Main MCP endpoint for JSON-RPC requests
        Route::post('/', [McpController::class, 'handle'])->name('mcp.server.handle');

        // Server information and capabilities
        Route::get('/capabilities', [McpController::class, 'capabilities'])->name('mcp.server.capabilities');
        Route::get('/status', [McpController::class, 'status'])->name('mcp.server.status');

        // Health check endpoint
        Route::get('/ping', [McpController::class, 'ping'])->name('mcp.server.ping');

        // Tools endpoints
        Route::prefix('tools')->group(function () {
            Route::get('/', [McpController::class, 'listTools'])->name('mcp.server.tools.list');
            Route::post('/call', [McpController::class, 'callTool'])->name('mcp.server.tools.call');
        });

        // Resources endpoints
        Route::prefix('resources')->group(function () {
            Route::get('/', [McpController::class, 'listResources'])->name('mcp.server.resources.list');
            Route::post('/read', [McpController::class, 'readResource'])->name('mcp.server.resources.read');
        });

        // Prompts endpoints
        Route::prefix('prompts')->group(function () {
            Route::get('/', [McpController::class, 'listPrompts'])->name('mcp.server.prompts.list');
            Route::post('/get', [McpController::class, 'getPrompt'])->name('mcp.server.prompts.get');
        });
    });
});

// Global MCP endpoints (not server-specific)
Route::prefix($prefix)->middleware(['api'])->group(function () {

    // List all configured servers
    Route::get('/servers', function () {
        $manager = app(\MCP\Laravel\Laravel\McpManager::class);
        return response()->json([
            'servers' => $manager->listServers(),
        ]);
    })->name('mcp.servers.list');

    // System health check
    Route::get('/health', function () {
        $manager = app(\MCP\Laravel\Laravel\McpManager::class);
        return response()->json($manager->healthCheck());
    })->name('mcp.health');

    // System status
    Route::get('/status', function () {
        $manager = app(\MCP\Laravel\Laravel\McpManager::class);
        return response()->json($manager->getSystemStatus());
    })->name('mcp.status');
});

// MCP-UI action endpoint
// Handles widget actions (tool calls, notifications, prompts, links)
// Note: Service provider checks mcp.ui.enabled before loading routes
Route::prefix($prefix)->group(function () {
    $uiMiddleware = config('mcp.ui.action_middleware', ['api', 'throttle:60,1']);

    Route::post('/ui-action', [McpUiActionController::class, 'handle'])
        ->middleware($uiMiddleware)
        ->name('mcp.ui.action');
});

// CORS preflight handling for all MCP routes
Route::options($prefix . '/{any}', function () {
    return response('', 200);
})->where('any', '.*')->middleware(['mcp.security'])->name('mcp.cors');
