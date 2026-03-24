<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use App\Services\UrlValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookSsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private UrlValidationService $urlValidator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant first
        $this->tenant = Tenant::factory()->create();

        // Seed roles and permissions for the tenant
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Give user admin role for webhook permissions
        $this->user->assignRole('admin');

        Sanctum::actingAs($this->user);
        $this->urlValidator = app(UrlValidationService::class);
    }

    public function test_valid_public_url_is_accepted(): void
    {
        $result = $this->urlValidator->validateUrl('https://example.com/webhook');

        $this->assertTrue($result['valid']);
    }

    public function test_localhost_url_is_blocked(): void
    {
        $blockedUrls = [
            'http://localhost/webhook',
            'http://127.0.0.1/webhook',
            'http://127.0.0.1:8080/webhook',
        ];

        foreach ($blockedUrls as $url) {
            $result = $this->urlValidator->validateUrl($url);

            $this->assertFalse($result['valid'], "URL should be blocked: {$url}");
        }
    }

    public function test_private_ip_urls_are_blocked(): void
    {
        $blockedUrls = [
            'http://192.168.1.1/webhook',
            'http://192.168.0.100:8080/webhook',
            'http://10.0.0.1/webhook',
            'http://10.10.10.10/webhook',
            'http://172.16.0.1/webhook',
            'http://172.31.255.255/webhook',
        ];

        foreach ($blockedUrls as $url) {
            $result = $this->urlValidator->validateUrl($url);

            $this->assertFalse($result['valid'], "URL should be blocked: {$url}");
        }
    }

    public function test_link_local_addresses_are_blocked(): void
    {
        $blockedUrls = [
            'http://169.254.169.254/latest/meta-data/', // AWS metadata
            'http://169.254.1.1/webhook',
        ];

        foreach ($blockedUrls as $url) {
            $result = $this->urlValidator->validateUrl($url);

            $this->assertFalse($result['valid'], "URL should be blocked: {$url}");
        }
    }

    public function test_cloud_metadata_endpoints_are_blocked(): void
    {
        $blockedHostnames = [
            'http://metadata.google.internal/computeMetadata/v1/',
            'http://metadata/computeMetadata/v1/',
        ];

        foreach ($blockedHostnames as $url) {
            $result = $this->urlValidator->validateUrl($url);

            $this->assertFalse($result['valid'], "URL should be blocked: {$url}");
        }
    }

    public function test_webhook_store_endpoint_rejects_ssrf_urls(): void
    {
        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks", [
            'name' => 'Malicious Webhook',
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'events' => ['order.created'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SSRF_PROTECTION');
    }

    public function test_webhook_update_endpoint_rejects_ssrf_urls(): void
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://valid-example.com/webhook',
        ]);

        $response = $this->putJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}", [
            'url' => 'http://192.168.1.1/malicious',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SSRF_PROTECTION');
    }

    public function test_webhook_test_endpoint_validates_url(): void
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'http://127.0.0.1:8080/test',
        ]);

        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/test");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SSRF_PROTECTION');
    }

    public function test_valid_webhook_creation_succeeds(): void
    {
        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/webhooks", [
            'name' => 'Valid Webhook',
            'url' => 'https://hooks.example.com/webhook',
            'events' => ['order.created'],
            'active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('webhooks', [
            'name' => 'Valid Webhook',
            'active' => true,
        ]);
    }
}
