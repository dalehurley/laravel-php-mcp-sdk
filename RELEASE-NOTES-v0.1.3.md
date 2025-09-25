# Release Notes - Laravel PHP MCP SDK v0.1.3

Release Date: September 25, 2025

## Overview

This patch release includes an important upstream fix from PHP-MCP-SDK v0.1.8 that resolves UnhandledFutureError exceptions that could occur in certain error scenarios.

## Dependencies Update

### Upstream PHP-MCP-SDK Fix

The underlying PHP-MCP-SDK has been updated to include a critical fix for unhandled future errors in StreamableHttpClientTransport. This fix prevents "UnhandledFutureError" exceptions that were occurring when session errors happened.

#### What was fixed:

- The PHP-MCP-SDK now properly handles all async operations in the HTTP transport
- Prevents `Amp\Future\UnhandledFutureError` when HTTP 400 "missing or invalid session" errors occur
- All futures are now properly awaited or explicitly ignored to prevent Amp framework warnings

#### Technical details:

- Modified `startSseStream()`, `handleSseStream()`, and `scheduleReconnection()` methods to properly return Future objects
- Added `->ignore()` calls to all async operations that don't require result handling
- Improves error handling robustness for async operations

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- PHP-MCP-SDK v0.1.8 or higher (automatically satisfied by existing constraint)

## Upgrade Instructions

```bash
composer update dalehurley/laravel-php-mcp-sdk
```

No code changes are required. The fix is automatically applied through the updated dependency.

## Impact

This update prevents error log pollution and improves stability when MCP connections encounter session-related errors. Users who have experienced `UnhandledFutureError` exceptions should see these resolved after updating.
