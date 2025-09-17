<?php

namespace MCP\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Laravel\ClientManager;
use MCP\Laravel\Laravel\LaravelMcpServer;
use MCP\Laravel\Laravel\LaravelMcpClient;

/**
 * Main MCP Facade
 *
 * @method static ServerManager servers()
 * @method static ClientManager clients()
 * @method static LaravelMcpServer server(string $name = null)
 * @method static LaravelMcpClient client(string $name = null)
 * @method static void startServer(string $name = null, string $transport = null)
 * @method static void connectClient(string $name, string $serverUrl, string $transport = null)
 * @method static array listServers()
 * @method static array listClients()
 * @method static array getServerStatus(string $name = null)
 * @method static array getClientStatus(string $name = null)
 * @method static void stopServer(string $name = null)
 * @method static void disconnectClient(string $name = null)
 * @method static bool isServerRunning(string $name = null)
 * @method static bool isClientConnected(string $name = null)
 *
 * @see \MCP\Laravel\Laravel\McpManager
 */
class Mcp extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mcp';
    }
}
