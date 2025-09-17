<?php

namespace MCP\Laravel\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when MCP operation progress is updated.
 */
class ProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $token,
        public array $progress
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel(config('mcp.events.broadcast_channel', 'mcp')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'progress.updated';
    }
}
