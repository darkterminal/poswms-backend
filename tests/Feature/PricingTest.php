<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(Tenant $tenant): User
    {
        $admin = User::factory()->forTenant($tenant->id)->create();
        Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_can_create_pricing_tier(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/pricing-tiers", [
                'name' => 'Gold Tier',
                'slug' => 'gold',
                'priority' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_can_create_pricing_rule(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $tier = PricingTier::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/pricing-rules", [
                'pricing_tier_id' => $tier->id,
                'type' => 'percentage',
                'operation' => 'subtract',
                'value' => 10,
            ]);

        $response->assertStatus(201);
    }

    public function test_pricing_rule_calculates_percentage_discount(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $rule = PricingRule::factory()->forTenant($tenant->id)->percentageDiscount(20)->create();

        $price = $rule->calculatePrice(100, 1);

        $this->assertEquals(80, $price);
    }

    public function test_pricing_rule_calculates_fixed_discount(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $rule = PricingRule::factory()->forTenant($tenant->id)->fixedDiscount(15)->create();

        $price = $rule->calculatePrice(100, 1);

        $this->assertEquals(85, $price);
    }

    public function test_customer_with_pricing_tier(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $tier = PricingTier::factory()->forTenant($tenant->id)->gold()->create();
        $customer = Customer::factory()->forTenant($tenant->id)->withPricingTier($tier->id)->create();

        $this->assertEquals($tier->id, $customer->pricing_tier_id);
        $this->assertEquals('Gold', $customer->pricingTier->name);
    }

    public function test_pricing_rule_date_range(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $rule = PricingRule::factory()->forTenant($tenant->id)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(7),
        ]);

        $this->assertFalse($rule->isActive());
    }
}
