<?php

namespace MCP\Laravel\Tests\Unit\Utilities;

use MCP\Laravel\Tests\TestCase;
use MCP\Laravel\Utilities\ProgressManager;
use MCP\Types\ProgressToken;

/**
 * Test cases for ProgressManager.
 */
class ProgressManagerTest extends TestCase
{
    protected ProgressManager $progressManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressManager = app(ProgressManager::class);
    }

    public function test_can_start_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);

        $this->assertInstanceOf(ProgressToken::class, $token);

        $progress = $this->progressManager->getProgress($token);
        $this->assertNotNull($progress);
        $this->assertEquals('Test operation', $progress['message']);
        $this->assertEquals(100, $progress['total']);
        $this->assertEquals(0, $progress['current']);
        $this->assertFalse($progress['completed']);
    }

    public function test_can_update_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);

        $this->progressManager->update($token, 50, 'Half way done');

        $progress = $this->progressManager->getProgress($token);
        $this->assertEquals(50, $progress['current']);
        $this->assertEquals('Half way done', $progress['message']);
        $this->assertEquals(50.0, $progress['percentage']);
    }

    public function test_can_complete_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);

        $this->progressManager->complete($token, 'Operation completed');

        $progress = $this->progressManager->getProgress($token);
        $this->assertTrue($progress['completed']);
        $this->assertEquals('Operation completed', $progress['message']);
        $this->assertEquals(100, $progress['current']);
        $this->assertEquals(100.0, $progress['percentage']);
        $this->assertArrayHasKey('completed_at', $progress);
    }

    public function test_can_cancel_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);

        $this->progressManager->cancel($token, 'Operation cancelled');

        $progress = $this->progressManager->getProgress($token);
        $this->assertNull($progress); // Should be removed after cancellation
    }

    public function test_can_increment_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 10);

        $this->progressManager->increment($token, 'Step 1');
        $progress = $this->progressManager->getProgress($token);
        $this->assertEquals(1, $progress['current']);

        $this->progressManager->increment($token, 'Step 2');
        $progress = $this->progressManager->getProgress($token);
        $this->assertEquals(2, $progress['current']);
    }

    public function test_can_set_percentage(): void
    {
        $token = $this->progressManager->start('Test operation', 200);

        $this->progressManager->setPercentage($token, 75.0, '75% complete');

        $progress = $this->progressManager->getProgress($token);
        $this->assertEquals(150, $progress['current']); // 75% of 200
        $this->assertEquals('75% complete', $progress['message']);
    }

    public function test_can_check_if_active(): void
    {
        $token = $this->progressManager->start('Test operation', 100);

        $this->assertTrue($this->progressManager->isActive($token));

        $this->progressManager->complete($token);
        $this->assertFalse($this->progressManager->isActive($token));
    }

    public function test_can_get_all_active_progress(): void
    {
        $token1 = $this->progressManager->start('Operation 1', 100);
        $token2 = $this->progressManager->start('Operation 2', 50);
        $token3 = $this->progressManager->start('Operation 3', 75);

        $this->progressManager->complete($token3);

        $active = $this->progressManager->getAllActive();
        $this->assertCount(2, $active);
        $this->assertTrue($active->has($token1->getValue()));
        $this->assertTrue($active->has($token2->getValue()));
        $this->assertFalse($active->has($token3->getValue()));
    }

    public function test_can_get_completed_progress(): void
    {
        $token1 = $this->progressManager->start('Operation 1', 100);
        $token2 = $this->progressManager->start('Operation 2', 50);

        $this->progressManager->complete($token1);

        $completed = $this->progressManager->getCompleted();
        $this->assertCount(1, $completed);
        $this->assertTrue($completed->has($token1->getValue()));
        $this->assertFalse($completed->has($token2->getValue()));
    }

    public function test_can_get_statistics(): void
    {
        $token1 = $this->progressManager->start('Operation 1', 100);
        $token2 = $this->progressManager->start('Operation 2', 50);
        $token3 = $this->progressManager->start('Operation 3', 75);

        $this->progressManager->complete($token1);
        $this->progressManager->cancel($token3, 'Cancelled');

        $stats = $this->progressManager->getStatistics();

        $this->assertArrayHasKey('total_operations', $stats);
        $this->assertArrayHasKey('active_operations', $stats);
        $this->assertArrayHasKey('completed_operations', $stats);
        $this->assertArrayHasKey('cancelled_operations', $stats);

        $this->assertEquals(2, $stats['total_operations']); // token3 was removed
        $this->assertEquals(1, $stats['active_operations']);
        $this->assertEquals(1, $stats['completed_operations']);
        $this->assertEquals(0, $stats['cancelled_operations']); // Cancelled ones are removed
    }

    public function test_can_create_mcp_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);
        $this->progressManager->update($token, 25);

        $mcpProgress = $this->progressManager->createMcpProgress($token);

        $this->assertInstanceOf(\MCP\Types\Progress::class, $mcpProgress);
        $this->assertEquals(25, $mcpProgress->getProgress());
        $this->assertEquals(100, $mcpProgress->getTotal());
    }

    public function test_cleanup_removes_old_progress(): void
    {
        $token = $this->progressManager->start('Test operation', 100);
        $this->progressManager->complete($token);

        // Manually set completed_at to simulate old progress
        $progress = $this->progressManager->getProgress($token);
        $progress['completed_at'] = now()->subHours(2);

        // This is a bit hacky but necessary for testing
        $reflection = new \ReflectionClass($this->progressManager);
        $property = $reflection->getProperty('activeProgress');
        $property->setAccessible(true);
        $activeProgress = $property->getValue($this->progressManager);
        $activeProgress->put($token->getValue(), $progress);

        $removed = $this->progressManager->cleanup(60); // Remove older than 1 hour

        $this->assertEquals(1, $removed);
        $this->assertNull($this->progressManager->getProgress($token));
    }

    public function test_handles_nonexistent_token_gracefully(): void
    {
        $fakeToken = new ProgressToken('nonexistent');

        $this->progressManager->update($fakeToken, 50);
        $this->progressManager->complete($fakeToken);
        $this->progressManager->cancel($fakeToken);

        $this->assertNull($this->progressManager->getProgress($fakeToken));
        $this->assertFalse($this->progressManager->isActive($fakeToken));
    }
}
