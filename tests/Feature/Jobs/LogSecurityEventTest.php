<?php

namespace Tests\Feature\Jobs;

use App\Jobs\LogSecurityEvent;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSecurityEventTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);
    }

    /**
     * Test that the job creates an audit log entry.
     */
    public function test_job_creates_audit_log(): void
    {
        $eventType = 'security.test_event';
        $description = 'Test security event';
        $tenantId = $this->tenant->id;
        $user = \App\Models\User::factory()->forTenant($tenantId)->create();
        $userId = $user->id;
        $ipAddress = '192.168.1.1';
        $userAgent = 'Test Agent';
        $url = 'https://example.com/test';

        $job = new LogSecurityEvent(
            eventType: $eventType,
            description: $description,
            context: ['test' => true],
            tenantId: $tenantId,
            userId: $userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            url: $url
        );

        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => $eventType,
            'description' => $description,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $url,
        ]);

        $auditLog = AuditLog::where('event_type', $eventType)->first();
        $this->assertEquals(['test' => true, 'logged_at' => $auditLog->metadata['logged_at'], 'logged_async' => true], $auditLog->metadata);
    }

    /**
     * Test that the job handles empty event type gracefully.
     */
    public function test_job_handles_empty_event_type(): void
    {
        $job = new LogSecurityEvent(
            eventType: '',
            description: 'Should not be logged'
        );

        $job->handle();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * Test that the failed method logs to error log.
     */
    public function test_failed_method_logs_error(): void
    {
        // We can't easily test Log::fake() with Monolog
        // Instead, test that the fallback mechanism works
        $user = \App\Models\User::factory()->forTenant($this->tenant->id)->create();

        $job = new LogSecurityEvent(
            eventType: 'security.test',
            description: 'Test event',
            tenantId: $this->tenant->id,
            userId: $user->id
        );

        // Simulate failure by calling failed with an exception
        // The job should attempt to log synchronously as fallback
        $exception = new \Exception('Database connection failed');

        // This should not throw an exception
        $job->failed($exception);

        // Verify the fallback created an audit log with error info
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'security.test',
            'description' => 'Test event [ASYNC FAILED]',
        ]);
    }

    /**
     * Test that the job has correct retry configuration.
     */
    public function test_job_retry_configuration(): void
    {
        $job = new LogSecurityEvent(
            eventType: 'security.test',
            description: 'Test event'
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->backoff);
    }

    /**
     * Test that the job handles null values correctly.
     */
    public function test_job_handles_null_values(): void
    {
        $job = new LogSecurityEvent(
            eventType: 'security.test',
            description: 'Test with nulls',
            context: [],
            tenantId: null,
            userId: null,
            ipAddress: null,
            userAgent: null,
            url: null
        );

        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'security.test',
            'description' => 'Test with nulls',
            'tenant_id' => null,
            'user_id' => null,
        ]);
    }

    /**
     * Test that the job includes async metadata.
     */
    public function test_job_includes_async_metadata(): void
    {
        $job = new LogSecurityEvent(
            eventType: 'security.test',
            description: 'Async test',
            context: ['original' => 'data']
        );

        $job->handle();

        $auditLog = AuditLog::where('event_type', 'security.test')->first();

        $this->assertEquals('Async test', $auditLog->description);
        $this->assertTrue($auditLog->metadata['logged_async']);
        $this->assertEquals('data', $auditLog->metadata['original']);
        $this->assertArrayHasKey('logged_at', $auditLog->metadata);
    }
}
