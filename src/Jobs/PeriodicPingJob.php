<?php

namespace MCP\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Background job for periodic MCP server/client pings.
 */
class PeriodicPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $connectionId,
        private string $type, // 'server' or 'client'
        private int $interval = 30
    ) {}

    public function handle(): void
    {
        $pingManager = app(\MCP\Laravel\Utilities\PingManager::class);

        try {
            // Perform the ping
            $result = $pingManager->ping($this->connectionId);

            if ($result) {
                // Update last ping time
                Cache::put("mcp:ping:last:{$this->connectionId}", now(), 3600);
            }

            // Check if monitoring should continue
            if (!Cache::get("mcp:ping:stop:{$this->connectionId}", false)) {
                // Schedule next ping
                self::dispatch($this->connectionId, $this->type, $this->interval)
                    ->delay(now()->addSeconds($this->interval));
            }
        } catch (\Exception $e) {
            // Log ping failure
            logger()->warning("MCP ping failed for {$this->connectionId}: {$e->getMessage()}");

            // Continue pinging even on failure (connection might recover)
            if (!Cache::get("mcp:ping:stop:{$this->connectionId}", false)) {
                self::dispatch($this->connectionId, $this->type, $this->interval)
                    ->delay(now()->addSeconds($this->interval * 2)); // Back off on failure
            }
        }
    }

    /**
     * Start periodic pinging for a connection.
     */
    public static function start(string $connectionId, string $type, int $interval = 30): void
    {
        Cache::forget("mcp:ping:stop:{$connectionId}");
        self::dispatch($connectionId, $type, $interval);
    }

    /**
     * Stop periodic pinging for a connection.
     */
    public static function stop(string $connectionId): void
    {
        Cache::put("mcp:ping:stop:{$connectionId}", true, 3600);
    }
}
