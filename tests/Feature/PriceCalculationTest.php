<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculationTest extends TestCase
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

    public function test_can_calculate_base_price_without_rules(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 100.00,
                        'final_price' => 100.00,
                        'discount' => 0.00,
                    ],
                ],
            ]);
    }

    public function test_calculate_price_with_percentage_discount_rule(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $tier = PricingTier::factory()->forTenant($tenant->id)->gold()->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);

        PricingRule::factory()->forTenant($tenant->id)->forPricingTier($tier->id)->percentageDiscount(20)->create();

        $customer = Customer::factory()->forTenant($tenant->id)->withPricingTier($tier->id)->create();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 1,
                'customer_id' => $customer->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 100.00,
                        'final_price' => 80.00,
                        'discount' => 20.00,
                    ],
                ],
            ]);
    }

    public function test_calculate_price_with_fixed_discount_rule(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $tier = PricingTier::factory()->forTenant($tenant->id)->silver()->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);

        PricingRule::factory()->forTenant($tenant->id)->forPricingTier($tier->id)->fixedDiscount(15)->create();

        $customer = Customer::factory()->forTenant($tenant->id)->withPricingTier($tier->id)->create();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 1,
                'customer_id' => $customer->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 100.00,
                        'final_price' => 85.00,
                        'discount' => 15.00,
                    ],
                ],
            ]);
    }

    public function test_calculate_price_with_quantity_based_rule(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $tier = PricingTier::factory()->forTenant($tenant->id)->gold()->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 10]);

        // Bulk discount: 10% off for quantities 10-50
        PricingRule::factory()->forTenant($tenant->id)->forPricingTier($tier->id)->create([
            'type' => 'percentage',
            'operation' => 'subtract',
            'value' => 10,
            'min_quantity' => 10,
            'max_quantity' => 50,
        ]);

        $customer = Customer::factory()->forTenant($tenant->id)->withPricingTier($tier->id)->create();

        // Test with quantity that qualifies for discount
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 20,
                'customer_id' => $customer->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 10.00,
                        'final_price' => 9.00, // 10% off
                        'discount' => 1.00,
                    ],
                    'quantity' => 20,
                ],
            ]);

        // Test with quantity that doesn't qualify
        $response2 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 5,
                'customer_id' => $customer->id,
            ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 10.00,
                        'final_price' => 10.00, // No discount
                        'discount' => 0.00,
                    ],
                    'quantity' => 5,
                ],
            ]);
    }

    public function test_calculate_cart_price(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $tier = PricingTier::factory()->forTenant($tenant->id)->gold()->create();
        $product1 = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);
        $product2 = Product::factory()->forTenant($tenant->id)->create(['price' => 50]);

        PricingRule::factory()->forTenant($tenant->id)->forPricingTier($tier->id)->percentageDiscount(10)->create();

        $customer = Customer::factory()->forTenant($tenant->id)->withPricingTier($tier->id)->create();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate-cart", [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 2],
                    ['product_id' => $product2->id, 'quantity' => 3],
                ],
                'customer_id' => $customer->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'subtotal' => 350.00, // (100*2) + (50*3)
                        'discount' => 35.00,  // 10% off
                        'total' => 315.00,
                    ],
                ],
            ]);
    }

    public function test_calculate_price_without_customer_uses_no_tier_rules(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);

        // Create a tier-specific rule (should not apply)
        $tier = PricingTier::factory()->forTenant($tenant->id)->gold()->create();
        PricingRule::factory()->forTenant($tenant->id)->forPricingTier($tier->id)->percentageDiscount(50)->create();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 1,
                // No customer_id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 100.00,
                        'final_price' => 100.00, // No discount applied
                        'discount' => 0.00,
                    ],
                ],
            ]);
    }

    public function test_calculate_price_with_general_rule_applies_to_all(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);

        // Create a general rule (no tier) - applies to everyone
        PricingRule::factory()->forTenant($tenant->id)->create([
            'pricing_tier_id' => null,
            'type' => 'percentage',
            'operation' => 'subtract',
            'value' => 5,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/prices/calculate", [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pricing' => [
                        'base_price' => 100.00,
                        'final_price' => 95.00, // 5% off for everyone
                        'discount' => 5.00,
                    ],
                ],
            ]);
    }
}
