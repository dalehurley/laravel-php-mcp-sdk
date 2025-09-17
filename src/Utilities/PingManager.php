<?php

namespace MCP\Laravel\Utilities;

use Illuminate\Support\Collection;

/**
 * Manages ping operations for MCP connections.
 * 
 * Provides functionality to monitor connection health and
 * perform periodic heartbeat checks.
 */
class PingManager
{
    protected Collection $pingHistory;
    protected array $config;

    public function __construct()
    {
        $this->pingHistory = new Collection();
        $this->config = config('mcp.utilities.ping', []);
    }

    /**
     * Ping a connection and record the result.
     */
    public function ping(string $connectionId, callable $pingFunction): array
    {
        $startTime = microtime(true);
        $success = false;
        $error = null;

        try {
            $pingFunction();
            $success = true;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $pingResult = [
            'connection_id' => $connectionId,
            'timestamp' => now(),
            'success' => $success,
            'response_time_ms' => round($responseTime, 2),
            'error' => $error,
        ];

        $this->recordPing($connectionId, $pingResult);

        return $pingResult;
    }

    /**
     * Record a ping result.
     */
    public function recordPing(string $connectionId, array $pingResult): void
    {
        if (!$this->pingHistory->has($connectionId)) {
            $this->pingHistory->put($connectionId, new Collection());
        }

        $connectionHistory = $this->pingHistory->get($connectionId);
        $connectionHistory->push($pingResult);

        // Keep only the last N ping results
        $maxHistory = $this->config['max_history'] ?? 100;
        if ($connectionHistory->count() > $maxHistory) {
            $connectionHistory->shift();
        }

        $this->pingHistory->put($connectionId, $connectionHistory);

        $this->notifyPingResult($connectionId, $pingResult);
    }

    /**
     * Get ping history for a connection.
     */
    public function getHistory(string $connectionId, int $limit = null): Collection
    {
        $history = $this->pingHistory->get($connectionId, new Collection());

        return $limit ? $history->take(-$limit) : $history;
    }

    /**
     * Get the latest ping result for a connection.
     */
    public function getLatest(string $connectionId): ?array
    {
        $history = $this->pingHistory->get($connectionId);

        return $history ? $history->last() : null;
    }

    /**
     * Check if a connection is healthy based on recent pings.
     */
    public function isHealthy(string $connectionId, int $lookbackMinutes = 5): bool
    {
        $history = $this->getHistory($connectionId);

        if ($history->isEmpty()) {
            return false;
        }

        // Check recent pings within the lookback period
        $cutoff = now()->subMinutes($lookbackMinutes);
        $recentPings = $history->filter(function ($ping) use ($cutoff) {
            return $ping['timestamp'] >= $cutoff;
        });

        if ($recentPings->isEmpty()) {
            return false;
        }

        // Consider healthy if success rate is above threshold
        $successRate = $recentPings->where('success', true)->count() / $recentPings->count();
        $healthThreshold = $this->config['health_threshold'] ?? 0.8;

        return $successRate >= $healthThreshold;
    }

    /**
     * Get connection statistics.
     */
    public function getStatistics(string $connectionId): array
    {
        $history = $this->pingHistory->get($connectionId, new Collection());

        if ($history->isEmpty()) {
            return [
                'total_pings' => 0,
                'success_rate' => 0,
                'average_response_time' => null,
                'last_ping' => null,
                'is_healthy' => false,
            ];
        }

        $successful = $history->where('success', true);
        $responseTimes = $successful->pluck('response_time_ms');

        return [
            'total_pings' => $history->count(),
            'successful_pings' => $successful->count(),
            'failed_pings' => $history->where('success', false)->count(),
            'success_rate' => round(($successful->count() / $history->count()) * 100, 2),
            'average_response_time' => $responseTimes->isEmpty() ? null : round($responseTimes->average(), 2),
            'min_response_time' => $responseTimes->isEmpty() ? null : $responseTimes->min(),
            'max_response_time' => $responseTimes->isEmpty() ? null : $responseTimes->max(),
            'last_ping' => $history->last(),
            'is_healthy' => $this->isHealthy($connectionId),
        ];
    }

    /**
     * Get statistics for all connections.
     */
    public function getAllStatistics(): array
    {
        $stats = [];

        foreach ($this->pingHistory->keys() as $connectionId) {
            $stats[$connectionId] = $this->getStatistics($connectionId);
        }

        return $stats;
    }

    /**
     * Clear ping history for a connection.
     */
    public function clearHistory(string $connectionId): void
    {
        $this->pingHistory->forget($connectionId);
    }

    /**
     * Clear all ping history.
     */
    public function clearAllHistory(): void
    {
        $this->pingHistory = new Collection();
    }

    /**
     * Clean up old ping history.
     */
    public function cleanup(int $olderThanHours = 24): int
    {
        $cutoff = now()->subHours($olderThanHours);
        $removed = 0;

        foreach ($this->pingHistory as $connectionId => $history) {
            $originalCount = $history->count();

            $filtered = $history->filter(function ($ping) use ($cutoff) {
                return $ping['timestamp'] >= $cutoff;
            });

            $this->pingHistory->put($connectionId, $filtered);
            $removed += $originalCount - $filtered->count();
        }

        return $removed;
    }

    /**
     * Start periodic ping monitoring for a connection.
     */
    public function startMonitoring(string $connectionId, callable $pingFunction, int $intervalSeconds = null): void
    {
        $interval = $intervalSeconds ?? $this->config['interval'] ?? 30;

        // Use Laravel's queue system for periodic monitoring
        if (function_exists('dispatch')) {
            dispatch(new \MCP\Laravel\Jobs\PeriodicPingJob($connectionId, $pingFunction, $interval))
                ->onQueue(config('mcp.queue.queue', 'mcp'));
        } else {
            // Fallback: log that monitoring was requested but not started
            if (function_exists('logger')) {
                logger()->warning("Periodic ping monitoring requested for {$connectionId} but queue system not available");
            }
        }
    }

    /**
     * Stop monitoring for a connection.
     */
    public function stopMonitoring(string $connectionId): void
    {
        // Mark monitoring as stopped in cache
        if (function_exists('cache')) {
            cache()->put("mcp:ping:stop:{$connectionId}", true, 3600);
        }

        // Log the stop request
        if (function_exists('logger')) {
            logger()->info("Ping monitoring stopped for connection: {$connectionId}");
        }

        // Note: Actual job cancellation would require job ID tracking
        // This is a simplified implementation for the Laravel wrapper
    }

    /**
     * Get unhealthy connections.
     */
    public function getUnhealthyConnections(): array
    {
        $unhealthy = [];

        foreach ($this->pingHistory->keys() as $connectionId) {
            if (!$this->isHealthy($connectionId)) {
                $unhealthy[] = [
                    'connection_id' => $connectionId,
                    'statistics' => $this->getStatistics($connectionId),
                ];
            }
        }

        return $unhealthy;
    }

    /**
     * Notify about ping results.
     */
    protected function notifyPingResult(string $connectionId, array $pingResult): void
    {
        // Fire Laravel event if events are enabled
        if (config('mcp.events.enabled', true) && function_exists('event')) {
            event('mcp.ping.completed', [
                'connection_id' => $connectionId,
                'result' => $pingResult,
            ]);
        }

        // Log failed pings
        if (!$pingResult['success'] && function_exists('logger')) {
            logger()->warning("MCP Ping Failed: {$connectionId}", [
                'error' => $pingResult['error'],
                'response_time' => $pingResult['response_time_ms'],
            ]);
        }

        // Log slow pings
        $slowThreshold = $this->config['slow_threshold_ms'] ?? 1000;
        if ($pingResult['success'] && $pingResult['response_time_ms'] > $slowThreshold && function_exists('logger')) {
            logger()->info("MCP Slow Ping: {$connectionId}", [
                'response_time' => $pingResult['response_time_ms'],
                'threshold' => $slowThreshold,
            ]);
        }
    }

    /**
     * Perform periodic ping (would be called by scheduled job).
     */
    protected function performPeriodicPing(string $connectionId, callable $pingFunction, int $interval): void
    {
        // This is a simplified version - in practice, this would be handled
        // by Laravel's task scheduler or a queue worker
        while (true) {
            $this->ping($connectionId, $pingFunction);
            sleep($interval);
        }
    }
}
