<?php

declare(strict_types = 1);

namespace Tests\Api;

/**
 * Mini REST Client for Testing POS WMS API Endpoints.
 *
 * This script provides a simple way to test all API endpoints documented
 * in docs/api/ directory. It uses cURL to make HTTP requests.
 *
 * Usage:
 *   php tests/api/RestClient.php
 *   php tests/api/RestClient.php --base-url=http://localhost:8000
 *   php tests/api/RestClient.php --tenant=1 --email=test@example.com --password=password
 *
 * Options:
 *   --base-url     Base URL of the API (default: http://localhost:8000)
 *   --tenant       Tenant ID to use for tests (default: 1)
 *   --email        User email for authentication (default: test@example.com)
 *   --password     User password for authentication (default: password)
 *   --endpoint     Specific endpoint to test (e.g., "stores", "products")
 *   --verbose      Enable verbose output
 *   --help         Show this help message
 */
class RestClient
{
    private string $baseUrl;
    private int $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;
    private bool $verbose = false;
    private array $testResults = [];
    private array $createdResources = [];

    public function __construct(
        string $baseUrl = 'http://localhost:8000',
        int $tenantId = 1,
        string $email = 'admin@demo.com',
        string $password = 'password'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->tenantId = $tenantId;
        $this->email = $email;
        $this->password = $password;
    }

    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * Run all API tests.
     */
    public function runAllTests(): void
    {
        echo "===========================================\n";
        echo "POS WMS API - Mini REST Client Tests\n";
        echo "===========================================\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "Tenant ID: {$this->tenantId}\n";
        echo "===========================================\n\n";

        // Authentication first
        $this->testAuthentication();

        if (! $this->token) {
            echo "❌ Authentication failed. Cannot proceed with other tests.\n";
            $this->printSummary();

            return;
        }

        // Run all resource tests
        $this->testStores();
        $this->testWarehouses();
        $this->testCategories();
        $this->testProducts();
        $this->testCustomers();
        $this->testInventory();
        $this->testOrders();
        $this->testPricingTiers();
        $this->testPricingRules();
        $this->testRoles();
        $this->testPermissions();
        $this->testReports();
        $this->testWebhooks();
        $this->testAuditLogs();

        // Cleanup
        $this->cleanup();

        $this->printSummary();
    }

    /**
     * Run a specific endpoint test.
     */
    public function runEndpointTest(string $endpoint): void
    {
        echo "Testing endpoint: {$endpoint}\n\n";

        // Convert endpoint to method name (e.g., "stores" -> "testStores", "authentication" -> "testAuthentication")
        $method = 'test' . ucfirst($endpoint);

        // Authenticate first (unless testing authentication itself)
        if ($endpoint !== 'authentication' && ! $this->token) {
            echo "Authenticating first...\n\n";
            $this->testAuthentication();

            if (! $this->token) {
                echo "❌ Authentication failed. Cannot proceed with {$endpoint} tests.\n";

                return;
            }
        }

        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            echo "❌ No test method found for: {$endpoint}\n";
            echo "   Available endpoints: authentication, stores, warehouses, categories, products, customers, inventory, orders, pricingTiers, pricingRules, roles, permissions, reports, webhooks, auditLogs\n";
        }
    }

    // ==================== Authentication ====================

    private function testAuthentication(): void
    {
        echo "--- Testing Authentication ---\n\n";

        // Test Login
        $this->test('POST /auth/login', function () {
            $response = $this->post('/api/v1/auth/login', [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response['status'] === 200) {
                $this->token = $response['data']['data']['token'] ?? null;

                return $this->token !== null;
            }

            return false;
        });

        // Test Get Current User (if authenticated)
        if ($this->token) {
            $this->test('GET /auth/me', function () {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/auth/me");

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Stores ====================

    private function testStores(): void
    {
        echo "--- Testing Stores ---\n\n";

        $this->test('POST /tenants/{id}/stores', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/stores", [
                'name' => 'Test Store ' . time(),
                'code' => 'TEST-' . rand(1000, 9999),
                'address' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'TS',
                'country' => 'Test Country',
                'postal_code' => '12345',
                'phone' => '+1-555-0100',
                'email' => 'teststore@example.com',
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $storeId = $response['data']['data']['store']['id'] ?? null;
                $this->createdResources['store'] = $storeId;
                if ($this->verbose) {
                    echo "    [DEBUG] Created store with ID: {$storeId}\n";
                }

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/stores', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/stores");

            return $response['status'] === 200 && isset($response['data']['data']['stores']);
        });

        if (isset($this->createdResources['store'])) {
            $storeId = $this->createdResources['store'];
            if ($this->verbose) {
                echo "    [DEBUG] Using store ID: {$storeId}\n";
            }

            $this->test('GET /tenants/{id}/stores/{id}', function () use ($storeId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/stores/{$storeId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/stores/{id}', function () use ($storeId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/stores/{$storeId}", [
                    'name' => 'Updated Test Store',
                ]);

                return $response['status'] === 200;
            });
        } else {
            if ($this->verbose) {
                echo "    [DEBUG] No store ID available for GET/PUT tests\n";
            }
        }

        echo "\n";
    }

    // ==================== Warehouses ====================

    private function testWarehouses(): void
    {
        echo "--- Testing Warehouses ---\n\n";

        $this->test('POST /tenants/{id}/warehouses', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/warehouses", [
                'name' => 'Test Warehouse ' . time(),
                'code' => 'TW-' . rand(1000, 9999),
                'address' => '456 Warehouse Blvd',
                'city' => 'Warehouse City',
                'state' => 'WS',
                'country' => 'Test Country',
                'postal_code' => '54321',
                'phone' => '+1-555-0200',
                'email' => 'warehouse@example.com',
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['warehouse'] = $response['data']['data']['warehouse']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/warehouses', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/warehouses");

            return $response['status'] === 200 && isset($response['data']['data']['warehouses']);
        });

        if (isset($this->createdResources['warehouse'])) {
            $warehouseId = $this->createdResources['warehouse'];

            $this->test('GET /tenants/{id}/warehouses/{id}', function () use ($warehouseId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/warehouses/{$warehouseId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/warehouses/{id}', function () use ($warehouseId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/warehouses/{$warehouseId}", [
                    'name' => 'Updated Test Warehouse',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Categories ====================

    private function testCategories(): void
    {
        echo "--- Testing Categories ---\n\n";

        $this->test('POST /tenants/{id}/categories', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/categories", [
                'name' => 'Test Category ' . time(),
                'slug' => 'test-category-' . time(),
                'description' => 'Test category for API testing',
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['category'] = $response['data']['data']['category']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/categories', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/categories");

            return $response['status'] === 200 && isset($response['data']['data']['categories']);
        });

        if (isset($this->createdResources['category'])) {
            $categoryId = $this->createdResources['category'];

            $this->test('GET /tenants/{id}/categories/{id}', function () use ($categoryId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/categories/{$categoryId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/categories/{id}', function () use ($categoryId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/categories/{$categoryId}", [
                    'name' => 'Updated Test Category',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Products ====================

    private function testProducts(): void
    {
        echo "--- Testing Products ---\n\n";

        $this->test('POST /tenants/{id}/products', function () {
            $data = [
                'name' => 'Test Product ' . time(),
                'sku' => 'TP-' . rand(1000, 9999),
                'barcode' => '1234567890' . rand(1000, 9999),
                'description' => 'Test product for API testing',
                'price' => 99.99,
                'cost' => 50.00,
                'tax_rate' => 0.08,
                'unit' => 'piece',
                'min_stock' => 10,
                'max_stock' => 500,
                'track_inventory' => true,
                'active' => true,
            ];

            if (isset($this->createdResources['category'])) {
                $data['category_id'] = $this->createdResources['category'];
            }

            $response = $this->post("/api/v1/tenants/{$this->tenantId}/products", $data);

            if ($response['status'] === 201) {
                $this->createdResources['product'] = $response['data']['data']['product']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/products', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/products");

            return $response['status'] === 200 && isset($response['data']['data']['products']);
        });

        if (isset($this->createdResources['product'])) {
            $productId = $this->createdResources['product'];

            $this->test('GET /tenants/{id}/products/{id}', function () use ($productId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/products/{$productId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/products/{id}', function () use ($productId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/products/{$productId}", [
                    'price' => 89.99,
                    'name' => 'Updated Test Product',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Customers ====================

    private function testCustomers(): void
    {
        echo "--- Testing Customers ---\n\n";

        $this->test('POST /tenants/{id}/customers', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/customers", [
                'name' => 'Test Customer ' . time(),
                'email' => 'customer' . time() . '@example.com',
                'phone' => '+1-555-0300',
                'company' => 'Test Company',
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['customer'] = $response['data']['data']['customer']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/customers', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/customers");

            return $response['status'] === 200 && isset($response['data']['data']['customers']);
        });

        if (isset($this->createdResources['customer'])) {
            $customerId = $this->createdResources['customer'];

            $this->test('GET /tenants/{id}/customers/{id}', function () use ($customerId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/customers/{$customerId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/customers/{id}', function () use ($customerId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/customers/{$customerId}", [
                    'phone' => '+1-555-0399',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Inventory ====================

    private function testInventory(): void
    {
        echo "--- Testing Inventory ---\n\n";

        // Create inventory if we have product and warehouse
        if (isset($this->createdResources['product']) && isset($this->createdResources['warehouse'])) {
            $this->test('POST /tenants/{id}/inventory', function () {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/inventory", [
                    'product_id' => $this->createdResources['product'],
                    'warehouse_id' => $this->createdResources['warehouse'],
                    'quantity' => 100,
                    'reserved' => 5,
                    'cost' => 50.00,
                    'location' => 'A-12-3',
                ]);

                if ($response['status'] === 201) {
                    $this->createdResources['inventory'] = $response['data']['data']['inventory']['id'] ?? null;

                    return true;
                }

                return false;
            });
        }

        $this->test('GET /tenants/{id}/inventory', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/inventory");

            return $response['status'] === 200 && isset($response['data']['data']['inventories']);
        });

        if (isset($this->createdResources['inventory'])) {
            $inventoryId = $this->createdResources['inventory'];

            $this->test('GET /tenants/{id}/inventory/{id}', function () use ($inventoryId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/inventory/{$inventoryId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/inventory/{id}', function () use ($inventoryId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/inventory/{$inventoryId}", [
                    'quantity' => 150,
                ]);

                return $response['status'] === 200;
            });

            // Test transfer if we have a store
            if (isset($this->createdResources['store'])) {
                $this->test('POST /tenants/{id}/inventory/transfer', function () {
                    $response = $this->post("/api/v1/tenants/{$this->tenantId}/inventory/transfer", [
                        'product_id' => $this->createdResources['product'] ?? 1,
                        'quantity' => 10,
                        'from_warehouse_id' => $this->createdResources['warehouse'] ?? null,
                        'to_store_id' => $this->createdResources['store'] ?? null,
                        'reason' => 'Test transfer',
                    ]);

                    return $response['status'] === 200;
                });
            }
        }

        echo "\n";
    }

    // ==================== Orders ====================

    private function testOrders(): void
    {
        echo "--- Testing Orders ---\n\n";

        // Create dependencies if they don't exist (for individual endpoint testing)
        if (! isset($this->createdResources['store'])) {
            $this->test('POST /tenants/{id}/stores (dependency)', function () {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/stores", [
                    'name' => 'Order Test Store ' . time(),
                    'code' => 'ORD-TEST-' . rand(1000, 9999),
                    'email' => 'ordertest@example.com',
                    'active' => true,
                ]);

                if ($response['status'] === 201) {
                    $this->createdResources['store'] = $response['data']['data']['store']['id'] ?? null;

                    return true;
                }

                return false;
            });
        }

        if (! isset($this->createdResources['product'])) {
            $this->test('POST /tenants/{id}/products (dependency)', function () {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/products", [
                    'name' => 'Order Test Product ' . time(),
                    'sku' => 'ORD-TP-' . rand(1000, 9999),
                    'barcode' => '1234567890' . rand(1000, 9999),
                    'price' => 99.99,
                    'active' => true,
                ]);

                if ($response['status'] === 201) {
                    $this->createdResources['product'] = $response['data']['data']['product']['id'] ?? null;

                    return true;
                }

                return false;
            });
        }

        // Create order if we have required resources
        if (isset($this->createdResources['product']) && isset($this->createdResources['store'])) {
            $this->test('POST /tenants/{id}/orders', function () {
                $data = [
                    'order_number' => 'TEST-ORD-' . time(),  // Unique order number for testing
                    'store_id' => $this->createdResources['store'],
                    'status' => 'pending',
                    'type' => 'sale',
                    'payment_method' => 'credit_card',
                    'items' => [
                        [
                            'product_id' => $this->createdResources['product'],
                            'quantity' => 2,
                            'unit_price' => 99.99,
                        ],
                    ],
                ];

                if (isset($this->createdResources['customer'])) {
                    $data['customer_id'] = $this->createdResources['customer'];
                }

                $response = $this->post("/api/v1/tenants/{$this->tenantId}/orders", $data);

                if ($response['status'] === 201) {
                    $this->createdResources['order'] = $response['data']['data']['order']['id'] ?? null;

                    return true;
                }

                return false;
            });
        }

        $this->test('GET /tenants/{id}/orders', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/orders");

            return $response['status'] === 200 && isset($response['data']['data']['orders']);
        });

        if (isset($this->createdResources['order'])) {
            $orderId = $this->createdResources['order'];

            $this->test('GET /tenants/{id}/orders/{id}', function () use ($orderId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/orders/{$orderId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/orders/{id}', function () use ($orderId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/orders/{$orderId}", [
                    'status' => 'confirmed',
                ]);

                return $response['status'] === 200;
            });

            $this->test('POST /tenants/{id}/orders/{id}/confirm', function () use ($orderId) {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/orders/{$orderId}/confirm");

                return $response['status'] === 200;
            });

            $this->test('POST /tenants/{id}/orders/{id}/fulfill', function () use ($orderId) {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/orders/{$orderId}/fulfill");

                return $response['status'] === 200;
            });

            // Only test cancel if fulfill failed (can't cancel fulfilled orders)
            // Create a new order for cancel testing
            $this->test('POST /tenants/{id}/orders (for cancel test)', function () {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/orders", [
                    'order_number' => 'TEST-ORD-CANCEL-' . time(),
                    'store_id' => $this->createdResources['store'],
                    'status' => 'pending',
                    'type' => 'sale',
                    'payment_method' => 'credit_card',
                    'items' => [
                        [
                            'product_id' => $this->createdResources['product'],
                            'quantity' => 1,
                            'unit_price' => 49.99,
                        ],
                    ],
                ]);

                if ($response['status'] === 201) {
                    $this->createdResources['cancelOrder'] = $response['data']['data']['order']['id'] ?? null;

                    return true;
                }

                return false;
            });

            $this->test('POST /tenants/{id}/orders/{id}/cancel', function () {
                $orderId = $this->createdResources['cancelOrder'] ?? null;
                if (! $orderId) {
                    return false;
                }

                $response = $this->post("/api/v1/tenants/{$this->tenantId}/orders/{$orderId}/cancel");

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Pricing Tiers ====================

    private function testPricingTiers(): void
    {
        echo "--- Testing Pricing Tiers ---\n\n";

        $this->test('POST /tenants/{id}/pricing-tiers', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/pricing-tiers", [
                'name' => 'Test Tier ' . time(),
                'slug' => 'test-tier-' . time(),
                'description' => 'Test pricing tier',
                'priority' => 1,
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['pricingTier'] = $response['data']['data']['pricingTier']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/pricing-tiers', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/pricing-tiers");

            return $response['status'] === 200 && isset($response['data']['data']['pricing_tiers']);
        });

        if (isset($this->createdResources['pricingTier'])) {
            $tierId = $this->createdResources['pricingTier'];

            $this->test('GET /tenants/{id}/pricing-tiers/{pricing_tier}', function () use ($tierId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/pricing-tiers/{$tierId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/pricing-tiers/{pricing_tier}', function () use ($tierId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/pricing-tiers/{$tierId}", [
                    'name' => 'Updated Test Tier',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Pricing Rules ====================

    private function testPricingRules(): void
    {
        echo "--- Testing Pricing Rules ---\n\n";

        if (isset($this->createdResources['pricingTier'])) {
            $this->test('POST /tenants/{id}/pricing-rules', function () {
                $data = [
                    'pricing_tier_id' => $this->createdResources['pricingTier'],
                    'type' => 'percentage',
                    'operation' => 'subtract',
                    'value' => 10.00,
                    'active' => true,
                ];

                if (isset($this->createdResources['product'])) {
                    $data['product_id'] = $this->createdResources['product'];
                }

                $response = $this->post("/api/v1/tenants/{$this->tenantId}/pricing-rules", $data);

                if ($response['status'] === 201) {
                    $this->createdResources['pricingRule'] = $response['data']['data']['pricingRule']['id'] ?? null;

                    return true;
                }

                return false;
            });
        }

        $this->test('GET /tenants/{id}/pricing-rules', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/pricing-rules");

            return $response['status'] === 200 && isset($response['data']['data']['pricing_rules']);
        });

        if (isset($this->createdResources['pricingRule'])) {
            $ruleId = $this->createdResources['pricingRule'];

            $this->test('GET /tenants/{id}/pricing-rules/{pricing_rule}', function () use ($ruleId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/pricing-rules/{$ruleId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/pricing-rules/{pricing_rule}', function () use ($ruleId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/pricing-rules/{$ruleId}", [
                    'value' => 15.00,
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Roles ====================

    private function testRoles(): void
    {
        echo "--- Testing Roles ---\n\n";

        $this->test('POST /tenants/{id}/roles', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/roles", [
                'name' => 'Test Role ' . time(),
                'slug' => 'test-role-' . time(),
                'description' => 'Test role for API testing',
                'permissions' => ['products.view', 'orders.view'],
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['role'] = $response['data']['data']['role']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/roles', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/roles");

            return $response['status'] === 200 && isset($response['data']['data']['roles']);
        });

        if (isset($this->createdResources['role'])) {
            $roleId = $this->createdResources['role'];

            $this->test('GET /tenants/{id}/roles/{role}', function () use ($roleId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/roles/{$roleId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/roles/{role}', function () use ($roleId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/roles/{$roleId}", [
                    'name' => 'Updated Test Role',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Permissions ====================

    private function testPermissions(): void
    {
        echo "--- Testing Permissions ---\n\n";

        $this->test('POST /tenants/{id}/permissions', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/permissions", [
                'name' => 'Test Permission ' . time(),
                'slug' => 'test.permission-' . time(),
                'group' => 'testing',
                'description' => 'Test permission for API testing',
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['permission'] = $response['data']['data']['permission']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/permissions', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/permissions");

            return $response['status'] === 200 && isset($response['data']['data']['permissions']);
        });

        if (isset($this->createdResources['permission'])) {
            $permissionId = $this->createdResources['permission'];

            $this->test('GET /tenants/{id}/permissions/{permission}', function () use ($permissionId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/permissions/{$permissionId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/permissions/{permission}', function () use ($permissionId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/permissions/{$permissionId}", [
                    'name' => 'Updated Test Permission',
                ]);

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Reports ====================

    private function testReports(): void
    {
        echo "--- Testing Reports ---\n\n";

        $this->test('GET /tenants/{id}/dashboard', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/dashboard");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/sales/revenue', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/sales/revenue");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/sales/orders-by-period', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/sales/orders-by-period");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/sales/top-products', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/sales/top-products");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/inventory', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/inventory");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/inventory/stock-levels', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/inventory/stock-levels");

            return $response['status'] === 200;
        });

        $this->test('GET /tenants/{id}/reports/inventory/low-stock', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/reports/inventory/low-stock");

            return $response['status'] === 200;
        });

        echo "\n";
    }

    // ==================== Webhooks ====================

    private function testWebhooks(): void
    {
        echo "--- Testing Webhooks ---\n\n";

        $this->test('POST /tenants/{id}/webhooks', function () {
            $response = $this->post("/api/v1/tenants/{$this->tenantId}/webhooks", [
                'name' => 'Test Webhook ' . time(),
                'url' => 'https://webhook.site/test-' . time(),
                'events' => ['order.created', 'order.confirmed'],
                'active' => true,
            ]);

            if ($response['status'] === 201) {
                $this->createdResources['webhook'] = $response['data']['data']['id'] ?? null;

                return true;
            }

            return false;
        });

        $this->test('GET /tenants/{id}/webhooks', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/webhooks");

            return $response['status'] === 200 && isset($response['data']['data']);
        });

        if (isset($this->createdResources['webhook'])) {
            $webhookId = $this->createdResources['webhook'];

            $this->test('GET /tenants/{id}/webhooks/{webhook}', function () use ($webhookId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/webhooks/{$webhookId}");

                return $response['status'] === 200;
            });

            $this->test('PUT /tenants/{id}/webhooks/{webhook}', function () use ($webhookId) {
                $response = $this->put("/api/v1/tenants/{$this->tenantId}/webhooks/{$webhookId}", [
                    'name' => 'Updated Test Webhook',
                ]);

                return $response['status'] === 200;
            });

            $this->test('POST /tenants/{id}/webhooks/{webhook}/test', function () use ($webhookId) {
                $response = $this->post("/api/v1/tenants/{$this->tenantId}/webhooks/{$webhookId}/test");

                return in_array($response['status'], [200, 400]);
            });

            $this->test('GET /tenants/{id}/webhooks/{webhook}/attempts', function () use ($webhookId) {
                $response = $this->get("/api/v1/tenants/{$this->tenantId}/webhooks/{$webhookId}/attempts");

                return $response['status'] === 200;
            });
        }

        echo "\n";
    }

    // ==================== Audit Logs ====================

    private function testAuditLogs(): void
    {
        echo "--- Testing Audit Logs ---\n\n";

        $this->test('GET /tenants/{id}/audit-logs', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/audit-logs");

            return $response['status'] === 200 && isset($response['data']['data']);
        });

        $this->test('GET /tenants/{id}/audit-logs/summary', function () {
            $response = $this->get("/api/v1/tenants/{$this->tenantId}/audit-logs/summary");

            return $response['status'] === 200;
        });

        echo "\n";
    }

    // ==================== Helper Methods ====================

    private function test(string $name, callable $test): void
    {
        if ($this->verbose) {
            echo "  Testing: {$name}... ";
        }

        try {
            $result = $test();

            if ($this->verbose && property_exists($this, 'createdResources')) {
                echo '[DEBUG] createdResources count: ' . count($this->createdResources) . "\n";
            }

            if ($result) {
                echo "✓ {$name}\n";
                $this->testResults['passed'][] = $name;
            } else {
                echo "✗ {$name}\n";
                $this->testResults['failed'][] = $name;
            }
        } catch (\Exception $e) {
            echo "✗ {$name} - Exception: " . $e->getMessage() . "\n";
            $this->testResults['failed'][] = $name;
        }
    }

    private function get(string $url): array
    {
        return $this->request('GET', $url);
    }

    private function post(string $url, array $data = []): array
    {
        return $this->request('POST', $url, $data);
    }

    private function put(string $url, array $data = []): array
    {
        return $this->request('PUT', $url, $data);
    }

    private function delete(string $url): array
    {
        return $this->request('DELETE', $url);
    }

    private function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token && ! str_contains($url, '/auth/login')) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if (! empty($data) && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // curl_close() is deprecated in PHP 8.5+ but still safe to call
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        if ($error) {
            throw new \Exception("cURL Error: {$error}");
        }

        $body = json_decode($response, true) ?? [];

        if ($this->verbose) {
            echo "\n    Request: {$method} {$url}\n";
            if (! empty($data)) {
                echo '    Data: ' . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
            echo "    Status: {$httpCode}\n";
            echo '    Response: ' . json_encode($body, JSON_PRETTY_PRINT) . "\n";
        }

        return [
            'status' => $httpCode,
            'data' => $body,
        ];
    }

    private function cleanup(): void
    {
        echo "--- Cleaning Up Test Resources ---\n\n";

        // Delete in reverse order of creation to handle dependencies
        $deleteOrder = [
            'cancelOrder',  // Delete test cancel order first
            'order',
            'inventory',
            'product',
            'customer',
            'category',
            'store',
            'warehouse',
            'pricingRule',
            'pricingTier',
            'role',
            'permission',
            'webhook',
        ];

        foreach ($deleteOrder as $resource) {
            if (isset($this->createdResources[$resource])) {
                $id = $this->createdResources[$resource];

                // Map resource names to endpoint paths (handle irregular plurals)
                $endpointMap = [
                    'store' => 'stores',
                    'warehouse' => 'warehouses',
                    'category' => 'categories',
                    'product' => 'products',
                    'customer' => 'customers',
                    'inventory' => 'inventory',  // Same singular and plural
                    'order' => 'orders',
                    'pricingTier' => 'pricing-tiers',
                    'pricingRule' => 'pricing-rules',
                    'role' => 'roles',
                    'permission' => 'permissions',
                    'webhook' => 'webhooks',
                ];

                $endpoint = $endpointMap[$resource] ?? $resource . 's';

                try {
                    $response = $this->delete("/api/v1/tenants/{$this->tenantId}/{$endpoint}/{$id}");
                    if (in_array($response['status'], [200, 204, 404])) {
                        echo "✓ Deleted {$endpoint}/{$id}\n";
                    } else {
                        echo "⚠ Failed to delete {$endpoint}/{$id} (Status: {$response['status']})\n";
                    }
                } catch (\Exception $e) {
                    echo "⚠ Exception deleting {$endpoint}/{$id}: {$e->getMessage()}\n";
                }
            }
        }

        echo "\n";
    }

    private function printSummary(): void
    {
        echo "===========================================\n";
        echo "Test Summary\n";
        echo "===========================================\n";

        $passed = count($this->testResults['passed'] ?? []);
        $failed = count($this->testResults['failed'] ?? []);
        $total = $passed + $failed;

        echo "Total Tests: {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";

        if (! empty($this->testResults['failed'])) {
            echo "\nFailed Tests:\n";
            foreach ($this->testResults['failed'] as $test) {
                echo "  - {$test}\n";
            }
        }

        echo "===========================================\n";
    }
}

// ==================== CLI Entry Point ====================

if (php_sapi_name() === 'cli') {
    // Parse command line arguments
    $options = getopt('', [
        'base-url:',
        'tenant:',
        'email:',
        'password:',
        'endpoint:',
        'verbose',
        'help',
    ]);

    if (isset($options['help'])) {
        echo shell_exec('php ' . __FILE__ . ' --help 2>&1 | head -20');
        exit(0);
    }

    $baseUrl = $options['base-url'] ?? 'http://localhost:8000';
    $tenantId = isset($options['tenant']) ? (int) $options['tenant'] : 1;
    $email = $options['email'] ?? 'admin@demo.com';
    $password = $options['password'] ?? 'password';
    $verbose = isset($options['verbose']);
    $endpoint = $options['endpoint'] ?? null;

    $client = new RestClient($baseUrl, $tenantId, $email, $password);
    $client->setVerbose($verbose);

    if ($endpoint) {
        $client->runEndpointTest($endpoint);
    } else {
        $client->runAllTests();
    }
}
