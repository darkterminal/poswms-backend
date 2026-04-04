<?php

namespace Tests\Feature\Admin;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
    }

    public function test_super_admin_can_list_api_keys_for_tenant(): void
    {
        ApiKey::factory()->count(3)->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_super_admin_can_create_api_key(): void
    {
        $data = [
            'name' => 'Test API Key',
            'abilities' => ['read', 'write'],
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys", $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.api_key.name', 'Test API Key')
            ->assertJsonStructure(['data' => ['full_key']]);
    }

    public function test_api_key_creation_requires_name(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys", [
                'abilities' => ['read'],
            ]);

        $response->assertStatus(422);
    }

    public function test_super_admin_can_view_single_api_key(): void
    {
        $apiKey = ApiKey::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $apiKey->id);
    }

    public function test_super_admin_can_update_api_key(): void
    {
        $apiKey = ApiKey::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_super_admin_can_delete_api_key(): void
    {
        $apiKey = ApiKey::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('api_keys', ['id' => $apiKey->id]);
    }

    public function test_super_admin_can_regenerate_api_key(): void
    {
        $apiKey = ApiKey::factory()->for($this->tenant)->create();
        $oldKey = $apiKey->key;

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}/regenerate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['full_key', 'key_preview']]);

        $apiKey->refresh();
        $this->assertNotEquals($oldKey, $apiKey->key);
    }

    public function test_super_admin_can_get_api_key_stats(): void
    {
        ApiKey::factory()->count(3)->for($this->tenant)->create();
        ApiKey::factory()->expired()->for($this->tenant)->create();
        ApiKey::factory()->used()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys/stats");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 5);
    }

    public function test_api_key_list_returns_key_preview_not_full_key(): void
    {
        $apiKey = ApiKey::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys");

        $response->assertOk();
        $data = $response->json('data.0');
        $this->assertStringContainsString('...', $data['key_preview']);
        $this->assertNotEquals($apiKey->key, $data['key_preview']);
    }

    public function test_expired_api_keys_are_marked_correctly(): void
    {
        $expiredKey = ApiKey::factory()->expired()->for($this->tenant)->create();
        $activeKey = ApiKey::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/admin/tenants/{$this->tenant->id}/api-keys");

        $response->assertOk();
        $keys = collect($response->json('data'));
        $this->assertTrue($keys->firstWhere('id', $expiredKey->id)['is_expired']);
        $this->assertFalse($keys->firstWhere('id', $activeKey->id)['is_expired']);
    }
}
