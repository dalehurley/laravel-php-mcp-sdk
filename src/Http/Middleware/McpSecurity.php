<?php

namespace MCP\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security middleware for MCP HTTP endpoints.
 * 
 * Implements security best practices including CORS, rate limiting,
 * input validation, and request sanitization.
 */
class McpSecurity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pre-request security checks
        if (!$this->validateRequestSize($request)) {
            return response()->json(['error' => 'Request too large'], 413);
        }

        if (!$this->validateMcpRequest($request)) {
            return response()->json(['error' => 'Invalid MCP request'], 400);
        }

        if ($this->detectSuspiciousActivity($request)) {
            return response()->json(['error' => 'Suspicious activity detected'], 403);
        }

        if (!$this->checkRateLimit($request)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Sanitize input
        $this->sanitizeInput($request);

        // Handle preflight requests before processing
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
            $this->handleCors($request, $response);
            return $response;
        }

        // Process the request
        $response = $next($request);

        // Post-request security headers
        $this->addSecurityHeaders($response);
        $this->handleCors($request, $response);

        return $response;
    }

    /**
     * Add security headers to the response.
     */
    protected function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'none'; object-src 'none';",
        ];

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }
    }

    /**
     * Handle CORS for MCP endpoints.
     */
    protected function handleCors(Request $request, Response $response): void
    {
        if (!config('mcp.transports.http.security.cors_enabled', true)) {
            return;
        }

        $allowedOrigins = config('mcp.transports.http.security.allowed_origins', ['*']);
        $origin = $request->headers->get('Origin');

        // Handle preflight requests properly
        if ($request->isMethod('OPTIONS')) {
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-MCP-API-Key, X-Requested-With');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->setContent(''); // Empty content for preflight
            $response->setStatusCode(200);
        }

        // Set CORS headers for all requests
        if (in_array('*', $allowedOrigins) || ($origin && in_array($origin, $allowedOrigins))) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * Validate request structure for MCP protocol.
     */
    protected function validateMcpRequest(Request $request): bool
    {
        // Skip validation for non-JSON requests (GET endpoints, etc.)
        if (!$request->isJson()) {
            return true;
        }

        // Skip validation if request doesn't contain JSON data
        try {
            $data = $request->json()->all();
        } catch (\Exception $e) {
            // If JSON parsing fails, let it pass through for better error handling
            return true;
        }

        // Only validate JSON-RPC structure if jsonrpc field is present
        if (!isset($data['jsonrpc'])) {
            return true; // Not a JSON-RPC request, let it pass
        }

        // If jsonrpc field is present, validate JSON-RPC structure
        if ($data['jsonrpc'] !== '2.0') {
            return false;
        }

        // Must have either method (request) or result/error (response)
        if (!isset($data['method']) && !isset($data['result']) && !isset($data['error'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize input data.
     */
    protected function sanitizeInput(Request $request): void
    {
        if (!$request->isJson()) {
            return;
        }

        try {
            $data = $request->json()->all();
            $sanitized = $this->recursiveSanitize($data);

            // Replace the request data
            $request->json()->replace($sanitized);
        } catch (\Exception $e) {
            // If JSON parsing fails, skip sanitization
            return;
        }
    }

    /**
     * Recursively sanitize data.
     */
    protected function recursiveSanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'recursiveSanitize'], $data);
        }

        if (is_string($data)) {
            // Remove potentially dangerous characters
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);

            // Limit string length
            $maxLength = config('mcp.security.max_string_length', 10000);
            if (strlen($data) > $maxLength) {
                $data = substr($data, 0, $maxLength);
            }
        }

        return $data;
    }

    /**
     * Check request size limits.
     */
    protected function validateRequestSize(Request $request): bool
    {
        $maxSize = config('mcp.security.max_request_size', 1024 * 1024); // 1MB default
        $contentLength = $request->header('Content-Length');

        if ($contentLength && $contentLength > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Log security events.
     */
    protected function logSecurityEvent(string $event, Request $request, array $context = []): void
    {
        if (!config('mcp.development.log_security_events', false)) {
            return;
        }

        $logData = array_merge([
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ], $context);

        if (function_exists('logger')) {
            logger()->warning("MCP Security Event: {$event}", $logData);
        }
    }

    /**
     * Check for suspicious patterns.
     */
    protected function detectSuspiciousActivity(Request $request): bool
    {
        $suspiciousPatterns = [
            // SQL injection patterns
            '/(\bunion\b|\bselect\b|\binsert\b|\bdelete\b|\bdrop\b|\bupdate\b)/i',

            // XSS patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',

            // Path traversal
            '/\.\.\//',

            // Command injection
            '/(\b(exec|system|shell_exec|passthru|eval)\s*\()/i',
        ];

        $content = $request->getContent();

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logSecurityEvent('suspicious_pattern_detected', $request, [
                    'pattern' => $pattern,
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Rate limiting check.
     */
    protected function checkRateLimit(Request $request): bool
    {
        $rateLimitConfig = config('mcp.transports.http.security.rate_limiting', '60,1');

        if (!$rateLimitConfig || $rateLimitConfig === 'disabled') {
            return true;
        }

        // Parse rate limit config (requests,minutes)
        [$requests, $minutes] = explode(',', $rateLimitConfig);

        $key = 'mcp_rate_limit:' . $request->ip();

        if (function_exists('cache')) {
            $attempts = cache()->get($key, 0);

            if ($attempts >= (int) $requests) {
                $this->logSecurityEvent('rate_limit_exceeded', $request, [
                    'attempts' => $attempts,
                    'limit' => $requests,
                ]);
                return false;
            }

            cache()->put($key, $attempts + 1, now()->addMinutes((int) $minutes));
        }

        return true;
    }
}
