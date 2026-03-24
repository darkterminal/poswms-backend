<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SqlInjectionPreventionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        Sanctum::actingAs($this->superAdmin);
    }

    public function test_valid_sort_field_is_accepted(): void
    {
        $response = $this->getJson('/api/v1/admin/users?sort_by=name&sort_order=asc');

        // SearchUsersRequest validates sort_by, so valid fields pass
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_invalid_sort_field_is_rejected_by_validation(): void
    {
        $response = $this->getJson('/api/v1/admin/users?sort_by=SQL_INJECTION_ATTACK&sort_order=asc');

        // SearchUsersRequest validates sort_by against whitelist
        $response->assertStatus(422);
    }

    public function test_sql_injection_in_sort_field_is_blocked(): void
    {
        $maliciousInputs = [
            'name; DROP TABLE users; --',
            'name ASC, (SELECT * FROM users) AS test',
            "created_at; WAITFOR DELAY '0:0:5' --",
            'email DESC UNION SELECT * FROM passwords',
            '1=1 --',
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->getJson('/api/v1/admin/users?sort_by=' . urlencode($input));

            // Should be rejected by validation
            $response->assertStatus(422);
        }
    }

    public function test_invalid_sort_order_is_rejected_by_validation(): void
    {
        $response = $this->getJson('/api/v1/admin/users?sort_by=name&sort_order=INVALID');

        $response->assertStatus(422);
    }

    public function test_sort_order_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/admin/users?sort_by=name&sort_order=ASC');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_allowed_sort_fields_work_correctly(): void
    {
        $allowedFields = ['name', 'email', 'created_at'];

        foreach ($allowedFields as $field) {
            $response = $this->getJson('/api/v1/admin/users?sort_by=' . $field . '&sort_order=desc');

            $response->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }
}
