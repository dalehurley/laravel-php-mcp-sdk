<?php

namespace MCP\Laravel\Exceptions;

/**
 * Exception thrown when a requested MCP client is not found.
 */
class ClientNotFoundException extends McpException
{
    protected string $mcpErrorCode = 'CLIENT_NOT_FOUND';

    public function __construct(string $message = 'MCP client not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
