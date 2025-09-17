<?php

namespace MCP\Laravel\Exceptions;

/**
 * Exception thrown when attempting to use a client that is not connected.
 */
class ClientNotConnectedException extends McpException
{
    protected string $mcpErrorCode = 'CLIENT_NOT_CONNECTED';

    public function __construct(string $message = 'MCP client is not connected', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
