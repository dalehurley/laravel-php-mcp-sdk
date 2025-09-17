<?php

namespace MCP\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP tool is executed.
 */
class ToolExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $serverName,
        public string $toolName,
        public array $params,
        public array $result,
        public float $executionTime,
        public ?string $userId = null
    ) {}
}
