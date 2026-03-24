<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant for foreign key relationships
        $this->tenant = Tenant::factory()->create();
    }

    /**
     * Test that webhook secret is encrypted at rest.
     */
    public function test_webhook_secret_is_encrypted_in_database(): void
    {
        $plaintextSecret = 'whsec_test_webhook_secret_12345';

        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => $plaintextSecret,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextSecret, $webhook->secret);

        // Verify the value in the database is encrypted (not plaintext)
        $rawValue = DB::table('webhooks')
            ->where('id', $webhook->id)
            ->value('secret');

        $this->assertNotEquals($plaintextSecret, $rawValue);
        $this->assertNotEmpty($rawValue);
        $this->assertNotEquals('whsec_test_webhook_secret_12345', $rawValue);
    }

    /**
     * Test that webhook headers are encrypted in database.
     */
    public function test_webhook_headers_are_encrypted_in_database(): void
    {
        $sensitiveHeaders = [
            'Authorization' => 'Bearer secret_token_12345',
            'X-API-Key' => 'api_key_67890',
            'Custom-Header' => 'sensitive_value',
        ];

        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'headers' => $sensitiveHeaders,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($sensitiveHeaders, $webhook->headers);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('webhooks')
            ->where('id', $webhook->id)
            ->value('headers');

        $this->assertNotEquals(json_encode($sensitiveHeaders), $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that customer tax_id is encrypted at rest.
     */
    public function test_customer_tax_id_is_encrypted_in_database(): void
    {
        $plaintextTaxId = 'TAX-123456789';

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tax_id' => $plaintextTaxId,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextTaxId, $customer->tax_id);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('customers')
            ->where('id', $customer->id)
            ->value('tax_id');

        $this->assertNotEquals($plaintextTaxId, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that customer email is encrypted at rest.
     */
    public function test_customer_email_is_encrypted_in_database(): void
    {
        $plaintextEmail = 'customer@example.com';

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $plaintextEmail,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextEmail, $customer->email);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('customers')
            ->where('id', $customer->id)
            ->value('email');

        $this->assertNotEquals($plaintextEmail, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that customer phone is encrypted at rest.
     */
    public function test_customer_phone_is_encrypted_in_database(): void
    {
        $plaintextPhone = '+1-555-123-4567';

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => $plaintextPhone,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextPhone, $customer->phone);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('customers')
            ->where('id', $customer->id)
            ->value('phone');

        $this->assertNotEquals($plaintextPhone, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that tenant email is encrypted at rest.
     */
    public function test_tenant_email_is_encrypted_in_database(): void
    {
        $plaintextEmail = 'tenant@company.com';

        $tenant = Tenant::factory()->create([
            'email' => $plaintextEmail,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextEmail, $tenant->email);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('email');

        $this->assertNotEquals($plaintextEmail, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that tenant settings are encrypted at rest.
     */
    public function test_tenant_settings_are_encrypted_in_database(): void
    {
        $sensitiveSettings = [
            'api_key' => 'sk_test_12345',
            'secret_key' => 'very_secret_value',
            'integration_token' => 'token_67890',
        ];

        $tenant = Tenant::factory()->create([
            'settings' => $sensitiveSettings,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($sensitiveSettings, $tenant->settings);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('settings');

        $this->assertNotEquals(json_encode($sensitiveSettings), $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that store email is encrypted at rest.
     */
    public function test_store_email_is_encrypted_in_database(): void
    {
        $plaintextEmail = 'store@example.com';

        $store = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $plaintextEmail,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextEmail, $store->email);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('stores')
            ->where('id', $store->id)
            ->value('email');

        $this->assertNotEquals($plaintextEmail, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that warehouse phone is encrypted at rest.
     */
    public function test_warehouse_phone_is_encrypted_in_database(): void
    {
        $plaintextPhone = '+1-555-987-6543';

        $warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => $plaintextPhone,
        ]);

        // Verify we can access the plaintext via the model
        $this->assertEquals($plaintextPhone, $warehouse->phone);

        // Verify the value in the database is encrypted
        $rawValue = DB::table('warehouses')
            ->where('id', $warehouse->id)
            ->value('phone');

        $this->assertNotEquals($plaintextPhone, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that encrypted values can be decrypted correctly.
     */
    public function test_encrypted_values_are_decrypted_correctly(): void
    {
        $originalSecret = 'whsec_original_secret_value';
        $originalHeaders = ['X-Custom' => 'custom_value'];
        $originalTaxId = 'TAX-987654321';

        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => $originalSecret,
            'headers' => $originalHeaders,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tax_id' => $originalTaxId,
        ]);

        // Reload from database
        $webhook->refresh();
        $customer->refresh();

        // Verify decryption works correctly
        $this->assertEquals($originalSecret, $webhook->secret);
        $this->assertEquals($originalHeaders, $webhook->headers);
        $this->assertEquals($originalTaxId, $customer->tax_id);
    }

    /**
     * Test that null values are handled correctly for encrypted fields.
     */
    public function test_null_values_in_encrypted_fields(): void
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => null,
            'headers' => null,
        ]);

        $this->assertNull($webhook->secret);
        $this->assertNull($webhook->headers);
    }

    /**
     * Test that empty string values are handled correctly for encrypted fields.
     */
    public function test_empty_string_in_encrypted_fields(): void
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => '',
        ]);

        $this->assertEquals('', $webhook->secret);
    }
}
