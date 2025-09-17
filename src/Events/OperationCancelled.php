<?php

namespace MCP\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP operation is cancelled.
 */
class OperationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $token,
        public string $reason = 'User cancelled'
    ) {}
}
