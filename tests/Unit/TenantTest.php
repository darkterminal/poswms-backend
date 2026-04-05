<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Company', $tenant->name);
        $this->assertEquals('test-company', $tenant->slug);
    }

    public function test_tenant_slug_is_unique(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 'unique-slug']);

        $this->expectException(QueryException::class);

        Tenant::factory()->create(['slug' => 'unique-slug']);
    }

    public function test_tenant_has_active_status_by_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue($tenant->isActive());
        $this->assertEquals('active', $tenant->status);
    }

    public function test_tenant_can_be_suspended(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->assertFalse($tenant->isActive());
        $this->assertEquals('suspended', $tenant->status);
    }

    public function test_tenant_can_be_on_trial(): void
    {
        $tenant = Tenant::factory()->onTrial()->create();

        $this->assertTrue($tenant->isOnTrial());
        $this->assertNotNull($tenant->trial_ends_at);
    }

    public function test_tenant_can_have_subscription(): void
    {
        $tenant = Tenant::factory()->withSubscription()->create();

        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertNotNull($tenant->subscription_ends_at);
    }

    public function test_tenant_has_users_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertEquals(3, $tenant->users()->count());
    }

    public function test_tenant_settings_are_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['theme' => 'dark', 'notifications' => false],
        ]);

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('dark', $tenant->settings['theme']);
    }

    public function test_tenant_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_tenant_uses_its_own_currency_when_set(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'IDR']);

        $this->assertEquals('IDR', $tenant->resolveCurrency());
    }

    public function test_tenant_falls_back_to_system_default_currency(): void
    {
        Setting::set('application.default_currency', 'EUR');

        $tenant = Tenant::factory()->create(['currency' => '']);

        $this->assertEquals('EUR', $tenant->resolveCurrency());
    }

    public function test_tenant_falls_back_to_usd_when_no_system_default(): void
    {
        $tenant = Tenant::factory()->create(['currency' => '']);

        $this->assertEquals('USD', $tenant->resolveCurrency());
    }
}
