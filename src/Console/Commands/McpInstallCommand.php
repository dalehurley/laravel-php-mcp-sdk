<?php

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Artisan command for installing MCP scaffolding and examples.
 */
class McpInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:install
                            {--examples : Install example tools, resources, and prompts}
                            {--config : Publish configuration files}
                            {--migrations : Publish migration files}
                            {--stubs : Publish stub files}
                            {--all : Install everything}
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install MCP scaffolding, examples, and configuration files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("🚀 Installing Laravel MCP SDK");
        $this->line("");

        try {
            if ($this->option('all')) {
                $this->installAll();
            } else {
                $this->installSelective();
            }

            $this->line("");
            $this->info("✅ Installation completed successfully!");
            $this->displayNextSteps();

            return 0;
        } catch (\Exception $e) {
            $this->error("Installation failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Install everything.
     */
    protected function installAll(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishStubs();
        $this->createDirectories();
        $this->installExamples();
    }

    /**
     * Install selectively based on options.
     */
    protected function installSelective(): void
    {
        if ($this->option('config')) {
            $this->publishConfig();
        }

        if ($this->option('migrations')) {
            $this->publishMigrations();
        }

        if ($this->option('stubs')) {
            $this->publishStubs();
        }

        if ($this->option('examples')) {
            $this->createDirectories();
            $this->installExamples();
        }

        // If no specific options, do basic installation
        if (!$this->hasAnyOption()) {
            $this->publishConfig();
            $this->createDirectories();
        }
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfig(): void
    {
        $this->info("📝 Publishing configuration files...");

        $this->call('vendor:publish', [
            '--provider' => 'MCP\Laravel\Providers\McpServiceProvider',
            '--tag' => 'mcp-config',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Publish migration files.
     */
    protected function publishMigrations(): void
    {
        $this->info("🗄️  Publishing migration files...");

        $this->call('vendor:publish', [
            '--provider' => 'MCP\Laravel\Providers\McpServiceProvider',
            '--tag' => 'mcp-migrations',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Publish stub files.
     */
    protected function publishStubs(): void
    {
        $this->info("📄 Publishing stub files...");

        $this->call('vendor:publish', [
            '--provider' => 'MCP\Laravel\Providers\McpServiceProvider',
            '--tag' => 'mcp-stubs',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Create necessary directories.
     */
    protected function createDirectories(): void
    {
        $this->info("📁 Creating directories...");

        $directories = [
            app_path('Mcp'),
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
            app_path('Mcp/Api'),
            app_path('Mcp/Api/Tools'),
            app_path('Mcp/Api/Resources'),
            app_path('Mcp/Api/Prompts'),
            app_path('Mcp/WebSocket'),
            app_path('Mcp/WebSocket/Tools'),
            app_path('Mcp/WebSocket/Resources'),
            app_path('Mcp/WebSocket/Prompts'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->line("  Created: {$directory}");
            } else {
                $this->line("  Exists:  {$directory}");
            }
        }
    }

    /**
     * Install example files.
     */
    protected function installExamples(): void
    {
        $this->info("💡 Installing example files...");

        $this->createExampleTool();
        $this->createExampleResource();
        $this->createExamplePrompt();
        $this->createExampleApiTool();
    }

    /**
     * Create example tool.
     */
    protected function createExampleTool(): void
    {
        $path = app_path('Mcp/Tools/CalculatorTool.php');

        if (File::exists($path) && !$this->option('force')) {
            $this->line("  Exists:  {$path}");
            return;
        }

        $content = $this->getExampleToolContent();
        File::put($path, $content);
        $this->line("  Created: {$path}");
    }

    /**
     * Create example resource.
     */
    protected function createExampleResource(): void
    {
        $path = app_path('Mcp/Resources/ConfigResource.php');

        if (File::exists($path) && !$this->option('force')) {
            $this->line("  Exists:  {$path}");
            return;
        }

        $content = $this->getExampleResourceContent();
        File::put($path, $content);
        $this->line("  Created: {$path}");
    }

    /**
     * Create example prompt.
     */
    protected function createExamplePrompt(): void
    {
        $path = app_path('Mcp/Prompts/CodeReviewPrompt.php');

        if (File::exists($path) && !$this->option('force')) {
            $this->line("  Exists:  {$path}");
            return;
        }

        $content = $this->getExamplePromptContent();
        File::put($path, $content);
        $this->line("  Created: {$path}");
    }

    /**
     * Create example API tool.
     */
    protected function createExampleApiTool(): void
    {
        $path = app_path('Mcp/Api/Tools/UserLookupTool.php');

        if (File::exists($path) && !$this->option('force')) {
            $this->line("  Exists:  {$path}");
            return;
        }

        $content = $this->getExampleApiToolContent();
        File::put($path, $content);
        $this->line("  Created: {$path}");
    }

    /**
     * Display next steps.
     */
    protected function displayNextSteps(): void
    {
        $this->line("");
        $this->info("🎯 Next Steps:");
        $this->line("");
        $this->line("1. Review the configuration file:");
        $this->line("   php artisan config:show mcp");
        $this->line("");
        $this->line("2. Test your MCP setup:");
        $this->line("   php artisan mcp:test");
        $this->line("");
        $this->line("3. Start a server:");
        $this->line("   php artisan mcp:server start");
        $this->line("");
        $this->line("4. List available components:");
        $this->line("   php artisan mcp:list");
        $this->line("");
        $this->line("5. Connect a client:");
        $this->line("   php artisan mcp:client connect main http://localhost:3000");
        $this->line("");
        $this->line("📚 Documentation: https://github.com/dalehurley/laravel-php-mcp-sdk");
    }

    /**
     * Check if any option is set.
     */
    protected function hasAnyOption(): bool
    {
        return $this->option('examples') ||
            $this->option('config') ||
            $this->option('migrations') ||
            $this->option('stubs');
    }

    /**
     * Get example tool content.
     */
    protected function getExampleToolContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Tools;

use MCP\Laravel\Laravel\LaravelTool;

/**
 * Example calculator tool for MCP.
 */
class CalculatorTool extends LaravelTool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Performs basic arithmetic operations (add, subtract, multiply, divide)';
    }

    protected function properties(): array
    {
        return [
            'operation' => [
                'type' => 'string',
                'enum' => ['add', 'subtract', 'multiply', 'divide'],
                'description' => 'The arithmetic operation to perform',
            ],
            'a' => [
                'type' => 'number',
                'description' => 'First number',
            ],
            'b' => [
                'type' => 'number',
                'description' => 'Second number',
            ],
        ];
    }

    protected function required(): array
    {
        return ['operation', 'a', 'b'];
    }

    public function handle(array $params): array
    {
        $this->validate($params);

        $operation = $params['operation'];
        $a = $params['a'];
        $b = $params['b'];

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : null,
            default => null,
        };

        if ($result === null) {
            return $this->errorResponse($operation === 'divide' ? 'Division by zero' : 'Invalid operation');
        }

        return $this->textContent("Result: {$a} {$operation} {$b} = {$result}");
    }

    protected function validationRules(): array
    {
        return [
            'operation' => 'required|in:add,subtract,multiply,divide',
            'a' => 'required|numeric',
            'b' => 'required|numeric',
        ];
    }
}
PHP;
    }

    /**
     * Get example resource content.
     */
    protected function getExampleResourceContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Resources;

use MCP\Laravel\Laravel\LaravelResource;

/**
 * Example resource for accessing Laravel configuration.
 */
class ConfigResource extends LaravelResource
{
    public function uri(): string
    {
        return 'config://{key}';
    }

    public function description(): string
    {
        return 'Access Laravel configuration values';
    }

    public function read(string $uri): array
    {
        $variables = $this->extractUriVariables($uri);
        $key = $variables['key'] ?? null;

        if (!$key) {
            return $this->errorResponse('Configuration key is required');
        }

        $value = config($key);

        if ($value === null) {
            return $this->errorResponse("Configuration key '{$key}' not found");
        }

        // Convert to JSON for complex values
        if (is_array($value) || is_object($value)) {
            return $this->jsonContent($value);
        }

        return $this->textContent("Configuration '{$key}': " . (string) $value);
    }

    protected function mimeType(): string
    {
        return 'application/json';
    }
}
PHP;
    }

    /**
     * Get example prompt content.
     */
    protected function getExamplePromptContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Prompts;

use MCP\Laravel\Laravel\LaravelPrompt;

/**
 * Example prompt for code review assistance.
 */
class CodeReviewPrompt extends LaravelPrompt
{
    public function name(): string
    {
        return 'code-review';
    }

    public function description(): string
    {
        return 'Generate a code review prompt for analyzing code quality and suggesting improvements';
    }

    protected function argumentSchema(): array
    {
        return [
            'code' => [
                'type' => 'string',
                'description' => 'The code to review',
            ],
            'language' => [
                'type' => 'string',
                'description' => 'Programming language (optional)',
            ],
            'focus' => [
                'type' => 'string',
                'enum' => ['security', 'performance', 'maintainability', 'all'],
                'description' => 'Focus area for the review',
                'default' => 'all',
            ],
        ];
    }

    public function handle(array $args): array
    {
        $this->validate($args);

        $code = $args['code'];
        $language = $args['language'] ?? 'unknown';
        $focus = $args['focus'] ?? 'all';

        $systemPrompt = $this->buildSystemPrompt($focus, $language);
        $userPrompt = $this->buildUserPrompt($code, $language);

        return $this->createPromptResponse([
            $this->systemMessage($systemPrompt),
            $this->userMessage($userPrompt),
        ], "Code review prompt for {$language} code with focus on {$focus}");
    }

    protected function validationRules(): array
    {
        return [
            'code' => 'required|string|min:10',
            'language' => 'nullable|string|max:50',
            'focus' => 'nullable|in:security,performance,maintainability,all',
        ];
    }

    private function buildSystemPrompt(string $focus, string $language): string
    {
        $focusInstructions = match ($focus) {
            'security' => 'Focus primarily on security vulnerabilities, input validation, and potential attack vectors.',
            'performance' => 'Focus on performance optimizations, algorithm efficiency, and resource usage.',
            'maintainability' => 'Focus on code organization, readability, documentation, and maintainability.',
            default => 'Provide a comprehensive review covering security, performance, maintainability, and best practices.',
        };

        return $this->formatText("
            You are an experienced software engineer conducting a code review.
            
            {$focusInstructions}
            
            For the given {$language} code, please:
            1. Identify potential issues and improvements
            2. Suggest specific fixes with code examples
            3. Explain the reasoning behind your suggestions
            4. Rate the overall code quality (1-10)
            
            Be constructive and educational in your feedback.
        ");
    }

    private function buildUserPrompt(string $code, string $language): string
    {
        return $this->formatText("
            Please review this {$language} code:
            
            " . $this->codeBlock($code, $language) . "
            
            Provide your detailed code review following the instructions above.
        ");
    }
}
PHP;
    }

    /**
     * Get example API tool content.
     */
    protected function getExampleApiToolContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Api\Tools;

use MCP\Laravel\Laravel\LaravelTool;
use App\Models\User;

/**
 * Example API tool for user lookup.
 */
class UserLookupTool extends LaravelTool
{
    public function name(): string
    {
        return 'user-lookup';
    }

    public function description(): string
    {
        return 'Look up user information by ID or email';
    }

    protected function properties(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'description' => 'User ID',
            ],
            'email' => [
                'type' => 'string',
                'description' => 'User email address',
            ],
            'include_profile' => [
                'type' => 'boolean',
                'description' => 'Include user profile information',
                'default' => false,
            ],
        ];
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function requiredScopes(): array
    {
        return ['users:read'];
    }

    public function handle(array $params): array
    {
        $this->validate($params);

        if (!isset($params['id']) && !isset($params['email'])) {
            return $this->errorResponse('Either id or email parameter is required');
        }

        $query = User::query();

        if (isset($params['id'])) {
            $user = $query->find($params['id']);
        } else {
            $user = $query->where('email', $params['email'])->first();
        }

        if (!$user) {
            return $this->errorResponse('User not found');
        }

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->toISOString(),
        ];

        if ($params['include_profile'] ?? false) {
            $data['profile'] = [
                'avatar' => $user->avatar_url ?? null,
                'bio' => $user->bio ?? null,
                'location' => $user->location ?? null,
            ];
        }

        return $this->successResponse($data);
    }

    protected function validationRules(): array
    {
        return [
            'id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|email|exists:users,email',
            'include_profile' => 'nullable|boolean',
        ];
    }
}
PHP;
    }
}
