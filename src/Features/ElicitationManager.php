<?php

namespace MCP\Laravel\Features;

use Illuminate\Support\Collection;

/**
 * Manager for MCP elicitation feature.
 * 
 * Handles interactive user input and elicitation requests.
 */
class ElicitationManager
{
    protected Collection $activeElicitations;
    protected array $config;

    public function __construct()
    {
        $this->activeElicitations = new Collection();
        $this->config = config('mcp.features.elicitation', []);
    }

    /**
     * Create a new elicitation request.
     */
    public function createElicitation(string $prompt, array $options = []): string
    {
        $id = uniqid('elicitation_', true);

        $this->activeElicitations->put($id, [
            'id' => $id,
            'prompt' => $prompt,
            'options' => $options,
            'created_at' => now(),
            'status' => 'pending',
            'response' => null,
        ]);

        return $id;
    }

    /**
     * Get an elicitation by ID.
     */
    public function getElicitation(string $id): ?array
    {
        return $this->activeElicitations->get($id);
    }

    /**
     * Complete an elicitation with a response.
     */
    public function completeElicitation(string $id, string $response): void
    {
        if ($this->activeElicitations->has($id)) {
            $elicitation = $this->activeElicitations->get($id);
            $elicitation['response'] = $response;
            $elicitation['status'] = 'completed';
            $elicitation['completed_at'] = now();

            $this->activeElicitations->put($id, $elicitation);
        }
    }

    /**
     * Cancel an elicitation.
     */
    public function cancelElicitation(string $id): void
    {
        if ($this->activeElicitations->has($id)) {
            $elicitation = $this->activeElicitations->get($id);
            $elicitation['status'] = 'cancelled';
            $elicitation['cancelled_at'] = now();

            $this->activeElicitations->put($id, $elicitation);
        }
    }

    /**
     * Get all active elicitations.
     */
    public function getActiveElicitations(): Collection
    {
        return $this->activeElicitations->filter(function ($elicitation) {
            return $elicitation['status'] === 'pending';
        });
    }

    /**
     * Check if elicitation feature is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get elicitation configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Clean up old elicitations.
     */
    public function cleanup(int $olderThanMinutes = 60): int
    {
        $cutoff = now()->subMinutes($olderThanMinutes);
        $removed = 0;

        $toRemove = $this->activeElicitations->filter(function ($elicitation) use ($cutoff) {
            return $elicitation['created_at'] < $cutoff;
        });

        foreach ($toRemove->keys() as $id) {
            $this->activeElicitations->forget($id);
            $removed++;
        }

        return $removed;
    }
}
