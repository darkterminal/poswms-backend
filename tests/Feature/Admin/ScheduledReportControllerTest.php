<?php

namespace Tests\Feature\Admin;

use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Tenant $tenant;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
        $this->tenant = Tenant::factory()->create();
        $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_scheduled_reports(): void
    {
        ScheduledReport::factory()->forTenant($this->tenant->id)->count(3)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/schedules');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_can_create_scheduled_report(): void
    {
        $payload = [
            'name' => 'Weekly Sales Report',
            'description' => 'Automated weekly sales report',
            'type' => 'sales',
            'filters' => [
                'date_range' => 'last_7_days',
                'status' => ['confirmed', 'fulfilled'],
            ],
            'schedule_frequency' => 'weekly',
            'schedule_day' => 1,
            'schedule_time' => '09:00',
            'recipients' => ['admin@example.com'],
            'export_format' => 'csv',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/reports/schedules', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.name', 'Weekly Sales Report');

        $this->assertDatabaseHas('scheduled_reports', [
            'name' => 'Weekly Sales Report',
            'schedule_frequency' => 'weekly',
        ]);
    }

    public function test_can_update_scheduled_report(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        $payload = [
            'name' => 'Updated Report Name',
            'schedule_time' => '10:00',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/reports/schedules/{$report->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.name', 'Updated Report Name');
    }

    public function test_can_delete_scheduled_report(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/reports/schedules/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('scheduled_reports', [
            'id' => $report->id,
        ]);
    }

    public function test_can_manually_run_scheduled_report(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/reports/schedules/{$report->id}/run");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('scheduled_report_executions', [
            'scheduled_report_id' => $report->id,
            'success' => true,
        ]);
    }

    public function test_can_get_execution_history(): void
    {
        $report = ScheduledReport::factory()->forTenant($this->tenant->id)->create();
        
        ScheduledReportExecution::factory()
            ->forScheduledReport($report->id)
            ->count(5)
            ->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/reports/schedules/{$report->id}/history");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data.executions');
    }

    public function test_can_filter_by_frequency(): void
    {
        ScheduledReport::factory()->forTenant($this->tenant->id)->daily()->count(2)->create();
        ScheduledReport::factory()->forTenant($this->tenant->id)->weekly()->count(3)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/schedules?frequency=weekly');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_can_filter_by_active_status(): void
    {
        ScheduledReport::factory()->forTenant($this->tenant->id)->active()->count(3)->create();
        ScheduledReport::factory()->forTenant($this->tenant->id)->inactive()->count(2)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/schedules?is_active=true');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_validation_requires_schedule_day_for_weekly(): void
    {
        $payload = [
            'name' => 'Weekly Report',
            'type' => 'sales',
            'filters' => [],
            'schedule_frequency' => 'weekly',
            'schedule_time' => '09:00',
            'recipients' => ['admin@example.com'],
            'export_format' => 'csv',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/reports/schedules', $payload);

        $response->assertStatus(422);
    }

    public function test_validation_requires_valid_schedule_day_for_monthly(): void
    {
        $payload = [
            'name' => 'Monthly Report',
            'type' => 'sales',
            'filters' => [],
            'schedule_frequency' => 'monthly',
            'schedule_day' => 32, // Invalid
            'schedule_time' => '09:00',
            'recipients' => ['admin@example.com'],
            'export_format' => 'csv',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/reports/schedules', $payload);

        $response->assertStatus(422);
    }

    public function test_super_admin_can_see_all_reports(): void
    {
        $otherTenant = Tenant::factory()->create();
        ScheduledReport::factory()->forTenant($otherTenant->id)->create();
        ScheduledReport::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/schedules');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.reports');
    }
}
