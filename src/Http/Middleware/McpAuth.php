<?php

namespace MCP\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Authentication middleware for MCP HTTP endpoints.
 * 
 * Handles OAuth 2.1, bearer token, and API key authentication
 * for MCP server HTTP transport.
 */
class McpAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Skip authentication if disabled
        if (!config('mcp.authorization.enabled', false)) {
            return $next($request);
        }

        $provider = config('mcp.authorization.provider', 'bearer');

        try {
            $authenticated = match ($provider) {
                'oauth' => $this->handleOAuth($request),
                'bearer' => $this->handleBearer($request),
                'api_key' => $this->handleApiKey($request),
                default => false,
            };

            if (!$authenticated) {
                return $this->unauthorizedResponse('Authentication required');
            }

            return $next($request);
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth 2.1 authentication.
     */
    protected function handleOAuth(Request $request): bool
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return false;
        }

        // Validate OAuth token using Laravel's authentication system
        if (function_exists('auth') && method_exists(auth(), 'guard')) {
            $guard = auth()->guard('api');

            if ($guard && method_exists($guard, 'setRequest')) {
                $guard->setRequest($request);

                if ($guard->check()) {
                    $user = $guard->user();
                    $request->setUserResolver(fn() => $user);
                    return $this->validateScopes($request, $user);
                }
            }
        }

        return false;
    }

    /**
     * Handle bearer token authentication.
     */
    protected function handleBearer(Request $request): bool
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return false;
        }

        // Simple bearer token validation
        // In production, this should validate against a token store
        $validTokens = config('mcp.authorization.bearer.valid_tokens', []);

        if (empty($validTokens)) {
            // If no tokens configured, accept any non-empty token
            return !empty($token);
        }

        return in_array($token, $validTokens);
    }

    /**
     * Handle API key authentication.
     */
    protected function handleApiKey(Request $request): bool
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return false;
        }

        // Validate API key
        $validKeys = config('mcp.authorization.api_key.valid_keys', []);

        if (empty($validKeys)) {
            // If no keys configured, accept any non-empty key
            return !empty($apiKey);
        }

        return in_array($apiKey, $validKeys);
    }

    /**
     * Extract bearer token from request.
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $header = config('mcp.authorization.bearer.header_name', 'Authorization');
        $authorization = $request->header($header);

        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return null;
    }

    /**
     * Extract API key from request.
     */
    protected function extractApiKey(Request $request): ?string
    {
        $headerName = config('mcp.authorization.api_key.header_name', 'X-MCP-API-Key');
        $queryParam = config('mcp.authorization.api_key.query_param', 'api_key');

        // Try header first
        $apiKey = $request->header($headerName);

        if ($apiKey) {
            return $apiKey;
        }

        // Try query parameter
        return $request->query($queryParam);
    }

    /**
     * Validate required scopes for the request.
     */
    protected function validateScopes(Request $request, $user): bool
    {
        $method = $request->input('method');

        if (!$method) {
            return true; // No specific method, allow
        }

        $requiredScopes = $this->getRequiredScopes($method);

        if (empty($requiredScopes)) {
            return true; // No scopes required
        }

        // Check if user has required scopes using Laravel's authorization system
        if (method_exists($user, 'hasScope')) {
            foreach ($requiredScopes as $scope) {
                if (!$user->hasScope($scope)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get required scopes for a method.
     */
    protected function getRequiredScopes(string $method): array
    {
        $scopeMap = [
            'tools/list' => ['mcp:tools'],
            'tools/call' => ['mcp:tools'],
            'resources/list' => ['mcp:resources'],
            'resources/read' => ['mcp:resources'],
            'prompts/list' => ['mcp:prompts'],
            'prompts/get' => ['mcp:prompts'],
        ];

        return $scopeMap[$method] ?? [];
    }

    /**
     * Create unauthorized response.
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => $message,
            ],
            'id' => null,
        ], 401);
    }
}
