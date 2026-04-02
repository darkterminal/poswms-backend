<?php

namespace Tests\Feature\Models;

use App\Models\SavedReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedReportTest extends TestCase
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

    public function test_can_create_saved_report(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'created_by' => $this->user->id,
            'name' => 'Test Sales Report',
            'type' => 'sales',
        ]);

        $this->assertDatabaseHas('saved_reports', [
            'id' => $report->id,
            'name' => 'Test Sales Report',
            'type' => 'sales',
        ]);
    }

    public function test_saved_report_scopes(): void
    {
        SavedReport::factory()->forTenant($this->tenant->id)->count(3)->create(['type' => 'sales', 'expires_at' => null]);
        SavedReport::factory()->forTenant($this->tenant->id)->count(2)->create(['type' => 'inventory', 'expires_at' => null]);
        SavedReport::factory()->expired()->forTenant($this->tenant->id)->create();

        // Test forTenant scope
        $tenantReports = SavedReport::forTenant($this->tenant->id)->get();
        $this->assertCount(6, $tenantReports);

        // Test ofType scope
        $salesReports = SavedReport::forTenant($this->tenant->id)->ofType('sales')->get();
        $this->assertCount(3, $salesReports);

        // Test notExpired scope (excludes the expired one)
        $notExpired = SavedReport::forTenant($this->tenant->id)->notExpired()->get();
        $this->assertCount(5, $notExpired);
    }

    public function test_saved_report_file_helpers(): void
    {
        $report = SavedReport::factory()->withFile()->forTenant($this->tenant->id)->create([
            'file_format' => 'csv',
            'file_size' => 2048,
            'expires_at' => null,
        ]);

        // File path exists but actual file doesn't (not created in tests)
        $this->assertNotNull($report->file_path);
        $this->assertEquals('2 KB', $report->getFormattedFileSize());
        $this->assertFalse($report->isExpired());
    }

    public function test_saved_report_is_expired(): void
    {
        $expired = SavedReport::factory()->expired()->forTenant($this->tenant->id)->create();
        $neverExpires = SavedReport::factory()->neverExpires()->forTenant($this->tenant->id)->create();

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($neverExpires->isExpired());
    }

    public function test_saved_report_relationships(): void
    {
        $report = SavedReport::factory()->forTenant($this->tenant->id)->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->tenant->id, $report->tenant->id);
        $this->assertEquals($this->user->id, $report->createdBy->id);
    }
}
