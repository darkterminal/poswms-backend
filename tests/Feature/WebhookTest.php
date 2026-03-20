<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assign admin role to the user
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin', 'tenant_id' => $this->tenant->id],
            ['name' => 'Admin', 'description' => 'Administrator with full access']
        );
        $this->adminUser->assignRole($adminRole);
    }

    public function test_admin_can_list_webhooks(): void
    {
        Webhook::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/webhooks");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_webhook(): void
    {
        $payload = [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'test-secret',
            'events' => ['order.created', 'order.updated'],
            'active' => true,
            'content_type' => 'json',
            'headers' => ['X-Custom-Header' => 'test'],
            'retry_count' => 5,
            'timeout' => 60,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook created successfully',
            ])
            ->assertJsonPath('data.name', 'Test Webhook')
            ->assertJsonPath('data.url', 'https://example.com/webhook');

        $this->assertDatabaseHas('webhooks', [
            'name' => 'Test Webhook',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_admin_can_view_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $webhook->id);
    }

    public function test_admin_can_update_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'name' => 'Updated Webhook Name',
            'url' => 'https://updated-example.com/webhook',
            'active' => false,
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook updated successfully',
            ])
            ->assertJsonPath('data.name', 'Updated Webhook Name');

        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'name' => 'Updated Webhook Name',
            'active' => false,
        ]);
    }

    public function test_admin_can_delete_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook deleted successfully',
            ]);

        $this->assertDatabaseMissing('webhooks', [
            'id' => $webhook->id,
        ]);
    }

    public function test_admin_can_test_webhook(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/test");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_id' => $webhook->id,
            'success' => true,
        ]);
    }

    public function test_admin_can_view_delivery_attempts(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        WebhookDeliveryAttempt::factory()->count(3)->create([
            'webhook_id' => $webhook->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/attempts");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_access_webhooks(): void
    {
        $nonAdminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assign viewer role (not admin)
        $viewerRole = Role::firstOrCreate(
            ['slug' => 'viewer', 'tenant_id' => $this->tenant->id],
            ['name' => 'Viewer', 'description' => 'Read-only access']
        );
        $nonAdminUser->assignRole($viewerRole);

        $response = $this->actingAs($nonAdminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/webhooks");

        $response->assertStatus(403);
    }

    public function test_webhook_requires_valid_url(): void
    {
        $payload = [
            'name' => 'Test Webhook',
            'url' => 'invalid-url',
            'events' => ['order.created'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_webhook_requires_events(): void
    {
        $payload = [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('events');
    }

    public function test_webhook_service_triggers_webhooks(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'events' => ['order.created'],
        ]);

        $service = app(WebhookService::class);
        $result = $service->trigger('order.created', ['order_id' => 123], $this->tenant->id);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(1, $result['webhooks_found']);

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_id' => $webhook->id,
            'event_type' => 'order.created',
            'success' => true,
        ]);
    }

    public function test_webhook_service_only_triggers_for_event(): void
    {
        Http::fake();

        Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['order.created'],
        ]);

        Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['product.updated'],
        ]);

        $service = app(WebhookService::class);
        $result = $service->trigger('order.created', ['order_id' => 123], $this->tenant->id);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(1, $result['webhooks_found']);
    }

    public function test_webhook_service_only_triggers_for_active_webhooks(): void
    {
        Http::fake();

        Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['order.created'],
            'active' => true,
        ]);

        Webhook::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['order.created'],
            'active' => false,
        ]);

        $service = app(WebhookService::class);
        $result = $service->trigger('order.created', ['order_id' => 123], $this->tenant->id);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(1, $result['webhooks_found']);
    }

    public function test_webhook_signature_generation(): void
    {
        $service = app(WebhookService::class);
        $payload = ['event' => 'order.created', 'data' => ['order_id' => 123]];
        $secret = 'test-secret';

        $signature = $service->generateSignature($payload, $secret);

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 hex length
    }

    public function test_webhook_signature_verification(): void
    {
        $service = app(WebhookService::class);
        $payload = ['event' => 'order.created', 'data' => ['order_id' => 123]];
        $secret = 'test-secret';

        $signature = $service->generateSignature($payload, $secret);

        $this->assertTrue($service->verifySignature($payload, $signature, $secret));
        $this->assertFalse($service->verifySignature($payload, 'invalid-signature', $secret));
    }
}
