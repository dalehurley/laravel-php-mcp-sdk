# Laravel MCP SDK - Test Suite

This directory contains comprehensive tests for the Laravel MCP SDK, covering all aspects of the package functionality.

## Test Structure

```
tests/
├── TestCase.php              # Base test case with MCP-specific helpers
├── Mocks/                    # Mock classes for testing
│   ├── MockTool.php         # Mock MCP tool
│   ├── MockResource.php     # Mock MCP resource
│   └── MockPrompt.php       # Mock MCP prompt
├── Unit/                     # Unit tests for individual components
│   ├── Laravel/             # Core Laravel wrapper tests
│   ├── Utilities/           # MCP utilities tests
│   └── Providers/           # Service provider tests
├── Feature/                  # Feature tests for complete workflows
│   ├── Http/                # HTTP transport and middleware tests
│   └── Console/             # Artisan command tests
└── Integration/              # Integration tests for complete system
    └── McpIntegrationTest.php
```

## Test Categories

### Unit Tests

- **Core Components**: McpManager, ServerManager, ClientManager
- **Base Classes**: LaravelTool, LaravelResource, LaravelPrompt
- **Utilities**: ProgressManager, CancellationManager, PingManager
- **Service Provider**: Dependency injection and configuration

### Feature Tests

- **HTTP Layer**: MCP controller endpoints, authentication, security
- **Middleware**: Authentication, CORS, rate limiting, input validation
- **Console Commands**: All Artisan commands with various options

### Integration Tests

- **Complete Workflows**: End-to-end MCP operations
- **Multiple Servers**: Multi-server configuration and management
- **Component Registration**: Tools, resources, and prompts
- **Error Handling**: Graceful failure scenarios

## Running Tests

### Run All Tests

```bash
composer test
```

### Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Feature tests only
vendor/bin/phpunit --testsuite=Feature

# Integration tests only
vendor/bin/phpunit --testsuite=Integration
```

### Run with Coverage

```bash
composer test-coverage
```

### Run Specific Test Files

```bash
vendor/bin/phpunit tests/Unit/Laravel/McpManagerTest.php
vendor/bin/phpunit tests/Feature/Http/McpControllerTest.php
vendor/bin/phpunit tests/Integration/McpIntegrationTest.php
```

## Mock Classes

The test suite includes comprehensive mock classes:

### MockTool

- Configurable success/failure scenarios
- Input validation testing
- Authentication requirement testing
- Various response types (text, error, custom)

### MockResource

- URI template support
- Different content types (JSON, text, images)
- Authentication and authorization testing
- Error scenario simulation

### MockPrompt

- Argument validation
- Message generation testing
- Style and topic customization
- Authentication testing

## Test Helpers

### Base TestCase

The base `TestCase` class provides:

- **MCP Configuration**: Pre-configured test environment
- **Mock Factories**: Easy creation of mock components
- **Assertion Helpers**: MCP-specific assertions
- **Laravel Integration**: Proper Laravel testing setup

### Custom Assertions

- `assertMcpResponse()`: Validates MCP response structure
- `assertMcpError()`: Validates MCP error structure
- `assertArrayStructure()`: Validates nested array structures

## Configuration

### PHPUnit Configuration

- **Test Database**: SQLite in-memory database
- **Cache**: Array driver for testing
- **Queue**: Sync driver for immediate execution
- **Coverage**: Excludes console commands and service provider

### Environment Variables

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
MCP_TRANSPORT=stdio
MCP_AUTH_ENABLED=false
MCP_DEBUG=true
```

## Test Data

### Server Configuration

```php
'servers.test' => [
    'name' => 'Test Server',
    'version' => '1.0.0',
    'transport' => 'stdio',
    'capabilities' => [...],
]
```

### Client Configuration

```php
'clients.test' => [
    'name' => 'Test Client',
    'version' => '1.0.0',
    'capabilities' => [...],
    'timeout' => 5000,
]
```

## Coverage Goals

The test suite aims for:

- **95%+ Line Coverage**: Comprehensive code coverage
- **100% Critical Path Coverage**: All core functionality tested
- **Edge Case Coverage**: Error scenarios and edge cases
- **Integration Coverage**: Complete workflow testing

## Best Practices

### Writing Tests

1. **Use Descriptive Names**: Test method names should clearly describe what is being tested
2. **Follow AAA Pattern**: Arrange, Act, Assert
3. **One Assertion Per Test**: Focus on testing one thing at a time
4. **Use Mock Classes**: Leverage provided mock classes for consistent testing
5. **Test Error Cases**: Include negative test cases and error scenarios

### Test Organization

1. **Group Related Tests**: Keep related tests in the same class
2. **Use Setup Methods**: Initialize common test data in `setUp()`
3. **Clean Teardown**: Clean up resources in `tearDown()` if needed
4. **Isolated Tests**: Each test should be independent

### Performance

1. **Fast Tests**: Keep unit tests fast and focused
2. **Minimal Setup**: Only set up what's needed for each test
3. **Efficient Mocks**: Use lightweight mock objects
4. **Parallel Execution**: Tests should be safe for parallel execution

## Continuous Integration

The test suite is designed to work with CI/CD pipelines:

- **No External Dependencies**: All tests run in isolation
- **Deterministic Results**: Tests produce consistent results
- **Fast Execution**: Optimized for quick feedback
- **Comprehensive Coverage**: Catches regressions effectively

## Debugging Tests

### Running Individual Tests

```bash
vendor/bin/phpunit --filter testMethodName
```

### Debug Mode

```bash
vendor/bin/phpunit --debug
```

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

### Stop on Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

## Contributing

When adding new features:

1. **Write Tests First**: Follow TDD approach when possible
2. **Update Mocks**: Extend mock classes as needed
3. **Test All Paths**: Include happy path and error scenarios
4. **Update Documentation**: Keep this README current
5. **Check Coverage**: Ensure new code is well-tested

The test suite is a critical part of the Laravel MCP SDK, ensuring reliability, maintainability, and confidence in all MCP operations.
