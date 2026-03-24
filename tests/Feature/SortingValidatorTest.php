<?php

namespace Tests\Feature;

use App\Services\SortingValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tests for the SortingValidator service.
 *
 * These tests verify that the sorting validator properly prevents SQL injection
 * via ORDER BY clauses while maintaining backward compatibility.
 */
class SortingValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SortingValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(SortingValidator::class);
    }

    /**
     * Test valid sort fields are accepted.
     */
    public function test_valid_sort_field_is_accepted(): void
    {
        $request = Request::create('/api/test?sort_by=name', 'GET');

        $result = $this->validator->validateSortField(
            $request,
            'created_at',
            ['name', 'email', 'created_at']
        );

        $this->assertEquals('name', $result);
    }

    /**
     * Test invalid sort field falls back to default.
     */
    public function test_invalid_sort_field_falls_back_to_default(): void
    {
        $request = Request::create('/api/test?sort_by=invalid_field', 'GET');

        $result = $this->validator->validateSortField(
            $request,
            'created_at',
            ['name', 'email', 'created_at']
        );

        $this->assertEquals('created_at', $result);
    }

    /**
     * Test SQL injection attempts are blocked.
     */
    public function test_sql_injection_attempts_are_blocked(): void
    {
        $injectionPayloads = [
            'name; DROP TABLE users--',
            'created_at UNION SELECT * FROM users',
            'id, (SELECT SLEEP(5))',
            'name/*comment*/',
            "updated_at' OR '1'='1",
            "id); WAITFOR DELAY '0:0:5'--",
            "name BENCHMARK(10000000, SHA1('test'))",
            'created_at INFORMATION_SCHEMA',
        ];

        foreach ($injectionPayloads as $payload) {
            $request = Request::create('/api/test?sort_by=' . urlencode($payload), 'GET');

            $result = $this->validator->validateSortField(
                $request,
                'created_at',
                ['name', 'email', 'created_at', 'updated_at']
            );

            $this->assertEquals(
                'created_at',
                $result,
                "Payload '{$payload}' should be blocked and fall back to default"
            );
        }
    }

    /**
     * Test dangerous characters are detected.
     */
    public function test_dangerous_characters_are_detected(): void
    {
        $dangerousChars = [';', '--', '/*', '*/', '#', '(', ')', ',', "'", '"', '`', '[', ']', ' '];

        foreach ($dangerousChars as $char) {
            $payload = 'name' . $char . 'DROP';
            $request = Request::create('/api/test?sort_by=' . urlencode($payload), 'GET');

            $result = $this->validator->validateSortField(
                $request,
                'created_at',
                ['name', 'email', 'created_at']
            );

            $this->assertEquals(
                'created_at',
                $result,
                "Character '{$char}' should trigger fallback to default"
            );
        }
    }

    /**
     * Test sort order validation.
     */
    public function test_sort_order_validation(): void
    {
        // Valid ASC
        $request = Request::create('/api/test?sort_order=asc', 'GET');
        $this->assertEquals('asc', $this->validator->validateSortOrder($request, 'desc'));

        // Valid DESC
        $request = Request::create('/api/test?sort_order=desc', 'GET');
        $this->assertEquals('desc', $this->validator->validateSortOrder($request, 'asc'));

        // Case insensitive
        $request = Request::create('/api/test?sort_order=ASC', 'GET');
        $this->assertEquals('asc', $this->validator->validateSortOrder($request, 'desc'));

        $request = Request::create('/api/test?sort_order=DESC', 'GET');
        $this->assertEquals('desc', $this->validator->validateSortOrder($request, 'asc'));

        // Invalid falls back to default
        $request = Request::create('/api/test?sort_order=invalid', 'GET');
        $this->assertEquals('desc', $this->validator->validateSortOrder($request, 'desc'));

        // SQL injection in sort order
        $request = Request::create('/api/test?sort_order=desc;DROP TABLE users', 'GET');
        $this->assertEquals('desc', $this->validator->validateSortOrder($request, 'asc'));
    }

    /**
     * Test getValidatedSortParams returns correct array.
     */
    public function test_get_validated_sort_params(): void
    {
        $request = Request::create('/api/test?sort_by=name&sort_order=asc', 'GET');

        $params = $this->validator->getValidatedSortParams(
            $request,
            'created_at',
            ['name', 'email', 'created_at'],
            'desc'
        );

        $this->assertEquals('name', $params['sort_by']);
        $this->assertEquals('asc', $params['sort_order']);
    }

    /**
     * Test empty whitelist uses safe default.
     */
    public function test_empty_whitelist_uses_safe_default(): void
    {
        $request = Request::create('/api/test?sort_by=name', 'GET');

        $result = $this->validator->validateSortField($request, 'id', []);

        // Empty whitelist should fall back to default for safety
        $this->assertEquals('id', $result);
    }

    /**
     * Test null whitelist uses service defaults.
     */
    public function test_null_whitelist_uses_service_defaults(): void
    {
        $request = Request::create('/api/test?sort_by=name', 'GET');

        $result = $this->validator->validateSortField($request, 'id', null);

        $this->assertEquals('name', $result);
    }

    /**
     * Test empty input falls back to default.
     */
    public function test_empty_input_falls_back_to_default(): void
    {
        $request = Request::create('/api/test?sort_by=', 'GET');

        $result = $this->validator->validateSortField($request, 'updated_at', ['name', 'email']);

        $this->assertEquals('updated_at', $result);
    }

    /**
     * Test non-string input is handled safely.
     */
    public function test_non_string_input_is_handled_safely(): void
    {
        // Array input should be rejected safely
        $request = Request::create('/api/test?sort_by[]=name', 'GET');

        $result = $this->validator->validateSortField($request, 'created_at', ['name', 'email']);

        $this->assertEquals('created_at', $result, 'Array input should fall back to default');
    }

    /**
     * Test SQL keywords are blocked.
     */
    public function test_sql_keywords_are_blocked(): void
    {
        $keywords = [
            'select', 'insert', 'update', 'delete', 'drop', 'union',
            'exec', 'execute', 'waitfor', 'delay', 'benchmark', 'sleep',
        ];

        foreach ($keywords as $keyword) {
            $request = Request::create('/api/test?sort_by=' . urlencode($keyword), 'GET');

            $result = $this->validator->validateSortField(
                $request,
                'created_at',
                ['name', 'email', 'created_at', $keyword] // Even if in whitelist
            );

            $this->assertEquals(
                'created_at',
                $result,
                "SQL keyword '{$keyword}' should be blocked"
            );
        }
    }

    /**
     * Test whitespace trimming.
     */
    public function test_whitespace_is_trimmed(): void
    {
        $request = Request::create('/api/test?sort_by=%20name%20', 'GET');

        $result = $this->validator->validateSortField(
            $request,
            'created_at',
            ['name', 'email', 'created_at']
        );

        $this->assertEquals('name', $result);
    }

    /**
     * Test strict whitelist comparison.
     */
    public function test_strict_whitelist_comparison(): void
    {
        // Case sensitive
        $request = Request::create('/api/test?sort_by=Name', 'GET');

        $result = $this->validator->validateSortField(
            $request,
            'created_at',
            ['name', 'email', 'created_at']
        );

        $this->assertEquals('created_at', $result, 'Whitelist should be case-sensitive');
    }
}
