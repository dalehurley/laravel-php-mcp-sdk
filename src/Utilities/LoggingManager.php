<?php

namespace MCP\Laravel\Utilities;

use Illuminate\Support\Collection;

/**
 * Manager for MCP logging operations.
 */
class LoggingManager
{
    protected Collection $logs;
    protected array $config;

    public function __construct()
    {
        $this->logs = new Collection();
        $this->config = config('mcp.utilities.logging', []);
    }

    /**
     * Log an MCP operation.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => now(),
        ];

        $this->logs->push($entry);

        // Keep only the last N entries
        $maxEntries = $this->config['max_entries'] ?? 1000;
        if ($this->logs->count() > $maxEntries) {
            $this->logs->shift();
        }

        // Log to Laravel's logging system if available
        if (function_exists('logger')) {
            $channel = $this->config['channel'] ?? 'mcp';
            logger($channel)->log($level, "[MCP] {$message}", $context);
        }
    }

    /**
     * Get all log entries.
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    /**
     * Get logs by level.
     */
    public function getLogsByLevel(string $level): Collection
    {
        return $this->logs->where('level', $level);
    }

    /**
     * Clear all logs.
     */
    public function clearLogs(): void
    {
        $this->logs = new Collection();
    }

    /**
     * Check if logging is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get logging configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
