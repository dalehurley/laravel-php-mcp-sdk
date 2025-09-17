<?php

namespace MCP\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use MCP\Laravel\Laravel\McpManager;
use MCP\Laravel\Laravel\ServerManager;
use MCP\Laravel\Laravel\ClientManager;
use MCP\Laravel\Utilities\CancellationManager;
use MCP\Laravel\Utilities\PingManager;
use MCP\Laravel\Utilities\ProgressManager;
use MCP\Laravel\Utilities\CompletionManager;
use MCP\Laravel\Utilities\LoggingManager;
use MCP\Laravel\Utilities\PaginationManager;
use MCP\Laravel\Features\RootsManager;
use MCP\Laravel\Features\SamplingManager;
use MCP\Laravel\Features\ElicitationManager;
use MCP\Laravel\Transport\StdioTransportManager;
use MCP\Laravel\Transport\HttpTransportManager;
use MCP\Laravel\Transport\WebSocketTransportManager;
use MCP\Laravel\Console\Commands\McpServerCommand;
use MCP\Laravel\Console\Commands\McpClientCommand;
use MCP\Laravel\Console\Commands\McpListCommand;
use MCP\Laravel\Console\Commands\McpTestCommand;
use MCP\Laravel\Console\Commands\McpInstallCommand;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (file_exists(__DIR__ . '/../../config/mcp.php')) {
            $this->mergeConfigFrom(__DIR__ . '/../../config/mcp.php', 'mcp');
        } else {
            // Provide minimal default configuration if config file doesn't exist
            $this->app['config']->set('mcp', [
                'default_server' => 'main',
                'default_client' => 'main',
                'servers' => [],
                'clients' => [],
                'transports' => [],
                'authorization' => ['enabled' => false],
                'utilities' => [],
                'features' => [],
                'cache' => ['enabled' => false],
                'development' => ['debug' => false],
                'queue' => ['enabled' => false],
                'events' => ['enabled' => false],
            ]);
        }

        // Register core managers as singletons
        $this->app->singleton(McpManager::class, function ($app) {
            return new McpManager($app);
        });

        $this->app->singleton(ServerManager::class, function ($app) {
            return new ServerManager($app, $app->make(McpManager::class));
        });

        $this->app->singleton(ClientManager::class, function ($app) {
            return new ClientManager($app, $app->make(McpManager::class));
        });

        // Register utility managers
        $this->registerUtilityManagers();

        // Register feature managers
        $this->registerFeatureManagers();

        // Register transport managers
        $this->registerTransportManagers();

        // Register aliases for facades
        $this->app->alias(McpManager::class, 'mcp');
        $this->app->alias(ServerManager::class, 'mcp.servers');
        $this->app->alias(ClientManager::class, 'mcp.clients');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishables();
            $this->registerCommands();
        }

        $this->registerRoutes();
        $this->registerMiddleware();
        $this->initializeLogging();
        $this->registerEventListeners();
    }

    /**
     * Register utility managers.
     */
    protected function registerUtilityManagers(): void
    {
        $this->app->singleton(CancellationManager::class);
        $this->app->singleton(PingManager::class);
        $this->app->singleton(ProgressManager::class);
        $this->app->singleton(CompletionManager::class);
        $this->app->singleton(LoggingManager::class);
        $this->app->singleton(PaginationManager::class);
    }

    /**
     * Register feature managers.
     */
    protected function registerFeatureManagers(): void
    {
        $this->app->singleton(RootsManager::class);
        $this->app->singleton(SamplingManager::class);
        $this->app->singleton(ElicitationManager::class);
    }

    /**
     * Register transport managers.
     */
    protected function registerTransportManagers(): void
    {
        $this->app->singleton(StdioTransportManager::class);
        $this->app->singleton(HttpTransportManager::class);
        $this->app->singleton(WebSocketTransportManager::class);
    }

    /**
     * Register publishable files.
     */
    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        if (file_exists(__DIR__ . '/../../database/migrations')) {
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'mcp-migrations');
        }

        if (file_exists(__DIR__ . '/../../resources/stubs')) {
            $this->publishes([
                __DIR__ . '/../../resources/stubs' => resource_path('stubs/mcp'),
            ], 'mcp-stubs');
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            McpServerCommand::class,
            McpClientCommand::class,
            McpListCommand::class,
            McpTestCommand::class,
            McpInstallCommand::class,
        ]);
    }

    /**
     * Register HTTP routes for MCP servers.
     */
    protected function registerRoutes(): void
    {
        $servers = config('mcp.servers', []);
        $hasHttpServer = false;

        foreach ($servers as $name => $config) {
            if (($config['transport'] ?? 'stdio') === 'http') {
                $hasHttpServer = true;
                break;
            }
        }

        if ($hasHttpServer && file_exists(__DIR__ . '/../../routes/mcp.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/mcp.php');
        }
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('mcp.auth', \MCP\Laravel\Http\Middleware\McpAuth::class);
        $router->aliasMiddleware('mcp.security', \MCP\Laravel\Http\Middleware\McpSecurity::class);

        // Register middleware group for MCP routes
        $router->middlewareGroup('mcp', [
            'mcp.security',
            'throttle:' . config('mcp.transports.http.security.rate_limiting', '60,1'),
        ]);

        // Add auth middleware if enabled
        if (config('mcp.authorization.enabled', false)) {
            $router->pushMiddlewareToGroup('mcp', 'mcp.auth');
        }
    }

    /**
     * Initialize MCP logging channel.
     */
    protected function initializeLogging(): void
    {
        if (!config('mcp.utilities.logging.enabled', true)) {
            return;
        }

        $channel = config('mcp.utilities.logging.channel', 'mcp');

        // Add MCP logging channel if it doesn't exist
        if (!config("logging.channels.{$channel}")) {
            config([
                "logging.channels.{$channel}" => [
                    'driver' => 'daily',
                    'path' => storage_path("logs/{$channel}.log"),
                    'level' => config('mcp.utilities.logging.level', 'info'),
                    'days' => 14,
                    'replace_placeholders' => true,
                ]
            ]);
        }
    }

    /**
     * Register event listeners for MCP operations.
     */
    protected function registerEventListeners(): void
    {
        if (!config('mcp.events.enabled', true)) {
            return;
        }

        // Register event listeners here
        // This will be expanded when we create the event classes
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            McpManager::class,
            ServerManager::class,
            ClientManager::class,
            CancellationManager::class,
            PingManager::class,
            ProgressManager::class,
            CompletionManager::class,
            LoggingManager::class,
            PaginationManager::class,
            RootsManager::class,
            SamplingManager::class,
            ElicitationManager::class,
            StdioTransportManager::class,
            HttpTransportManager::class,
            WebSocketTransportManager::class,
            'mcp',
            'mcp.servers',
            'mcp.clients',
        ];
    }
}
