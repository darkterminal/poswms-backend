<?php

namespace Tests\Feature\Admin;

use App\Models\CurrencyExchangeRate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_list_available_currencies(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/currencies');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Available currencies retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'currencies' => [
                        '*' => ['code', 'decimal_places'],
                    ],
                    'default_currency',
                ],
            ]);
    }

    public function test_get_exchange_rates(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/currencies/rates');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'rates' => [
                        [
                            'base_currency' => 'USD',
                            'target_currency' => 'EUR',
                            'rate' => 0.92,
                        ],
                    ],
                ],
            ]);
    }

    public function test_update_exchange_rate(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/rates', [
                'base_currency' => 'USD',
                'target_currency' => 'EUR',
                'rate' => 0.95,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'rate' => [
                        'base_currency' => 'USD',
                        'target_currency' => 'EUR',
                        'rate' => 0.95,
                        'source' => 'manual',
                    ],
                ],
            ]);
    }

    public function test_update_exchange_rate_validation(): void
    {
        // Missing required fields
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/rates', []);

        $response->assertStatus(422);

        // Invalid currency code
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/rates', [
                'base_currency' => 'US',
                'target_currency' => 'EUR',
                'rate' => 0.95,
            ]);

        $response->assertStatus(422);

        // Same currency
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/rates', [
                'base_currency' => 'USD',
                'target_currency' => 'USD',
                'rate' => 1.0,
            ]);

        $response->assertStatus(422);

        // Negative rate
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/rates', [
                'base_currency' => 'USD',
                'target_currency' => 'EUR',
                'rate' => -1,
            ]);

        $response->assertStatus(422);
    }

    public function test_delete_exchange_rate(): void
    {
        $rate = CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/currencies/rates/{$rate->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Exchange rate deleted successfully',
            ]);
    }

    public function test_convert_currency(): void
    {
        CurrencyExchangeRate::updateRate('USD', 'EUR', 0.92, 'manual');

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/convert', [
                'amount' => 100,
                'from_currency' => 'USD',
                'to_currency' => 'EUR',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'from' => ['amount' => 100, 'currency' => 'USD'],
                    'to' => ['currency' => 'EUR'],
                ],
            ]);

        $data = $response->json('data.to.amount');
        $this->assertEqualsWithDelta(92.0, $data, 0.01);
    }

    public function test_convert_currency_validation(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/currencies/convert', []);

        $response->assertStatus(422);
    }

    public function test_get_tenant_currency(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'IDR']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/currency");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenant->id,
                    'currency' => 'IDR',
                ],
            ]);
    }

    public function test_update_tenant_currency(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'USD']);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/currency", [
                'currency' => 'EUR',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenant->id,
                    'currency' => 'EUR',
                ],
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_update_tenant_currency_validation(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/currency", [
                'currency' => 'INVALID',
            ]);

        $response->assertStatus(422);
    }

    public function test_non_super_admin_cannot_access_currency_endpoints(): void
    {
        $regularUser = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);

        $response = $this->actingAs($regularUser)->getJson('/api/v1/admin/currencies');
        $response->assertStatus(403);

        $response = $this->actingAs($regularUser)->getJson('/api/v1/admin/currencies/rates');
        $response->assertStatus(403);

        $response = $this->actingAs($regularUser)->postJson('/api/v1/admin/currencies/rates', [
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.92,
        ]);
        $response->assertStatus(403);
    }

    public function test_currency_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/currencies');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/currencies/rates');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/admin/currencies/convert', [
            'amount' => 100,
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
        ]);
        $response->assertStatus(401);
    }
}
