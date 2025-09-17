<?php

namespace MCP\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MCP\Laravel\Laravel\LaravelMcpClient;

/**
 * Client Management Facade
 *
 * @method static LaravelMcpClient get(string $name = null)
 * @method static LaravelMcpClient create(string $name, array $config = [])
 * @method static void connect(string $name, string $serverUrl, string $transport = null, array $options = [])
 * @method static void disconnect(string $name = null)
 * @method static array list()
 * @method static bool exists(string $name)
 * @method static bool isConnected(string $name = null)
 * @method static array getStatus(string $name = null)
 * @method static mixed callTool(string $clientName, string $toolName, array $params = [])
 * @method static mixed readResource(string $clientName, string $uri)
 * @method static mixed getPrompt(string $clientName, string $promptName, array $args = [])
 * @method static array listTools(string $clientName = null)
 * @method static array listResources(string $clientName = null)
 * @method static array listPrompts(string $clientName = null)
 * @method static array getRoots(string $clientName = null)
 * @method static array listRootContents(string $clientName, string $uri)
 * @method static mixed createSampling(string $clientName, array $request)
 * @method static mixed sendElicitation(string $clientName, array $request)
 * @method static array getCapabilities(string $clientName = null)
 * @method static void ping(string $clientName = null)
 * @method static mixed completeText(string $clientName, string $text, array $options = [])
 * @method static void cancelOperation(string $clientName, string $operationId)
 * @method static array getProgress(string $clientName, string $operationId)
 *
 * @see \MCP\Laravel\Laravel\ClientManager
 */
class McpClient extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mcp.clients';
    }
}
