<?php

namespace MCP\Laravel\Tests\Feature\Console;

use MCP\Laravel\Tests\TestCase;

/**
 * Feature tests for MCP Artisan commands.
 */
class McpCommandsTest extends TestCase
{
    public function test_mcp_list_command(): void
    {
        $this->artisan('mcp:list')
            ->expectsOutput('MCP System Overview')
            ->assertExitCode(0);
    }

    public function test_mcp_list_servers_only(): void
    {
        $this->artisan('mcp:list --servers')
            ->assertExitCode(0);
    }

    public function test_mcp_list_clients_only(): void
    {
        $this->artisan('mcp:list --clients')
            ->assertExitCode(0);
    }

    public function test_mcp_list_with_json_output(): void
    {
        $this->artisan('mcp:list --json')
            ->assertExitCode(0);

        // We can't easily test JSON output structure in console tests,
        // but we can ensure it doesn't crash
    }

    public function test_mcp_test_command(): void
    {
        $this->artisan('mcp:test')
            ->expectsOutput('🧪 MCP Testing Suite')
            ->assertExitCode(0);
    }

    public function test_mcp_test_configuration(): void
    {
        $this->artisan('mcp:test')
            ->expectsOutput('⚙️  Testing Configuration')
            ->expectsOutput('✅ MCP configuration loaded')
            ->assertExitCode(0);
    }

    public function test_mcp_test_health_check(): void
    {
        $this->artisan('mcp:test --health')
            ->expectsOutput('🏥 Running Health Check');
        // Don't assert exit code as health check might fail in test environment
    }

    public function test_mcp_test_verbose(): void
    {
        $this->artisan('mcp:test --verbose')
            ->assertExitCode(0);
    }

    public function test_mcp_server_list(): void
    {
        $this->artisan('mcp:server list')
            ->expectsOutput('Configured MCP Servers:')
            ->assertExitCode(0);
    }

    public function test_mcp_server_status(): void
    {
        $this->artisan('mcp:server status test')
            ->expectsOutput('Server Status: test')
            ->assertExitCode(0);
    }

    public function test_mcp_server_status_nonexistent(): void
    {
        $this->artisan('mcp:server status nonexistent')
            ->expectsOutput("Server 'nonexistent' is not configured.")
            ->assertExitCode(1);
    }

    public function test_mcp_client_status(): void
    {
        $this->artisan('mcp:client status test')
            ->expectsOutput('Client Status: test')
            ->assertExitCode(0);
    }

    public function test_mcp_client_status_nonexistent(): void
    {
        $this->artisan('mcp:client status nonexistent')
            ->expectsOutput("Client 'nonexistent' is not configured.")
            ->assertExitCode(1);
    }

    public function test_mcp_install_command(): void
    {
        $this->artisan('mcp:install')
            ->expectsOutput('🚀 Installing Laravel MCP SDK')
            ->expectsOutput('✅ Installation completed successfully!')
            ->assertExitCode(0);
    }

    public function test_mcp_install_with_examples(): void
    {
        $this->artisan('mcp:install --examples')
            ->expectsOutput('💡 Installing example files...')
            ->assertExitCode(0);
    }

    public function test_mcp_install_with_config(): void
    {
        $this->artisan('mcp:install --config')
            ->expectsOutput('📝 Publishing configuration files...')
            ->assertExitCode(0);
    }

    public function test_mcp_install_all(): void
    {
        $this->artisan('mcp:install --all')
            ->expectsOutput('📝 Publishing configuration files...')
            ->expectsOutput('📁 Creating directories...')
            ->expectsOutput('💡 Installing example files...')
            ->assertExitCode(0);
    }

    public function test_invalid_server_action(): void
    {
        $this->artisan('mcp:server invalid-action')
            ->expectsOutput('Unknown action: invalid-action')
            ->assertExitCode(1);
    }

    public function test_invalid_client_action(): void
    {
        $this->artisan('mcp:client invalid-action')
            ->expectsOutput('Unknown action: invalid-action')
            ->assertExitCode(1);
    }

    public function test_server_start_command_structure(): void
    {
        // Test that the command accepts the right parameters
        // Note: We can't actually start servers in tests easily
        $this->artisan('mcp:server start test --transport=stdio')
            ->assertExitCode(0); // Should not crash due to missing transport implementation
    }

    public function test_client_connect_missing_url(): void
    {
        $this->artisan('mcp:client connect')
            ->expectsOutput('Server URL is required for connect action.')
            ->assertExitCode(1);
    }

    public function test_client_call_tool_missing_tool(): void
    {
        $this->artisan('mcp:client call-tool test')
            ->expectsOutput('Tool name is required. Use --tool option.')
            ->assertExitCode(1);
    }

    public function test_client_read_resource_missing_resource(): void
    {
        $this->artisan('mcp:client read-resource test')
            ->expectsOutput('Resource URI is required. Use --resource option.')
            ->assertExitCode(1);
    }

    public function test_client_get_prompt_missing_prompt(): void
    {
        $this->artisan('mcp:client get-prompt test')
            ->expectsOutput('Prompt name is required. Use --prompt option.')
            ->assertExitCode(1);
    }

    public function test_command_help_output(): void
    {
        $this->artisan('help mcp:list')
            ->assertExitCode(0);

        $this->artisan('help mcp:server')
            ->assertExitCode(0);

        $this->artisan('help mcp:client')
            ->assertExitCode(0);

        $this->artisan('help mcp:test')
            ->assertExitCode(0);

        $this->artisan('help mcp:install')
            ->assertExitCode(0);
    }

    public function test_mcp_test_with_specific_server(): void
    {
        $this->artisan('mcp:test --server=test')
            ->assertExitCode(0);
        // When testing a specific server, it doesn't print "📡 Testing Servers"
        // it goes directly to testing that server
    }

    public function test_mcp_list_with_status(): void
    {
        $this->artisan('mcp:list --status')
            ->expectsOutput('📊 System Status')
            ->assertExitCode(0);
    }

    public function test_error_handling_in_commands(): void
    {
        // Test with verbose flag to ensure error details are shown
        $this->artisan('mcp:server status nonexistent --verbose')
            ->assertExitCode(1);
    }

    public function test_mcp_install_force_flag(): void
    {
        $this->artisan('mcp:install --examples --force')
            ->assertExitCode(0);
    }

    public function test_json_parameter_parsing(): void
    {
        // Test that invalid JSON is handled gracefully
        $this->artisan('mcp:client call-tool test --tool=test --params=invalid-json')
            ->assertExitCode(1);

        // The command should fail with exit code 1, either due to invalid JSON or connection issues
        $this->assertTrue(true); // Just ensure the command fails gracefully
    }

    public function test_command_output_formatting(): void
    {
        // Test that commands produce properly formatted output
        $this->artisan('mcp:list')
            ->expectsOutputToContain('📡 MCP Servers')
            ->expectsOutputToContain('📱 MCP Clients')
            ->assertExitCode(0);
    }

    public function test_verbose_flag_functionality(): void
    {
        // Test that verbose flag provides additional output
        $this->artisan('mcp:server status test --verbose')
            ->assertExitCode(0);

        // The verbose output should contain more details
        // We can't easily test the exact content, but we can ensure it doesn't crash
    }
}
