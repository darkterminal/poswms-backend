<?php

namespace Tests\Feature\Models;

use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_scheduled_report(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create([
            'created_by' => $this->user->id,
            'name' => 'Weekly Sales Report',
            'schedule_frequency' => 'weekly',
        ]);

        $this->assertDatabaseHas('scheduled_reports', [
            'id' => $report->id,
            'name' => 'Weekly Sales Report',
            'schedule_frequency' => 'weekly',
        ]);
    }

    public function test_scheduled_report_scopes(): void
    {
        ScheduledReport::factory()->forTenant($this->tenant->id)->count(3)->active()->create();
        ScheduledReport::factory()->forTenant($this->tenant->id)->count(2)->inactive()->create();

        // Test active scope
        $activeReports = ScheduledReport::active()->get();
        $this->assertCount(3, $activeReports);

        // Test forTenant scope
        $tenantReports = ScheduledReport::forTenant($this->tenant->id)->get();
        $this->assertCount(5, $tenantReports);
    }

    public function test_calculate_next_run_daily(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->daily()->create([
            'schedule_time' => '09:00:00',
            'last_run_at' => now()->subDay(),
        ]);

        $nextRun = $report->calculateNextRun();
        
        $this->assertTrue($nextRun->gt(now()));
    }

    public function test_calculate_next_run_weekly(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->weekly(1)->create([
            'schedule_time' => '09:00:00',
            'last_run_at' => now()->subWeek(),
        ]);

        $nextRun = $report->calculateNextRun();
        
        $this->assertTrue($nextRun->gt(now()));
    }

    public function test_update_next_run(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->daily()->create([
            'schedule_time' => '09:00:00',
            'last_run_at' => null,
            'next_run_at' => now(),
        ]);

        $report->updateNextRun();

        $this->assertNotNull($report->fresh()->last_run_at);
        $this->assertNotNull($report->fresh()->next_run_at);
        $this->assertTrue($report->fresh()->next_run_at->isFuture());
    }

    public function test_is_due(): void
    {
        $dueReport = ScheduledReport::factory()->forTenant($this->tenant->id)->create([
            'is_active' => true,
            'next_run_at' => now()->subHour(),
        ]);

        $notDueReport = ScheduledReport::factory()->forTenant($this->tenant->id)->create([
            'is_active' => true,
            'next_run_at' => now()->addHour(),
        ]);

        $inactiveReport = ScheduledReport::factory()->forTenant($this->tenant->id)->create([
            'is_active' => false,
            'next_run_at' => now()->subHour(),
        ]);

        $this->assertTrue($dueReport->isDue());
        $this->assertFalse($notDueReport->isDue());
        $this->assertFalse($inactiveReport->isDue());
    }

    public function test_get_schedule_description(): void
    {
        $daily = ScheduledReport::factory()->forTenant($this->tenant->id)->daily()->create([
            'schedule_time' => '09:00:00',
        ]);

        $weekly = ScheduledReport::factory()->forTenant($this->tenant->id)->weekly(3)->create([
            'schedule_time' => '14:00:00',
        ]);

        $monthly = ScheduledReport::factory()->forTenant($this->tenant->id)->monthly(15)->create([
            'schedule_time' => '08:30:00',
        ]);

        $this->assertStringContainsString('Daily', $daily->getScheduleDescription());
        $this->assertStringContainsString('Weekly', $weekly->getScheduleDescription());
        $this->assertStringContainsString('Monthly', $monthly->getScheduleDescription());
    }

    public function test_scheduled_report_relationships(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->tenant->id, $report->tenant->id);
        $this->assertEquals($this->user->id, $report->createdBy->id);
    }

    public function test_can_create_execution_history(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        $execution = ScheduledReportExecution::factory()->create([
            'scheduled_report_id' => $report->id,
            'tenant_id' => $this->tenant->id,
            'success' => true,
            'records_count' => 150,
        ]);

        $this->assertDatabaseHas('scheduled_report_executions', [
            'id' => $execution->id,
            'scheduled_report_id' => $report->id,
            'success' => true,
        ]);

        // Test relationship
        $this->assertEquals(1, $report->executionHistory()->count());
    }

    public function test_execution_history_scopes(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        ScheduledReportExecution::factory()->forScheduledReport($report->id)->successful()->count(3)->create();
        ScheduledReportExecution::factory()->forScheduledReport($report->id)->failed()->count(2)->create();

        // Test successful scope
        $successful = ScheduledReportExecution::successful()->count();
        $this->assertEquals(3, $successful);

        // Test failed scope
        $failed = ScheduledReportExecution::failed()->count();
        $this->assertEquals(2, $failed);
    }
}
