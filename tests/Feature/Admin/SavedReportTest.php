<?php

namespace Tests\Feature\Admin;

use App\Models\SavedReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavedReportTest extends TestCase
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

    public function test_can_list_saved_reports(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_can_create_saved_report(): void
    {
        $payload = [
            'name' => 'Monthly Sales Report',
            'description' => 'Sales report for January 2024',
            'type' => 'sales',
            'tenant_id' => $this->tenant->id, // Super admins can specify tenant
            'filters' => [
                'date_range' => 'last_30_days',
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-31',
            ],
            'data' => [
                'summary' => [
                    'total_revenue' => 50000,
                    'total_orders' => 150,
                ],
            ],
            'expires_at' => now()->addMonths(6)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/reports/saved', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.name', 'Monthly Sales Report');

        $this->assertDatabaseHas('saved_reports', [
            'name' => 'Monthly Sales Report',
            'type' => 'sales',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_view_saved_report(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Test Report',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/reports/saved/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.name', 'Test Report');
    }

    public function test_cannot_view_other_tenants_report(): void
    {
        $otherTenant = Tenant::factory()->create();
        $report = SavedReport::factory()->forTenant($otherTenant->id)->create();

        // Super admins can see all reports
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/reports/saved/{$report->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_saved_report(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create();

        $payload = [
            'name' => 'Updated Report Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/reports/saved/{$report->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.name', 'Updated Report Name');
    }

    public function test_can_delete_saved_report(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/reports/saved/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('saved_reports', [
            'id' => $report->id,
        ]);
    }

    public function test_can_get_saved_report_stats(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create(['type' => 'sales']);
        SavedReport::factory()->forTenant($this->tenant->id)->count(2)->create(['type' => 'inventory']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.by_type.sales', 3)
            ->assertJsonPath('data.by_type.inventory', 2);
    }

    public function test_can_filter_reports_by_type(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create(['type' => 'sales']);
        SavedReport::factory()->forTenant($this->tenant->id)->count(2)->create(['type' => 'inventory']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved?type=sales');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_can_search_reports_by_name(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->create(['name' => 'Sales Report 2024']);
        SavedReport::factory()->forTenant($this->tenant->id)->create(['name' => 'Inventory Report']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved?search=Sales');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.reports');
    }

    public function test_excludes_expired_reports_by_default(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create(['expires_at' => null]);
        SavedReport::factory()->expired()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.reports');
    }

    public function test_can_include_expired_reports(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create(['expires_at' => null]);
        SavedReport::factory()->expired()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/saved?include_expired=true');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data.reports');
    }

    public function test_download_returns_file(): void
    {
        Storage::fake('public');
        
        $filePath = 'saved_reports/test-report.csv';
        Storage::disk('public')->put($filePath, 'test,csv,content');

        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'file_path' => $filePath,
            'file_format' => 'csv',
            'name' => 'Test Report',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/reports/saved/{$report->id}/download");

        $response->assertStatus(200)
            ->assertDownload('Test Report.csv');
    }

    public function test_download_fails_if_no_file(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'file_path' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/reports/saved/{$report->id}/download");

        $response->assertStatus(404);
    }

    public function test_deleting_report_removes_file(): void
    {
        Storage::fake('public');
        
        $filePath = 'saved_reports/to-delete.csv';
        Storage::disk('public')->put($filePath, 'test content');

        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'file_path' => $filePath,
        ]);

        $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/reports/saved/{$report->id}");

        Storage::disk('public')->assertMissing($filePath);
    }
}
