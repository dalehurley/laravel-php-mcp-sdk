<?php

namespace MCP\Laravel\Exceptions;

/**
 * Exception thrown when a requested MCP server is not found.
 */
class ServerNotFoundException extends McpException
{
    protected string $mcpErrorCode = 'SERVER_NOT_FOUND';

    public function __construct(string $message = 'MCP server not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
