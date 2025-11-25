<?php

namespace MCP\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Events\UiActionReceived;
use MCP\Laravel\Exceptions\McpException;

/**
 * HTTP controller for MCP-UI action endpoints.
 *
 * Handles actions dispatched from MCP-UI widgets via postMessage API.
 * Supports tool calls, notifications, prompts, and link actions.
 */
class McpUiActionController extends Controller
{
    protected ?ServerManager $serverManager;

    public function __construct(?ServerManager $serverManager = null)
    {
        $this->serverManager = $serverManager;
    }

    /**
     * Handle a UI action from a widget.
     *
     * Accepts actions from MCP-UI widgets and routes them appropriately:
     * - 'tool': Executes a tool on the specified server
     * - 'notification': Fires an event for the application to handle
     * - 'prompt': Fires an event for the application to handle
     * - 'link': Returns the URL for the client to handle
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate the action request
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:tool,notification,prompt,link',
            'payload' => 'required|array',
            'server' => 'nullable|string',
            'widget_uri' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid action request: ' . $validator->errors()->first(),
                400
            );
        }

        $type = $request->input('type');
        $payload = $request->input('payload');
        $serverName = $request->input('server', config('mcp.ui.default_server') ?? config('mcp.default_server', 'main'));
        $widgetUri = $request->input('widget_uri');

        // Fire the event for all action types
        $userId = auth()->check() ? (string) auth()->id() : null;
        event(new UiActionReceived($type, $payload, $serverName, $userId, $widgetUri));

        // Route the action based on type
        return match ($type) {
            'tool' => $this->handleToolAction($payload, $serverName, $request),
            'notification' => $this->handleNotificationAction($payload),
            'prompt' => $this->handlePromptAction($payload, $serverName, $request),
            'link' => $this->handleLinkAction($payload),
            default => $this->errorResponse("Unknown action type: {$type}", 400),
        };
    }

    /**
     * Handle a tool action from a widget.
     */
    protected function handleToolAction(array $payload, string $serverName, Request $request): JsonResponse
    {
        // Validate tool payload
        $validator = Validator::make($payload, [
            'name' => 'required|string',
            'arguments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid tool action: ' . $validator->errors()->first(),
                400
            );
        }

        $toolName = $payload['name'];
        $arguments = $payload['arguments'] ?? [];

        // Resolve ServerManager if not injected
        $serverManager = $this->serverManager ?? app(ServerManager::class);

        try {
            // Check if server exists
            if (!$serverManager->exists($serverName)) {
                return $this->errorResponse("Server '{$serverName}' not found", 404);
            }

            $mcpServer = $serverManager->get($serverName);
            $tools = $mcpServer->getTools();

            // Check if tool exists
            if (!isset($tools[$toolName])) {
                return $this->errorResponse("Tool '{$toolName}' not found on server '{$serverName}'", 404);
            }

            // Execute the tool
            $tool = $tools[$toolName];
            $result = call_user_func($tool['handler'], $arguments);

            return response()->json([
                'success' => true,
                'type' => 'tool',
                'result' => $result,
            ]);
        } catch (McpException $e) {
            return $this->errorResponse($e->getMessage(), 500, $e->getMcpErrorCode());
        } catch (\Exception $e) {
            return $this->errorResponse("Tool execution failed: {$e->getMessage()}", 500);
        }
    }

    /**
     * Handle a notification action from a widget.
     */
    protected function handleNotificationAction(array $payload): JsonResponse
    {
        // Validate notification payload
        $validator = Validator::make($payload, [
            'message' => 'required|string',
            'level' => 'nullable|string|in:info,success,warning,error,debug',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid notification action: ' . $validator->errors()->first(),
                400
            );
        }

        // The event has already been fired, so just acknowledge
        return response()->json([
            'success' => true,
            'type' => 'notification',
            'message' => 'Notification received',
        ]);
    }

    /**
     * Handle a prompt action from a widget.
     */
    protected function handlePromptAction(array $payload, string $serverName, Request $request): JsonResponse
    {
        // Validate prompt payload
        $validator = Validator::make($payload, [
            'name' => 'required|string',
            'arguments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid prompt action: ' . $validator->errors()->first(),
                400
            );
        }

        $promptName = $payload['name'];
        $arguments = $payload['arguments'] ?? [];

        // Resolve ServerManager if not injected
        $serverManager = $this->serverManager ?? app(ServerManager::class);

        try {
            // Check if server exists
            if (!$serverManager->exists($serverName)) {
                return $this->errorResponse("Server '{$serverName}' not found", 404);
            }

            $mcpServer = $serverManager->get($serverName);
            $prompts = $mcpServer->getPrompts();

            // Check if prompt exists
            if (!isset($prompts[$promptName])) {
                return $this->errorResponse("Prompt '{$promptName}' not found on server '{$serverName}'", 404);
            }

            // Execute the prompt
            $prompt = $prompts[$promptName];
            $result = call_user_func($prompt['handler'], $arguments);

            return response()->json([
                'success' => true,
                'type' => 'prompt',
                'result' => $result,
            ]);
        } catch (McpException $e) {
            return $this->errorResponse($e->getMessage(), 500, $e->getMcpErrorCode());
        } catch (\Exception $e) {
            return $this->errorResponse("Prompt execution failed: {$e->getMessage()}", 500);
        }
    }

    /**
     * Handle a link action from a widget.
     */
    protected function handleLinkAction(array $payload): JsonResponse
    {
        // Validate link payload
        $validator = Validator::make($payload, [
            'url' => 'required|url',
            'target' => 'nullable|string|in:_blank,_self,_parent,_top',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid link action: ' . $validator->errors()->first(),
                400
            );
        }

        // Return the URL for the client to handle
        // The event has already been fired
        return response()->json([
            'success' => true,
            'type' => 'link',
            'url' => $payload['url'],
            'target' => $payload['target'] ?? '_blank',
        ]);
    }

    /**
     * Create an error response.
     */
    protected function errorResponse(string $message, int $httpCode = 400, ?string $mcpCode = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $mcpCode ?? $httpCode,
                'message' => $message,
            ],
        ], $httpCode);
    }
}

