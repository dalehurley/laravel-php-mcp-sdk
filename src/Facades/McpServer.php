<?php

namespace MCP\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MCP\Laravel\Laravel\LaravelMcpServer;

/**
 * Server Management Facade
 *
 * @method static LaravelMcpServer get(string $name = null)
 * @method static LaravelMcpServer create(string $name, array $config = [])
 * @method static void start(string $name = null, string $transport = null)
 * @method static void stop(string $name = null)
 * @method static array list()
 * @method static bool exists(string $name)
 * @method static bool isRunning(string $name = null)
 * @method static array getStatus(string $name = null)
 * @method static void addTool(string $serverName, string $toolName, callable $handler, array $schema = [])
 * @method static void addResource(string $serverName, string $uri, callable $handler, array $metadata = [])
 * @method static void addPrompt(string $serverName, string $promptName, callable $handler, array $schema = [])
 * @method static void removeTool(string $serverName, string $toolName)
 * @method static void removeResource(string $serverName, string $uri)
 * @method static void removePrompt(string $serverName, string $promptName)
 * @method static array getTools(string $serverName = null)
 * @method static array getResources(string $serverName = null)
 * @method static array getPrompts(string $serverName = null)
 * @method static array getCapabilities(string $serverName = null)
 * @method static void setCapabilities(string $serverName, array $capabilities)
 * @method static void discover(string $serverName = null, array $directories = [])
 * @method static void registerBatch(string $serverName, array $components)
 *
 * @see \MCP\Laravel\Laravel\ServerManager
 */
class McpServer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mcp.servers';
    }
}
