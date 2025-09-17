<?php

namespace MCP\Laravel\Exceptions;

use Exception;

/**
 * Base exception for all MCP-related errors in Laravel.
 */
class McpException extends Exception
{
    protected string $mcpErrorCode = 'MCP_ERROR';

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the MCP-specific error code.
     */
    public function getMcpErrorCode(): string
    {
        return $this->mcpErrorCode;
    }

    /**
     * Set the MCP-specific error code.
     */
    public function setMcpErrorCode(string $code): void
    {
        $this->mcpErrorCode = $code;
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'error_code' => $this->getMcpErrorCode(),
            'message' => $this->getMessage(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}
