<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PricingTier;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_can_create_customer(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/customers", [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '123-456-7890',
                'company' => 'Acme Corp',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('customers', ['name' => 'John Doe', 'email' => 'john@example.com']);
    }

    public function test_can_list_customers(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        Customer::factory()->forTenant($tenant->id)->count(3)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/customers");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['customers' => [['id', 'name', 'email']]]]);
    }

    public function test_can_get_single_customer(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $customer = Customer::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['customer' => ['id' => $customer->id]]]);
    }

    public function test_can_update_customer(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $customer = Customer::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson("/api/v1/tenants/{$tenant->id}/customers/{$customer->id}", [
                'name' => 'Jane Doe',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jane Doe']);
    }

    public function test_can_delete_customer(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $customer = Customer::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson("/api/v1/tenants/{$tenant->id}/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_can_create_customer_with_pricing_tier(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $pricingTier = PricingTier::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/customers", [
                'name' => 'Premium Customer',
                'email' => 'premium@example.com',
                'pricing_tier_id' => $pricingTier->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', [
            'name' => 'Premium Customer',
            'pricing_tier_id' => $pricingTier->id,
        ]);
    }

    public function test_customer_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->active()->create();
        $tenant2 = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant1);
        $customer = Customer::factory()->forTenant($tenant2->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant1->id}/customers/{$customer->id}");

        $response->assertStatus(404);
    }
}
