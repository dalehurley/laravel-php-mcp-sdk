<?php

namespace MCP\Laravel\Utilities;

use Illuminate\Support\Collection;

/**
 * Manages cancellation for MCP operations.
 * 
 * Provides functionality to cancel long-running operations and
 * check cancellation status during execution.
 */
class CancellationManager
{
    protected Collection $cancellationTokens;
    protected array $config;

    public function __construct()
    {
        $this->cancellationTokens = new Collection();
        $this->config = config('mcp.utilities.cancellation', []);
    }

    /**
     * Create a cancellation token for an operation.
     */
    public function createToken(string $operationId): string
    {
        $token = uniqid('cancel_', true);

        $this->cancellationTokens->put($token, [
            'operation_id' => $operationId,
            'cancelled' => false,
            'created_at' => now(),
            'cancelled_at' => null,
            'reason' => null,
        ]);

        return $token;
    }

    /**
     * Cancel an operation by token.
     */
    public function cancel(string $token, ?string $reason = null): bool
    {
        if (!$this->cancellationTokens->has($token)) {
            return false;
        }

        $cancellation = $this->cancellationTokens->get($token);
        $cancellation['cancelled'] = true;
        $cancellation['cancelled_at'] = now();
        $cancellation['reason'] = $reason;

        $this->cancellationTokens->put($token, $cancellation);

        $this->notifyCancellation($token, $cancellation);

        return true;
    }

    /**
     * Cancel an operation by operation ID.
     */
    public function cancelByOperationId(string $operationId, ?string $reason = null): bool
    {
        $token = $this->findTokenByOperationId($operationId);

        if (!$token) {
            return false;
        }

        return $this->cancel($token, $reason);
    }

    /**
     * Check if an operation is cancelled by token.
     */
    public function isCancelled(string $token): bool
    {
        $cancellation = $this->cancellationTokens->get($token);

        return $cancellation ? ($cancellation['cancelled'] ?? false) : false;
    }

    /**
     * Check if an operation is cancelled by operation ID.
     */
    public function isOperationCancelled(string $operationId): bool
    {
        $token = $this->findTokenByOperationId($operationId);

        return $token ? $this->isCancelled($token) : false;
    }

    /**
     * Get cancellation information.
     */
    public function getCancellationInfo(string $token): ?array
    {
        return $this->cancellationTokens->get($token);
    }

    /**
     * Get cancellation reason.
     */
    public function getCancellationReason(string $token): ?string
    {
        $cancellation = $this->getCancellationInfo($token);

        return $cancellation ? $cancellation['reason'] : null;
    }

    /**
     * Remove a cancellation token.
     */
    public function removeToken(string $token): void
    {
        $this->cancellationTokens->forget($token);
    }

    /**
     * Clean up old cancellation tokens.
     */
    public function cleanup(int $olderThanMinutes = 60): int
    {
        $cutoff = now()->subMinutes($olderThanMinutes);
        $removed = 0;

        $toRemove = $this->cancellationTokens->filter(function ($cancellation) use ($cutoff) {
            return $cancellation['created_at'] < $cutoff;
        });

        foreach ($toRemove->keys() as $token) {
            $this->cancellationTokens->forget($token);
            $removed++;
        }

        return $removed;
    }

    /**
     * Get all active (non-cancelled) tokens.
     */
    public function getActiveTokens(): Collection
    {
        return $this->cancellationTokens->filter(function ($cancellation) {
            return !($cancellation['cancelled'] ?? false);
        });
    }

    /**
     * Get all cancelled tokens.
     */
    public function getCancelledTokens(): Collection
    {
        return $this->cancellationTokens->filter(function ($cancellation) {
            return $cancellation['cancelled'] ?? false;
        });
    }

    /**
     * Create a cancellation exception.
     */
    public function createCancellationException(string $token): \Exception
    {
        $reason = $this->getCancellationReason($token) ?? 'Operation was cancelled';

        return new \RuntimeException("Operation cancelled: {$reason}");
    }

    /**
     * Throw if cancelled.
     */
    public function throwIfCancelled(string $token): void
    {
        if ($this->isCancelled($token)) {
            throw $this->createCancellationException($token);
        }
    }

    /**
     * Create a cancellation scope for a callable.
     */
    public function withCancellation(string $operationId, callable $callback): mixed
    {
        $token = $this->createToken($operationId);

        try {
            return $callback($token);
        } finally {
            $this->removeToken($token);
        }
    }

    /**
     * Create a timeout-based cancellation.
     */
    public function withTimeout(string $operationId, int $timeoutSeconds, callable $callback): mixed
    {
        $token = $this->createToken($operationId);

        // Schedule automatic cancellation
        if (function_exists('dispatch')) {
            dispatch(function () use ($token) {
                $this->cancel($token, 'Operation timed out');
            })->delay(now()->addSeconds($timeoutSeconds));
        }

        try {
            return $callback($token);
        } finally {
            $this->removeToken($token);
        }
    }

    /**
     * Get cancellation statistics.
     */
    public function getStatistics(): array
    {
        $all = $this->cancellationTokens;
        $cancelled = $this->getCancelledTokens();
        $active = $this->getActiveTokens();

        return [
            'total_tokens' => $all->count(),
            'active_tokens' => $active->count(),
            'cancelled_tokens' => $cancelled->count(),
            'cancellation_rate' => $all->count() > 0 ? ($cancelled->count() / $all->count()) * 100 : 0,
            'average_lifetime' => $this->calculateAverageLifetime($cancelled),
            'most_common_reason' => $this->getMostCommonCancellationReason($cancelled),
        ];
    }

    /**
     * Find token by operation ID.
     */
    protected function findTokenByOperationId(string $operationId): ?string
    {
        foreach ($this->cancellationTokens as $token => $cancellation) {
            if ($cancellation['operation_id'] === $operationId) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Notify about cancellation.
     */
    protected function notifyCancellation(string $token, array $cancellation): void
    {
        // Fire Laravel event if events are enabled
        if (config('mcp.events.enabled', true) && function_exists('event')) {
            event('mcp.operation.cancelled', [
                'token' => $token,
                'cancellation' => $cancellation,
            ]);
        }

        // Broadcast if broadcasting is enabled
        if (config('mcp.events.broadcast', false) && function_exists('broadcast')) {
            $channel = config('mcp.events.broadcast_channel', 'mcp');
            broadcast(new \Illuminate\Broadcasting\Channel($channel))->with([
                'type' => 'operation_cancelled',
                'token' => $token,
                'operation_id' => $cancellation['operation_id'],
                'reason' => $cancellation['reason'],
            ]);
        }

        // Log cancellation if debugging is enabled
        if (config('mcp.development.debug', false) && function_exists('logger')) {
            logger()->info("MCP Operation Cancelled: {$cancellation['operation_id']}", [
                'token' => $token,
                'reason' => $cancellation['reason'],
            ]);
        }
    }

    /**
     * Calculate average lifetime for cancelled operations.
     */
    protected function calculateAverageLifetime(Collection $cancelled): ?float
    {
        if ($cancelled->isEmpty()) {
            return null;
        }

        $lifetimes = $cancelled->map(function ($cancellation) {
            if (!isset($cancellation['created_at']) || !isset($cancellation['cancelled_at'])) {
                return null;
            }

            return $cancellation['cancelled_at']->diffInSeconds($cancellation['created_at']);
        })->filter();

        return $lifetimes->isEmpty() ? null : $lifetimes->average();
    }

    /**
     * Get the most common cancellation reason.
     */
    protected function getMostCommonCancellationReason(Collection $cancelled): ?string
    {
        if ($cancelled->isEmpty()) {
            return null;
        }

        $reasons = $cancelled->pluck('reason')->filter()->countBy();

        return $reasons->isEmpty() ? null : $reasons->keys()->first();
    }
}
