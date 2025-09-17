<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Server Configuration
    |--------------------------------------------------------------------------
    |
    | This value is the name of your default MCP server configuration. This
    | server will be used when no specific server name is provided to the
    | MCP facade methods.
    |
    */

    'default_server' => env('MCP_DEFAULT_SERVER', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Default Client Configuration
    |--------------------------------------------------------------------------
    |
    | This value is the name of your default MCP client configuration. This
    | client will be used when no specific client name is provided to the
    | MCP facade methods.
    |
    */

    'default_client' => env('MCP_DEFAULT_CLIENT', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Multiple Server Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure multiple MCP server instances. Each server can
    | have its own transport, capabilities, and component discovery settings.
    | This allows you to run multiple specialized MCP servers from a single
    | Laravel application.
    |
    */

    'servers' => [
        'main' => [
            'name' => env('MCP_SERVER_NAME', config('app.name', 'Laravel') . ' MCP Server'),
            'version' => env('MCP_SERVER_VERSION', '1.0.0'),
            'transport' => env('MCP_TRANSPORT', 'stdio'),
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
                'logging' => [],
            ],
            'tools' => [
                'discover' => [app_path('Mcp/Tools')],
                'auto_register' => true,
            ],
            'resources' => [
                'discover' => [app_path('Mcp/Resources')],
                'auto_register' => true,
            ],
            'prompts' => [
                'discover' => [app_path('Mcp/Prompts')],
                'auto_register' => true,
            ],
        ],

        'api' => [
            'name' => config('app.name', 'Laravel') . ' API Server',
            'version' => '1.0.0',
            'transport' => 'http',
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
                'logging' => [],
            ],
            'tools' => [
                'discover' => [app_path('Mcp/Api/Tools')],
                'auto_register' => true,
            ],
            'resources' => [
                'discover' => [app_path('Mcp/Api/Resources')],
                'auto_register' => true,
            ],
            'prompts' => [
                'discover' => [app_path('Mcp/Api/Prompts')],
                'auto_register' => true,
            ],
        ],

        'websocket' => [
            'name' => config('app.name', 'Laravel') . ' WebSocket Server',
            'version' => '1.0.0',
            'transport' => 'websocket',
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
                'logging' => [],
            ],
            'tools' => [
                'discover' => [app_path('Mcp/WebSocket/Tools')],
                'auto_register' => true,
            ],
            'resources' => [
                'discover' => [app_path('Mcp/WebSocket/Resources')],
                'auto_register' => true,
            ],
            'prompts' => [
                'discover' => [app_path('Mcp/WebSocket/Prompts')],
                'auto_register' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multiple Client Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure multiple MCP client connections. Each client can
    | connect to different MCP servers using different transports and have
    | different capabilities and retry policies.
    |
    */

    'clients' => [
        'main' => [
            'name' => env('MCP_CLIENT_NAME', config('app.name', 'Laravel') . ' MCP Client'),
            'version' => env('MCP_CLIENT_VERSION', '1.0.0'),
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
            ],
            'timeout' => env('MCP_CLIENT_TIMEOUT', 30000),
            'retry' => [
                'enabled' => true,
                'max_attempts' => 3,
                'delay_ms' => 1000,
                'backoff_multiplier' => 2,
            ],
        ],

        'remote' => [
            'name' => config('app.name', 'Laravel') . ' Remote Client',
            'version' => '1.0.0',
            'capabilities' => [
                'experimental' => [],
                'sampling' => [],
                'roots' => ['listChanged' => true],
            ],
            'timeout' => 60000,
            'retry' => [
                'enabled' => true,
                'max_attempts' => 5,
                'delay_ms' => 2000,
                'backoff_multiplier' => 1.5,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport Configurations
    |--------------------------------------------------------------------------
    |
    | Configure the various transport layers that MCP can use. Each transport
    | has its own specific configuration options and security settings.
    |
    */

    'transports' => [
        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
            'buffer_size' => 8192,
            'timeout' => 30,
        ],

        'http' => [
            'enabled' => env('MCP_HTTP_ENABLED', true),
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
            'port' => env('MCP_HTTP_PORT', 3000),
            'routes' => [
                'prefix' => env('MCP_ROUTE_PREFIX', 'mcp'),
                'middleware' => ['api', 'mcp.auth', 'mcp.security'],
            ],
            'security' => [
                'cors_enabled' => env('MCP_CORS_ENABLED', true),
                'csrf_protection' => env('MCP_CSRF_PROTECTION', true),
                'rate_limiting' => env('MCP_RATE_LIMITING', '60,1'),
                'allowed_origins' => explode(',', env('MCP_ALLOWED_ORIGINS', '*')),
            ],
        ],

        'websocket' => [
            'enabled' => env('MCP_WEBSOCKET_ENABLED', false),
            'host' => env('MCP_WEBSOCKET_HOST', '127.0.0.1'),
            'port' => env('MCP_WEBSOCKET_PORT', 3001),
            'heartbeat_interval' => 30,
            'max_connections' => env('MCP_WEBSOCKET_MAX_CONNECTIONS', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure OAuth 2.1 and other authorization mechanisms for MCP servers.
    | This includes scope definitions, token lifetimes, and client registration.
    |
    */

    'authorization' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'provider' => env('MCP_AUTH_PROVIDER', 'bearer'), // bearer, oauth, api_key

        'oauth' => [
            'client_registration' => env('MCP_OAUTH_CLIENT_REGISTRATION', 'dynamic'),
            'scopes' => [
                'mcp:tools' => 'Access to MCP tools',
                'mcp:resources' => 'Access to MCP resources',
                'mcp:prompts' => 'Access to MCP prompts',
                'mcp:roots' => 'Access to MCP roots',
                'mcp:sampling' => 'Access to MCP sampling',
                'mcp:logging' => 'Access to MCP logging',
                'mcp:admin' => 'Administrative access to MCP server',
            ],
            'token_lifetime' => env('MCP_TOKEN_LIFETIME', 3600),
            'refresh_token_lifetime' => env('MCP_REFRESH_TOKEN_LIFETIME', 86400),
            'pkce_required' => env('MCP_PKCE_REQUIRED', true),
        ],

        'api_key' => [
            'header_name' => env('MCP_API_KEY_HEADER', 'X-MCP-API-Key'),
            'query_param' => env('MCP_API_KEY_QUERY', 'api_key'),
        ],

        'bearer' => [
            'header_name' => env('MCP_BEARER_HEADER', 'Authorization'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Utilities Configuration
    |--------------------------------------------------------------------------
    |
    | Configure MCP utilities like cancellation, ping, progress reporting,
    | text completion, logging, and pagination. These enhance the MCP
    | protocol with additional capabilities.
    |
    */

    'utilities' => [
        'cancellation' => [
            'enabled' => env('MCP_CANCELLATION_ENABLED', true),
            'timeout' => env('MCP_CANCELLATION_TIMEOUT', 30),
        ],

        'ping' => [
            'enabled' => env('MCP_PING_ENABLED', true),
            'interval' => env('MCP_PING_INTERVAL', 30),
            'timeout' => env('MCP_PING_TIMEOUT', 10),
        ],

        'progress' => [
            'enabled' => env('MCP_PROGRESS_ENABLED', true),
            'update_interval' => env('MCP_PROGRESS_UPDATE_INTERVAL', 1),
            'buffer_size' => env('MCP_PROGRESS_BUFFER_SIZE', 100),
        ],

        'completion' => [
            'enabled' => env('MCP_COMPLETION_ENABLED', true),
            'max_completions' => env('MCP_MAX_COMPLETIONS', 10),
            'timeout' => env('MCP_COMPLETION_TIMEOUT', 5),
        ],

        'logging' => [
            'enabled' => env('MCP_LOGGING_ENABLED', true),
            'level' => env('MCP_LOG_LEVEL', 'info'),
            'channel' => env('MCP_LOG_CHANNEL', 'mcp'),
            'max_entries' => env('MCP_LOG_MAX_ENTRIES', 1000),
        ],

        'pagination' => [
            'enabled' => env('MCP_PAGINATION_ENABLED', true),
            'default_limit' => env('MCP_PAGINATION_LIMIT', 50),
            'max_limit' => env('MCP_PAGINATION_MAX_LIMIT', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configure advanced MCP client features like roots (for hierarchical
    | resource navigation), sampling (for AI model preferences), and
    | elicitation (for interactive user input).
    |
    */

    'features' => [
        'roots' => [
            'enabled' => env('MCP_ROOTS_ENABLED', true),
            'auto_refresh' => env('MCP_ROOTS_AUTO_REFRESH', true),
            'refresh_interval' => env('MCP_ROOTS_REFRESH_INTERVAL', 300),
            'cache_ttl' => env('MCP_ROOTS_CACHE_TTL', 600),
        ],

        'sampling' => [
            'enabled' => env('MCP_SAMPLING_ENABLED', true),
            'default_temperature' => env('MCP_SAMPLING_TEMPERATURE', 0.7),
            'max_tokens' => env('MCP_SAMPLING_MAX_TOKENS', 1000),
            'top_p' => env('MCP_SAMPLING_TOP_P', 0.9),
            'top_k' => env('MCP_SAMPLING_TOP_K', 50),
        ],

        'elicitation' => [
            'enabled' => env('MCP_ELICITATION_ENABLED', true),
            'timeout' => env('MCP_ELICITATION_TIMEOUT', 300),
            'max_prompts' => env('MCP_ELICITATION_MAX_PROMPTS', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching and performance settings for the Laravel MCP SDK.
    | This includes cache stores, TTL values, and connection pooling.
    |
    */

    'cache' => [
        'enabled' => env('MCP_CACHE_ENABLED', true),
        'store' => env('MCP_CACHE_STORE', config('cache.default', 'file')),
        'prefix' => env('MCP_CACHE_PREFIX', 'mcp:'),
        'ttl' => [
            'tools' => env('MCP_CACHE_TOOLS_TTL', 300),
            'resources' => env('MCP_CACHE_RESOURCES_TTL', 60),
            'prompts' => env('MCP_CACHE_PROMPTS_TTL', 300),
            'roots' => env('MCP_CACHE_ROOTS_TTL', 600),
            'capabilities' => env('MCP_CACHE_CAPABILITIES_TTL', 3600),
        ],
    ],

    'performance' => [
        'connection_pool' => [
            'enabled' => env('MCP_CONNECTION_POOL_ENABLED', true),
            'max_connections' => env('MCP_CONNECTION_POOL_MAX', 10),
            'idle_timeout' => env('MCP_CONNECTION_POOL_IDLE_TIMEOUT', 300),
        ],
        'batch_processing' => [
            'enabled' => env('MCP_BATCH_PROCESSING_ENABLED', true),
            'batch_size' => env('MCP_BATCH_SIZE', 50),
            'timeout' => env('MCP_BATCH_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Configuration options for development and testing environments.
    | This includes debug settings, mock mode, and test server URLs.
    |
    */

    'development' => [
        'debug' => env('MCP_DEBUG', config('app.debug', false)),
        'mock_mode' => env('MCP_MOCK_MODE', false),
        'test_servers' => env('MCP_TEST_SERVERS', 'http://localhost:3000'),
        'log_requests' => env('MCP_LOG_REQUESTS', false),
        'log_responses' => env('MCP_LOG_RESPONSES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Laravel queue integration for MCP operations. This allows
    | long-running MCP operations to be processed in the background.
    |
    */

    'queue' => [
        'enabled' => env('MCP_QUEUE_ENABLED', false),
        'connection' => env('MCP_QUEUE_CONNECTION', config('queue.default', 'sync')),
        'queue' => env('MCP_QUEUE_NAME', 'mcp'),
        'timeout' => env('MCP_QUEUE_TIMEOUT', 300),
        'retry_after' => env('MCP_QUEUE_RETRY_AFTER', 90),
        'max_tries' => env('MCP_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Laravel event integration for MCP operations. This allows
    | you to listen to MCP events and respond accordingly.
    |
    */

    'events' => [
        'enabled' => env('MCP_EVENTS_ENABLED', true),
        'broadcast' => env('MCP_EVENTS_BROADCAST', false),
        'broadcast_channel' => env('MCP_EVENTS_BROADCAST_CHANNEL', 'mcp'),
    ],
];
