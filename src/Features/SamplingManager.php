<?php

namespace MCP\Laravel\Features;

/**
 * Manager for MCP sampling feature.
 * 
 * Handles AI model sampling preferences and configuration.
 */
class SamplingManager
{
    protected array $config;
    protected array $preferences = [];

    public function __construct()
    {
        $this->config = config('mcp.features.sampling', []);
        $this->preferences = [
            'temperature' => $this->config['default_temperature'] ?? 0.7,
            'max_tokens' => $this->config['max_tokens'] ?? 1000,
            'top_p' => $this->config['top_p'] ?? 0.9,
            'top_k' => $this->config['top_k'] ?? 50,
        ];
    }

    /**
     * Set sampling preferences.
     */
    public function setPreferences(array $preferences): void
    {
        $this->preferences = array_merge($this->preferences, $preferences);
    }

    /**
     * Get sampling preferences.
     */
    public function getPreferences(): array
    {
        return $this->preferences;
    }

    /**
     * Check if sampling feature is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get sampling configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Create sampling request.
     */
    public function createSamplingRequest(array $options = []): array
    {
        return array_merge($this->preferences, $options);
    }

    /**
     * Reset preferences to defaults.
     */
    public function resetToDefaults(): void
    {
        $this->preferences = [
            'temperature' => $this->config['default_temperature'] ?? 0.7,
            'max_tokens' => $this->config['max_tokens'] ?? 1000,
            'top_p' => $this->config['top_p'] ?? 0.9,
            'top_k' => $this->config['top_k'] ?? 50,
        ];
    }
}
