<?php

namespace Tests\Feature\Admin;

use App\Models\ReportTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Tenant $tenant;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->tenant = Tenant::factory()->create();
        $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_super_admin_can_list_report_templates(): void
    {
        ReportTemplate::factory()->global()->count(3)->create();
        ReportTemplate::factory()->forTenant($this->tenant->id)->count(2)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/templates');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data.templates');
    }

    public function test_tenant_user_cannot_access_admin_templates(): void
    {
        // Tenant users should not have access to admin routes
        ReportTemplate::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->tenantUser)
            ->getJson('/api/v1/admin/reports/templates');

        $response->assertStatus(403);
    }

    public function test_can_create_report_template(): void
    {
        $payload = [
            'name' => 'Monthly Sales Report',
            'description' => 'Monthly sales performance report',
            'type' => 'sales',
            'config' => [
                'filters' => ['date_range' => 'last_30_days'],
                'columns' => ['date', 'revenue', 'orders'],
                'grouping' => 'monthly',
                'sorting' => ['field' => 'date', 'direction' => 'desc'],
            ],
            'is_global' => false,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/reports/templates', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.template.name', 'Monthly Sales Report');

        $this->assertDatabaseHas('report_templates', [
            'name' => 'Monthly Sales Report',
            'type' => 'sales',
        ]);
    }

    public function test_can_update_report_template(): void
    {
        $template = ReportTemplate::factory()->forTenant($this->tenant->id)->create();

        $payload = [
            'name' => 'Updated Report Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/reports/templates/{$template->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.template.name', 'Updated Report Name');

        $this->assertDatabaseHas('report_templates', [
            'id' => $template->id,
            'name' => 'Updated Report Name',
        ]);
    }

    public function test_can_delete_report_template(): void
    {
        $template = ReportTemplate::factory()->forTenant($this->tenant->id)->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/reports/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Soft delete - record still exists but with deleted_at set
        $this->assertSoftDeleted('report_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_can_duplicate_report_template(): void
    {
        $template = ReportTemplate::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Original Report',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/reports/templates/{$template->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.template.name', 'Original Report (Copy)');

        $this->assertDatabaseHas('report_templates', [
            'name' => 'Original Report (Copy)',
            'type' => $template->type,
        ]);
    }

    public function test_can_get_report_types(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/templates/types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.types');
    }

    public function test_non_super_admin_cannot_modify_global_templates(): void
    {
        $template = ReportTemplate::factory()->global()->create();

        $payload = ['name' => 'Unauthorized Update'];

        $response = $this->actingAs($this->tenantUser)
            ->putJson("/api/v1/admin/reports/templates/{$template->id}", $payload);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_can_filter_templates_by_type(): void
    {
        ReportTemplate::factory()->global()->create(['type' => 'sales']);
        ReportTemplate::factory()->global()->create(['type' => 'inventory']);
        ReportTemplate::factory()->global()->create(['type' => 'sales']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/templates?type=sales');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.templates');
    }

    public function test_can_search_templates_by_name(): void
    {
        ReportTemplate::factory()->global()->create(['name' => 'Sales Report 2024']);
        ReportTemplate::factory()->global()->create(['name' => 'Inventory Report']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/reports/templates?search=Sales');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.templates');
    }
}
