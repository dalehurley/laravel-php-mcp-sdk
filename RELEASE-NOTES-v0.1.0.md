# Laravel PHP MCP SDK v0.1.0 Release Notes

## Release Date: September 17, 2025

## Overview

Initial pre-release of the Laravel PHP MCP SDK, providing a comprehensive Laravel wrapper around the PHP-MCP-SDK with full MCP 2025-06-18 specification support.

## Pre-Release Notice

This is a pre-1.0 release, indicating that the API may still undergo changes. We're releasing v0.1.0 to align with the PHP MCP SDK which is also pre-1.0 (currently at v0.1.7).

## Key Features

- **Full MCP 2025-06-18 Specification Support**: Complete implementation of the Model Context Protocol
- **Multiple Server and Client Support**: Manage multiple named MCP server instances and client connections
- **Laravel-Native Integration**: Deep integration with Laravel's service container, events, caching, queues, and validation
- **All Transport Types**: Support for STDIO, HTTP, and WebSocket transports
- **Authentication & Security**: OAuth 2.1 with PKCE, bearer tokens, API keys, and comprehensive security middleware
- **Developer-Friendly**: Intuitive base classes, auto-discovery, rich Artisan commands, and comprehensive documentation
- **Production Ready**: Connection pooling, health checks, monitoring, graceful shutdown, and enterprise-grade error handling

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- dalehurley/php-mcp-sdk ^0.1.7
- Composer

## Installation

```bash
composer require dalehurley/laravel-php-mcp-sdk
```

## Documentation

Full documentation is available in the README.md and the comprehensive inline documentation throughout the codebase.

## Testing

The package includes a comprehensive test suite with 143 tests covering all major functionality.

## Stability Note

As a pre-1.0 release, minor version updates (0.2.0, 0.3.0, etc.) may include breaking changes. We recommend reviewing the changelog carefully when updating.

## License

MIT License - see LICENSE file for details.

## Support

- GitHub Issues: https://github.com/dalehurley/laravel-php-mcp-sdk/issues
- Documentation: See README.md

## Acknowledgments

Built on top of the robust dalehurley/php-mcp-sdk package.
