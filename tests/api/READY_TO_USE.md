# REST Client - Ready to Use! ✅

## Quick Start

The REST client is **ready to use** with your POS WMS API!

### Test It Now

```bash
# Quick authentication test
php tests/api/RestClient.php --endpoint=authentication

# Test stores endpoint
php tests/api/RestClient.php --endpoint=stores

# Run all tests
php tests/api/RestClient.php
```

### Default Credentials

The REST client comes pre-configured with demo credentials:
- **Email:** `admin@demo.com`
- **Password:** `password`
- **Tenant ID:** `1`

These match the seeded demo users in your database.

## Test Results

### ✅ Working Endpoints (33/53 tests passing)

| Resource | Tests | Status |
|----------|-------|--------|
| Authentication | 2/2 | ✅ Fully Working |
| Stores | 2/4 | ⚠️ Partially Working |
| Warehouses | 2/4 | ⚠️ Partially Working |
| Categories | 2/4 | ⚠️ Partially Working |
| Products | 2/4 | ⚠️ Partially Working |
| Customers | 4/4 | ✅ Fully Working |
| Inventory | 5/5 | ✅ Fully Working |
| Orders | 6/7 | ⚠️ Partially Working |
| Dashboard/Reports | 8/8 | ✅ Fully Working |
| Pricing Tiers | 0/2 | ❌ Not Implemented |
| Pricing Rules | 0/1 | ❌ Not Implemented |
| Roles | 0/2 | ❌ Not Implemented |
| Permissions | 0/2 | ❌ Not Implemented |
| Webhooks | 0/2 | ❌ Not Implemented |
| Audit Logs | 0/2 | ❌ Not Implemented |

**Note:** "Partially Working" means CREATE and LIST work, but individual resource operations (GET/PUT by ID) may have issues with resource tracking.

### Known Issues

1. **Resource ID Tracking**: Some created resource IDs aren't being tracked properly for subsequent GET/PUT tests
2. **Missing API Endpoints**: Pricing, Roles, Permissions, Webhooks, and Audit Logs endpoints don't exist yet in the API

## Usage Examples

### 1. Quick Authentication Test
```bash
php tests/api/RestClient.php --endpoint=authentication
```

Expected output:
```
Testing endpoint: authentication

--- Testing Authentication ---

✓ POST /auth/login
✓ GET /auth/me
```

### 2. Test All Core Resources
```bash
php tests/api/RestClient.php --endpoint=stores
php tests/api/RestClient.php --endpoint=products
php tests/api/RestClient.php --endpoint=orders
```

### 3. Test Reports (All Working!)
```bash
php tests/api/RestClient.php --endpoint=reports
```

Expected output:
```
--- Testing Reports ---

✓ GET /tenants/{id}/dashboard
✓ GET /tenants/{id}/reports/sales/revenue
✓ GET /tenants/{id}/reports/sales/orders-by-period
✓ GET /tenants/{id}/reports/sales/top-products
✓ GET /tenants/{id}/reports/inventory
✓ GET /tenants/{id}/reports/inventory/stock-levels
✓ GET /tenants/{id}/reports/inventory/movements
✓ GET /tenants/{id}/reports/inventory/low-stock
```

### 4. Full Test Suite
```bash
php tests/api/RestClient.php
```

### 5. Verbose Mode for Debugging
```bash
php tests/api/RestClient.php --endpoint=products --verbose
```

### 6. Using the Bash Wrapper
```bash
# Quick test
./tests/api/api-test.sh --quick

# Test specific endpoint
./tests/api/api-test.sh --endpoint=inventory

# Full test with verbose output
./tests/api/api-test.sh --all --verbose
```

## Configuration

### Option 1: Command Line (Recommended)
```bash
php tests/api/RestClient.php \
  --base-url=http://localhost:8000 \
  --tenant=1 \
  --email=admin@demo.com \
  --password=password
```

### Option 2: Configuration File
```bash
cp tests/api/.rest-client-config.example.php tests/api/.rest-client-config.php
nano tests/api/.rest-client-config.php  # Edit with your settings
```

### Option 3: Environment Variables
```bash
export API_TEST_EMAIL=admin@demo.com
export API_TEST_PASSWORD=password
php tests/api/RestClient.php
```

## Available Test Users

| Email | Password | Tenant | Role |
|-------|----------|--------|------|
| admin@demo.com | password | 1 | user |
| manager@demo.com | password | 1 | user |
| staff@demo.com | password | 1 | user |

## Requirements

- ✅ PHP 8.3+
- ✅ cURL extension
- ✅ Laravel server running (`php artisan serve`)
- ✅ Database seeded with demo users

## Files Created

| File | Purpose |
|------|---------|
| `tests/api/RestClient.php` | Main REST client (1,098 lines) |
| `tests/api/README.md` | Complete documentation |
| `tests/api/QUICK_REFERENCE.md` | Quick reference card |
| `tests/api/RestClientExample.php` | Usage examples |
| `tests/api/api-test.sh` | Bash wrapper script |
| `tests/api/.rest-client-config.example.php` | Config template |
| `tests/api/setup.php` | Setup helper script |
| `tests/api/IMPLEMENTATION_SUMMARY.md` | Technical details |
| `tests/api/READY_TO_USE.md` | This file |

## Next Steps

### For Development

1. **Test your API changes**:
   ```bash
   php tests/api/RestClient.php --endpoint=your-resource
   ```

2. **Debug with verbose mode**:
   ```bash
   php tests/api/RestClient.php --endpoint=products --verbose
   ```

3. **Run before committing**:
   ```bash
   php tests/api/RestClient.php
   ```

### For Future API Development

When you implement new endpoints, the REST client will automatically test them:

1. **Pricing endpoints** → `--endpoint=pricingTiers`, `--endpoint=pricingRules`
2. **Roles endpoints** → `--endpoint=roles`
3. **Permissions endpoints** → `--endpoint=permissions`
4. **Webhooks endpoints** → `--endpoint=webhooks`
5. **Audit Logs endpoints** → `--endpoint=auditLogs`

## Troubleshooting

### "Unauthenticated" Errors
```bash
# Make sure you're using correct credentials
php tests/api/RestClient.php --email=admin@demo.com --password=password
```

### "Connection Refused" Errors
```bash
# Start the Laravel server
php artisan serve
```

### Specific Tests Failing
```bash
# Run with verbose to see details
php tests/api/RestClient.php --endpoint=products --verbose
```

## Success Criteria

The REST client is working correctly if you see:

```
✓ POST /auth/login
✓ GET /auth/me
```

When running the authentication test!

## Support

- **Documentation:** `tests/api/README.md`
- **Quick Reference:** `tests/api/QUICK_REFERENCE.md`
- **Examples:** `tests/api/RestClientExample.php`

---

**Status:** ✅ Ready to Use  
**Last Updated:** March 20, 2026  
**Version:** 1.0.0
