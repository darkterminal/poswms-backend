<?php

declare(strict_types = 1);

/**
 * REST Client Example - Usage Demonstrations.
 *
 * This file demonstrates various ways to use the RestClient for API testing.
 */

require_once __DIR__ . '/RestClient.php';

use Tests\Api\RestClient;

// ==================== Example 1: Basic Usage ====================

echo "Example 1: Basic Usage with Default Settings\n";
echo "=============================================\n\n";

$client = new RestClient;
$client->setVerbose(false);

// Run all tests
// $client->runAllTests();

// ==================== Example 2: Custom Configuration ====================

echo "Example 2: Custom Configuration\n";
echo "================================\n\n";

$client = new RestClient(
    baseUrl: 'http://localhost:8000',
    tenantId: 1,
    email: 'admin@demo.com',
    password: 'password'
);

$client->setVerbose(false);

// Run all tests
// $client->runAllTests();

// ==================== Example 3: Test Specific Endpoint ====================

echo "Example 3: Test Specific Endpoint\n";
echo "==================================\n\n";

$client = new RestClient;

// Test only stores endpoint
// $client->runEndpointTest('stores');

// Test only products endpoint
// $client->runEndpointTest('products');

// ==================== Example 4: Verbose Mode ====================

echo "Example 4: Verbose Mode for Debugging\n";
echo "======================================\n\n";

$client = new RestClient;
$client->setVerbose(true);

// This will show detailed request/response information
// $client->runAllTests();

// ==================== Example 5: Command Line Usage ====================

echo "Example 5: Command Line Examples\n";
echo "=================================\n\n";

$examples = [
    'Run all tests with defaults' => 'php tests/api/RestClient.php',
    'Run with custom base URL' => 'php tests/api/RestClient.php --base-url=http://localhost:8000',
    'Run with custom tenant' => 'php tests/api/RestClient.php --tenant=2',
    'Run with custom credentials' => 'php tests/api/RestClient.php --email=admin@example.com --password=secret',
    'Test specific endpoint' => 'php tests/api/RestClient.php --endpoint=products',
    'Enable verbose output' => 'php tests/api/RestClient.php --verbose',
    'Combine multiple options' => 'php tests/api/RestClient.php --base-url=http://localhost:8000 --tenant=1 --email=admin@example.com --password=secret --endpoint=orders --verbose',
];

foreach ($examples as $description => $command) {
    echo "{$description}:\n";
    echo "  {$command}\n\n";
}

// ==================== Example 6: Extending the Client ====================

echo "Example 6: Extending the RestClient\n";
echo "====================================\n\n";

class CustomRestClient extends RestClient
{
    /**
     * Add custom test for a specific scenario.
     */
    public function testCustomScenario(): void
    {
        echo "--- Testing Custom Scenario ---\n\n";

        $this->test('Custom API Test', function () {
            // Your custom test logic here
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/dashboard");

            return $response['status'] === 200;
        });

        echo "\n";
    }

    /**
     * Override authentication to use different credentials.
     */
    public function authenticateAsAdmin(): void
    {
        $this->email = 'admin@example.com';
        $this->password = 'admin-password';
        $this->testAuthentication();
    }
}

$client = new CustomRestClient;
// $client->authenticateAsAdmin();
// $client->testCustomScenario();

// ==================== Example 7: Batch Testing ====================

echo "Example 7: Batch Testing Multiple Tenants\n";
echo "==========================================\n\n";

$tenants = [1, 2, 3];

foreach ($tenants as $tenantId) {
    echo "Testing Tenant ID: {$tenantId}\n";

    $client = new RestClient(tenantId: $tenantId);
    // $client->runAllTests();

    echo "\n";
}

// ==================== Example 8: Selective Testing ====================

echo "Example 8: Selective Testing\n";
echo "=============================\n\n";

$client = new RestClient;

// Test only core resources
$coreResources = ['stores', 'warehouses', 'products', 'customers', 'orders'];

foreach ($coreResources as $resource) {
    echo "Testing: {$resource}\n";
    // $client->runEndpointTest($resource);
}

// ==================== Quick Reference ====================

echo "\n";
echo "=============================================\n";
echo "Quick Reference\n";
echo "=============================================\n\n";

echo "Available Endpoints to Test:\n";
$endpoints = [
    'authentication',
    'stores',
    'warehouses',
    'categories',
    'products',
    'customers',
    'inventory',
    'orders',
    'pricingTiers',
    'pricingRules',
    'roles',
    'permissions',
    'reports',
    'webhooks',
    'auditLogs',
];

foreach ($endpoints as $endpoint) {
    echo "  - {$endpoint}\n";
}

echo "\n";
echo "To test: php tests/api/RestClient.php --endpoint={endpoint}\n";
echo "\n";
