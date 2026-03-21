# API REST Client - Test Documentation

A comprehensive mini REST client for testing all POS WMS API endpoints documented in `docs/api/`.

## Overview

This REST client is a standalone PHP script that allows you to:
- Test all API endpoints automatically
- Verify endpoint responses
- Create, read, update, and delete test resources
- Clean up test data after execution

## Location

```
tests/api/RestClient.php
```

## Requirements

- PHP 8.3+
- cURL extension enabled
- Running instance of the POS WMS backend API

## Quick Start

### Prerequisites

1. **Database seeded** with test users
2. **Laravel server running**: `php artisan serve`

### Basic Usage

Run all tests with default settings:

```bash
php tests/api/RestClient.php
```

**Note:** The default credentials are `test@example.com` / `password`. If these don't work, use one of the demo users:

```bash
# Use demo admin user
php tests/api/RestClient.php --email=admin@demo.com --password=password

# Use demo manager user
php tests/api/RestClient.php --email=manager@demo.com --password=password

# Use demo staff user
php tests/api/RestClient.php --email=staff@demo.com --password=password
```

### Custom Configuration

Specify custom base URL, tenant, and credentials:

```bash
php tests/api/RestClient.php \
  --base-url=http://localhost:8000 \
  --tenant=1 \
  --email=admin@example.com \
  --password=your-password
```

### Verbose Mode

Enable detailed output for debugging:

```bash
php tests/api/RestClient.php --verbose
```

### Test Specific Endpoint

Test a specific resource endpoint:

```bash
php tests/api/RestClient.php --endpoint=stores
php tests/api/RestClient.php --endpoint=products
php tests/api/RestClient.php --endpoint=orders
```

## Command Line Options

| Option | Description | Default |
|--------|-------------|---------|
| `--base-url` | Base URL of the API | `http://localhost:8000` |
| `--tenant` | Tenant ID for tests | `1` |
| `--email` | User email for authentication | `test@example.com` |
| `--password` | User password | `password` |
| `--endpoint` | Specific endpoint to test | All endpoints |
| `--verbose` | Enable detailed output | Off |
| `--help` | Show help message | - |

## Endpoints Tested

The REST client tests all endpoints documented in the API documentation:

### Authentication
- ✓ POST `/api/v1/auth/login`
- ✓ GET `/api/v1/tenants/{id}/auth/me`

### Stores
- ✓ POST `/api/v1/tenants/{id}/stores`
- ✓ GET `/api/v1/tenants/{id}/stores`
- ✓ GET `/api/v1/tenants/{id}/stores/{id}`
- ✓ PUT `/api/v1/tenants/{id}/stores/{id}`

### Warehouses
- ✓ POST `/api/v1/tenants/{id}/warehouses`
- ✓ GET `/api/v1/tenants/{id}/warehouses`
- ✓ GET `/api/v1/tenants/{id}/warehouses/{id}`
- ✓ PUT `/api/v1/tenants/{id}/warehouses/{id}`

### Categories
- ✓ POST `/api/v1/tenants/{id}/categories`
- ✓ GET `/api/v1/tenants/{id}/categories`
- ✓ GET `/api/v1/tenants/{id}/categories/{id}`
- ✓ PUT `/api/v1/tenants/{id}/categories/{id}`

### Products
- ✓ POST `/api/v1/tenants/{id}/products`
- ✓ GET `/api/v1/tenants/{id}/products`
- ✓ GET `/api/v1/tenants/{id}/products/{id}`
- ✓ PUT `/api/v1/tenants/{id}/products/{id}`

### Customers
- ✓ POST `/api/v1/tenants/{id}/customers`
- ✓ GET `/api/v1/tenants/{id}/customers`
- ✓ GET `/api/v1/tenants/{id}/customers/{id}`
- ✓ PUT `/api/v1/tenants/{id}/customers/{id}`

### Inventory
- ✓ POST `/api/v1/tenants/{id}/inventory`
- ✓ GET `/api/v1/tenants/{id}/inventory`
- ✓ GET `/api/v1/tenants/{id}/inventory/{id}`
- ✓ PUT `/api/v1/tenants/{id}/inventory/{id}`
- ✓ POST `/api/v1/tenants/{id}/inventory/transfer`

### Orders
- ✓ POST `/api/v1/tenants/{id}/orders`
- ✓ GET `/api/v1/tenants/{id}/orders`
- ✓ GET `/api/v1/tenants/{id}/orders/{id}`
- ✓ PUT `/api/v1/tenants/{id}/orders/{id}`
- ✓ POST `/api/v1/tenants/{id}/orders/{id}/confirm`
- ✓ POST `/api/v1/tenants/{id}/orders/{id}/fulfill`
- ✓ POST `/api/v1/tenants/{id}/orders/{id}/cancel`

### Pricing Tiers
- ✓ POST `/api/v1/tenants/{id}/pricing-tiers`
- ✓ GET `/api/v1/tenants/{id}/pricing-tiers`
- ✓ GET `/api/v1/tenants/{id}/pricing-tiers/{id}`
- ✓ PUT `/api/v1/tenants/{id}/pricing-tiers/{id}`

### Pricing Rules
- ✓ POST `/api/v1/tenants/{id}/pricing-rules`
- ✓ GET `/api/v1/tenants/{id}/pricing-rules`
- ✓ GET `/api/v1/tenants/{id}/pricing-rules/{id}`
- ✓ PUT `/api/v1/tenants/{id}/pricing-rules/{id}`

### Roles
- ✓ POST `/api/v1/tenants/{id}/roles`
- ✓ GET `/api/v1/tenants/{id}/roles`
- ✓ GET `/api/v1/tenants/{id}/roles/{id}`
- ✓ PUT `/api/v1/tenants/{id}/roles/{id}`

### Permissions
- ✓ POST `/api/v1/tenants/{id}/permissions`
- ✓ GET `/api/v1/tenants/{id}/permissions`
- ✓ GET `/api/v1/tenants/{id}/permissions/{id}`
- ✓ PUT `/api/v1/tenants/{id}/permissions/{id}`

### Reports & Dashboard
- ✓ GET `/api/v1/tenants/{id}/dashboard`
- ✓ GET `/api/v1/tenants/{id}/reports/sales/revenue`
- ✓ GET `/api/v1/tenants/{id}/reports/sales/orders-by-period`
- ✓ GET `/api/v1/tenants/{id}/reports/sales/top-products`
- ✓ GET `/api/v1/tenants/{id}/reports/inventory`
- ✓ GET `/api/v1/tenants/{id}/reports/inventory/stock-levels`
- ✓ GET `/api/v1/tenants/{id}/reports/inventory/movements`
- ✓ GET `/api/v1/tenants/{id}/reports/inventory/low-stock`

### Webhooks
- ✓ POST `/api/v1/tenants/{id}/webhooks`
- ✓ GET `/api/v1/tenants/{id}/webhooks`
- ✓ GET `/api/v1/tenants/{id}/webhooks/{id}`
- ✓ PUT `/api/v1/tenants/{id}/webhooks/{id}`
- ✓ POST `/api/v1/tenants/{id}/webhooks/{id}/test`
- ✓ GET `/api/v1/tenants/{id}/webhooks/{id}/attempts`

### Audit Logs
- ✓ GET `/api/v1/tenants/{id}/audit-logs`
- ✓ GET `/api/v1/tenants/{id}/audit-logs/summary`

## Example Output

```
===========================================
POS WMS API - Mini REST Client Tests
===========================================
Base URL: http://localhost:8000
Tenant ID: 1
===========================================

--- Testing Authentication ---

✓ POST /auth/login
✓ GET /auth/me

--- Testing Stores ---

✓ POST /tenants/{id}/stores
✓ GET /tenants/{id}/stores
✓ GET /tenants/{id}/stores/{id}
✓ PUT /tenants/{id}/stores/{id}

--- Testing Warehouses ---

✓ POST /tenants/{id}/warehouses
✓ GET /tenants/{id}/warehouses
✓ GET /tenants/{id}/warehouses/{id}
✓ PUT /tenants/{id}/warehouses/{id}

...

--- Cleaning Up Test Resources ---

✓ Deleted stores/1
✓ Deleted warehouses/1
✓ Deleted products/1
✓ Deleted categories/1

===========================================
Test Summary
===========================================
Total Tests: 65
Passed: 63
Failed: 2
===========================================
```

## How It Works

1. **Authentication**: The client first authenticates using the provided credentials and stores the Bearer token.

2. **Resource Creation**: Tests create resources in dependency order:
   - Stores & Warehouses
   - Categories
   - Products (may reference category)
   - Customers
   - Inventory (references product & warehouse)
   - Orders (references product, store, customer)
   - Pricing Tiers & Rules
   - Roles & Permissions
   - Webhooks

3. **CRUD Operations**: For each resource type, the client tests:
   - CREATE (POST)
   - READ (GET - list and single)
   - UPDATE (PUT)
   - DELETE (cleanup phase)

4. **Cleanup**: After all tests, created resources are deleted in reverse order to handle dependencies.

## Test Data

The client creates test resources with unique names/timestamps to avoid conflicts:

```php
'name' => 'Test Store ' . time(),
'code' => 'TEST-' . rand(1000, 9999),
```

## Resource Tracking

Created resources are tracked in the `$createdResources` array:

```php
[
    'store' => 1,
    'warehouse' => 1,
    'category' => 1,
    'product' => 1,
    'customer' => 1,
    'inventory' => 1,
    'order' => 1,
    'pricingTier' => 1,
    'pricingRule' => 1,
    'role' => 1,
    'permission' => 1,
    'webhook' => 1,
]
```

## Error Handling

The client handles errors gracefully:
- Failed tests are marked with ✗
- Exceptions are caught and reported
- Cleanup continues even if some tests fail
- A summary is provided at the end

## Verbose Mode

Use `--verbose` to see detailed request/response information:

```bash
php tests/api/RestClient.php --verbose
```

Example verbose output:
```
  Testing: POST /tenants/{id}/stores...
    Request: POST /api/v1/tenants/1/stores
    Data: {
        "name": "Test Store 1710943200",
        "code": "TEST-5678",
        ...
    }
    Status: 201
    Response: {
        "success": true,
        "data": {
            "store": {...}
        }
    }
✓ POST /tenants/{id}/stores
```

## Customization

### Adding New Tests

To add tests for new endpoints:

1. Add a new test method:
```php
private function testNewEndpoint(): void
{
    echo "--- Testing New Endpoint ---\n\n";

    $this->test('POST /tenants/{id}/new-endpoint', function () {
        $response = $this->post("/api/v1/tenants/{$this->tenantId}/new-endpoint", [
            'field' => 'value',
        ]);

        if ($response['status'] === 201) {
            $this->createdResources['newResource'] = $response['data']['data']['id'] ?? null;
            return true;
        }

        return false;
    });

    echo "\n";
}
```

2. Call it from `runAllTests()`:
```php
public function runAllTests(): void
{
    // ... existing tests ...
    $this->testNewEndpoint();
    // ... cleanup & summary ...
}
```

### Modifying Test Data

Edit the data arrays in each test method to customize test data:

```php
$this->test('POST /tenants/{id}/products', function () {
    $response = $this->post("/api/v1/tenants/{$this->tenantId}/products", [
        'name' => 'Custom Product Name',
        'sku' => 'CUSTOM-SKU',
        'price' => 149.99,
        // ... customize fields ...
    ]);
    // ...
});
```

## Troubleshooting

### Authentication Fails

**Problem**: `❌ Authentication failed`

**Solutions**:
1. Verify credentials are correct
2. Check that the user exists in the database
3. Ensure the API is running
4. Check `.env` for correct Sanctum configuration

### Connection Refused

**Problem**: `cURL Error: Failed to connect`

**Solutions**:
1. Start the Laravel server: `php artisan serve`
2. Verify the base URL is correct
3. Check firewall settings

### Tests Fail Due to Dependencies

**Problem**: Resource creation fails because referenced resources don't exist

**Solutions**:
1. Run all tests (not individual endpoints) to ensure dependencies are created
2. Check that previous resource creation tests passed
3. Verify database is clean before running tests

### Cleanup Fails

**Problem**: Resources not deleted after tests

**Solutions**:
1. Check foreign key constraints in database
2. Manually delete test resources if needed
3. Run database seeder to reset state

## Best Practices

1. **Run in Development**: Only run on development/staging environments, never production
2. **Clean Database**: Use a clean database or seed before running tests
3. **Review Verbose Output**: Use `--verbose` to understand failures
4. **Check Summary**: Always review the test summary at the end
5. **Manual Cleanup**: If cleanup fails, manually remove test resources

## Integration with Test Suite

This REST client complements the PHPUnit test suite:

- **PHPUnit Tests**: Automated, CI/CD integration, detailed assertions
- **REST Client**: Manual testing, API exploration, quick verification

Use the REST client for:
- Quick API verification during development
- Manual endpoint testing
- API documentation validation
- Integration testing with real server

## Related Documentation

- [API Overview](../../docs/api/01-overview.md)
- [API Design](../../API_DESIGN.md)
- [Full API Documentation](../../docs/api/README.md)

## Support

For issues or questions, refer to the main API documentation or contact the development team.

---

**Last Updated:** March 20, 2026
**Version:** 1.0.0
