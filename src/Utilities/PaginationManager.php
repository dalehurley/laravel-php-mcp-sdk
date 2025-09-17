<?php

namespace MCP\Laravel\Utilities;

/**
 * Manager for MCP pagination operations.
 */
class PaginationManager
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('mcp.utilities.pagination', []);
    }

    /**
     * Paginate results for MCP responses.
     */
    public function paginate(string $uri, array $items, array $paginationInfo): array
    {
        $defaultLimit = $this->config['default_limit'] ?? 50;
        $maxLimit = $this->config['max_limit'] ?? 1000;

        $total = $paginationInfo['total'] ?? count($items);
        $perPage = min($paginationInfo['per_page'] ?? $defaultLimit, $maxLimit);
        $currentPage = $paginationInfo['current_page'] ?? 1;
        $lastPage = $paginationInfo['last_page'] ?? ceil($total / $perPage);

        return [
            'contents' => array_map(function ($item) {
                return [
                    'type' => 'text',
                    'text' => is_string($item) ? $item : json_encode($item),
                ];
            }, $items),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'has_more' => $currentPage < $lastPage,
                'next_uri' => $currentPage < $lastPage ? $this->buildNextUri($uri, $currentPage + 1) : null,
                'prev_uri' => $currentPage > 1 ? $this->buildNextUri($uri, $currentPage - 1) : null,
            ],
        ];
    }

    /**
     * Extract pagination parameters from URI.
     */
    public function extractPaginationParams(string $uri): array
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        $params = [];

        if ($query) {
            parse_str($query, $params);
        }

        return [
            'page' => (int) ($params['page'] ?? 1),
            'limit' => min((int) ($params['limit'] ?? $this->config['default_limit'] ?? 50), $this->config['max_limit'] ?? 1000),
        ];
    }

    /**
     * Check if pagination is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get pagination configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Build next/previous URI with page parameter.
     */
    protected function buildNextUri(string $baseUri, int $page): string
    {
        $parts = parse_url($baseUri);
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['page'] = $page;

        $newUri = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $newUri .= ':' . $parts['port'];
        }
        $newUri .= $parts['path'] ?? '';
        $newUri .= '?' . http_build_query($query);

        return $newUri;
    }
}
