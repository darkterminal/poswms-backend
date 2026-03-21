# REST Client - Quick Reference Card

## Basic Commands

```bash
# Run all tests
php tests/api/RestClient.php

# Show help
php tests/api/RestClient.php --help
```

## Common Options

```bash
# Custom base URL
php tests/api/RestClient.php --base-url=http://localhost:8000

# Custom tenant ID
php tests/api/RestClient.php --tenant=2

# Custom credentials
php tests/api/RestClient.php --email=admin@example.com --password=secret

# Test specific endpoint
php tests/api/RestClient.php --endpoint=products

# Verbose output
php tests/api/RestClient.php --verbose

# Combine options
php tests/api/RestClient.php \
  --base-url=http://localhost:8000 \
  --tenant=1 \
  --email=admin@example.com \
  --endpoint=orders \
  --verbose
```

## Available Endpoints

| Endpoint | Description |
|----------|-------------|
| `authentication` | Login and auth tests |
| `stores` | Store management |
| `warehouses` | Warehouse management |
| `categories` | Product categories |
| `products` | Product catalog |
| `customers` | Customer management |
| `inventory` | Stock management |
| `orders` | Order processing |
| `pricingTiers` | Pricing tiers |
| `pricingRules` | Pricing rules |
| `roles` | User roles |
| `permissions` | Permissions |
| `reports` | Reports & dashboard |
| `webhooks` | Webhook management |
| `auditLogs` | Audit trail |

## Test Output Symbols

- ✓ = Test passed
- ✗ = Test failed
- ⚠ = Warning (cleanup issue)

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

===========================================
Test Summary
===========================================
Total Tests: 65
Passed: 63
Failed: 2
===========================================
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Auth failed | Check credentials, verify user exists |
| Connection refused | Start server: `php artisan serve` |
| Tests fail | Run full test suite, check dependencies |
| Cleanup fails | Manually delete test resources |

## Tips

1. Run on development environment only
2. Use `--verbose` for debugging
3. Test specific endpoints during development
4. Review summary at the end
5. Check database state before running

## Full Documentation

See [tests/api/README.md](../../tests/api/README.md) for complete documentation.

---

**Quick Start:** `php tests/api/RestClient.php --verbose`
