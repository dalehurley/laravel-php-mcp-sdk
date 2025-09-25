# Changelog

All notable changes to the Laravel PHP MCP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Dependencies

- **Upstream PHP-MCP-SDK Fix**: Updated to include fix for unhandled future errors in StreamableHttpClientTransport that was causing "UnhandledFutureError" exceptions with invalid sessions
  - The underlying PHP-MCP-SDK now properly handles all async operations in the HTTP transport
  - Fixes issues when HTTP 400 "missing or invalid session" errors occurred
  - Requires PHP-MCP-SDK version that includes the StreamableHttpClientTransport future handling fix

## [0.1.2] - 2025-09-18

### Fixed

- **UnhandledFutureError Prevention** - Fixed `Amp\Future\UnhandledFutureError` when MCP connections are closed by implementing proper future handling with `safeAwait()` helper method that catches connection closed scenarios and updates client connection status
- **Connection State Management** - Improved connection state tracking by automatically marking client as disconnected when receiving "Connection closed" errors (-32000), preventing subsequent operations on dead connections
- **Error Handling Optimization** - Added centralized `handleException()` method to reduce code duplication and ensure consistent error handling across all MCP client operations

### Added

- **Comprehensive Test Coverage** - Added unit and integration tests for connection error handling scenarios to ensure reliability and prevent regressions

## [0.1.1] - 2025-09-17

### Fixed

- **Client Capabilities Validation** - Fixed MCP validation error where empty experimental and sampling capabilities were being sent as arrays instead of being omitted, causing "Expected object, received array" validation errors when connecting to MCP servers like `mcp.pga.com`
- **LaravelMcpClient Initialization** - Added `prepareCapability()` method to convert empty capability arrays to `null` during client initialization, preventing JSON serialization of empty arrays that cause server-side validation failures

## [0.1.0] - 2025-09-17

Initial pre-release of the Laravel PHP MCP SDK, providing a comprehensive Laravel wrapper around the PHP-MCP-SDK with full MCP 2025-06-18 specification support.

### Added

- **Comprehensive Laravel MCP SDK Foundation** - Complete Laravel wrapper around PHP-MCP-SDK with full MCP 2025-06-18 specification support
- **Multiple Server and Client Support** - Support for multiple named server instances and client connections with dynamic creation and management
- **Laravel Service Provider** - Complete service provider with auto-discovery, configuration publishing, and middleware registration
- **Multiple Facades** - `Mcp`, `McpServer`, and `McpClient` facades for convenient access to all MCP functionality
- **Core Management Classes** - `McpManager`, `ServerManager`, and `ClientManager` for centralized control of MCP operations
- **Laravel-Friendly Base Classes** - `LaravelTool`, `LaravelResource`, and `LaravelPrompt` base classes with Laravel integration helpers
- **Comprehensive Configuration** - Extensive configuration file with support for multiple servers, clients, transports, and features
- **MCP Utilities** - Full implementation of cancellation, ping, progress reporting, completion, logging, and pagination utilities
- **HTTP Transport Layer** - Complete HTTP controller, authentication middleware, security middleware, and routes for HTTP transport
- **Comprehensive Artisan Commands** - Full suite of commands for server management, client operations, listing components, testing, and installation
- **Authentication and Authorization** - OAuth 2.1, bearer token, and API key authentication with scope-based authorization
- **Security Best Practices** - CORS handling, rate limiting, input validation, security headers, and suspicious activity detection
- **Auto-Discovery System** - Automatic discovery and registration of tools, resources, and prompts from configured directories
- **Laravel Integration** - Deep integration with Laravel's container, events, cache, queue, validation, and logging systems
- **Production-Ready Features** - Connection pooling, health checks, monitoring, graceful shutdown, and comprehensive error handling

### Added (Latest)

- **Laravel 12 Support** - Added support for Laravel 12.x alongside existing 10.x and 11.x support

### Fixed

- **Console Command Verbose Options** - Fixed conflicts with Laravel's built-in verbose option by removing custom --verbose options and using Laravel's standard verbose handling
- **HTTP Middleware Implementation** - Completed security middleware with proper request validation, CORS preflight handling, and input sanitization
- **HTTP Controller Tests** - Fixed server running state checks for testing environment compatibility
- **Regex Pattern Validation** - Fixed JavaScript-style regex modifiers in security middleware to use proper PHP syntax
- **Server Name Access** - Added public getName() method to LaravelMcpServer for proper encapsulation
- **CORS Preflight Handling** - Fixed OPTIONS request handling by ensuring middleware processes preflight requests
- **Test Suite Compatibility** - Fixed all test failures to achieve 100% test passing rate (143/143 tests)

### Enhanced

- **Laravel Events** - Added comprehensive event system with ToolExecuted, ProgressUpdated, and OperationCancelled events
- **Background Jobs** - Added PeriodicPingJob for background connection monitoring and health checks
- **Configuration Validation** - Added runtime configuration validation with detailed error reporting
- **Broadcasting Support** - Added real-time progress updates via Laravel broadcasting system
- **Production Hardening** - Enhanced error handling, logging, and graceful failure recovery
- **Orchestra Testbench 10.x** - Updated testing framework to support Laravel 12.x
- **Comprehensive Test Suite** - Complete test coverage with unit, feature, and integration tests
  - Unit tests for all core components (managers, base classes, utilities)
  - Feature tests for HTTP layer (controllers, middleware, authentication)
  - Integration tests for complete MCP system workflows
  - Console command tests for all Artisan commands
  - Mock classes for testing tools, resources, and prompts
  - PHPUnit configuration with coverage reporting
  - Service provider tests for dependency injection and configuration
- **Test Infrastructure Fixes** - Fixed all major test issues and improved test reliability
  - Fixed ProgressToken property access using getValue() method
  - Fixed MCP Server method calls (registerTool, registerResource, registerPrompt)
  - Fixed Laravel Collection usage (replaced clear() with new Collection())
  - Fixed console command verbose option conflicts
  - Fixed HTTP middleware return type issues
  - Created missing transport and feature manager classes
  - Improved test mocking and setup for better reliability
- **Complete Transport Implementation** - Implemented actual transport layer startup methods
  - STDIO transport using StdioServerTransport/StdioClientTransport with proper process management
  - HTTP transport using StreamableHttpServerTransport/StreamableHttpClientTransport with configurable host/port
  - WebSocket transport using WebSocketServerTransport/WebSocketClientTransport with connection limits
  - Proper integration with AMP async framework for non-blocking operations
  - Configuration-driven transport options with Laravel config system
- **Complete Method Implementations** - Fixed all placeholder and incomplete method implementations
  - Fixed LaravelMcpClient methods to use proper MCP request objects and Amp futures
  - Implemented proper tool calling with CallToolRequest and await patterns
  - Fixed resource reading with ReadResourceRequest and proper async handling
  - Fixed prompt handling with GetPromptRequest and proper response handling
  - Implemented proper list operations (tools, resources, prompts) with request objects
  - Fixed ping operations with proper Future handling
  - Improved remove operations with proper logging and restart requirements
  - Fixed completion, cancellation, and progress methods to use Laravel managers
  - Replaced placeholder OAuth comments with proper Laravel integration patterns

### Changed

- **Project Structure** - Organized as a comprehensive Laravel package with proper namespace structure and Laravel conventions
- **Configuration Approach** - Laravel-native configuration with environment variable support and sensible defaults
- **Error Handling** - Laravel-style exception handling with custom MCP exception classes and proper error responses

---

## Changelog Update Guidelines

When making changes to the PHP MCP SDK:

1. **Before starting work**: Review this changelog to understand recent changes
2. **During development**: Keep notes of what you're changing
3. **After completing changes**: Update this changelog with:
   - What was added, changed, fixed, or removed
   - Why the change was made (if not obvious)
   - Any breaking changes or migration notes

### Categories to use:

- **Added** - for new features
- **Changed** - for changes in existing functionality
- **Deprecated** - for soon-to-be removed features
- **Removed** - for now removed features
- **Fixed** - for any bug fixes
- **Security** - in case of vulnerabilities

### Example entry:

```markdown
### Added

- New `McpServer::registerMiddleware()` method for adding custom middleware
- Support for WebSocket transport in client

### Changed

- Improved error handling in STDIO transport to handle partial messages
- Updated minimum ReactPHP version to 3.0 for better performance

### Fixed

- Fixed memory leak in long-running servers when handling large payloads
- Fixed capability merging in Server class to properly handle readonly ServerCapabilities properties
- Fixed WritableIterableStream constructor calls in transport implementations to include buffer size
- Fixed Response constructor calls to use readable stream iterators instead of writable streams
- Fixed JsonSerializable interface checks in transport and protocol classes
- Fixed Amp buffer() method calls to remove deprecated size parameters
- Fixed schema validation integration in McpServer for tools and prompts with actual validation logic
```
