<?php

namespace MCP\Laravel\Features;

use Illuminate\Support\Collection;

/**
 * Manager for MCP roots feature.
 * 
 * Handles hierarchical resource navigation and root management.
 */
class RootsManager
{
    protected Collection $roots;
    protected array $config;

    public function __construct()
    {
        $this->roots = new Collection();
        $this->config = config('mcp.features.roots', []);
    }

    /**
     * Add a root to the collection.
     */
    public function addRoot(string $uri, string $name, array $metadata = []): void
    {
        $this->roots->put($uri, [
            'uri' => $uri,
            'name' => $name,
            'metadata' => $metadata,
            'added_at' => now(),
        ]);
    }

    /**
     * Get all roots.
     */
    public function getRoots(): array
    {
        return $this->roots->values()->toArray();
    }

    /**
     * Check if roots feature is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get roots configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Remove a root.
     */
    public function removeRoot(string $uri): void
    {
        $this->roots->forget($uri);
    }

    /**
     * Check if a root exists.
     */
    public function hasRoot(string $uri): bool
    {
        return $this->roots->has($uri);
    }

    /**
     * Clear all roots.
     */
    public function clearRoots(): void
    {
        $this->roots = new Collection();
    }
}
