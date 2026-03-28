# Testing Guide

## Overview

This project uses **separate databases** for development and testing to preserve your seeded data.

- **Main Database**: `database/database.sqlite` (development/production)
- **Test Database**: `database/testing.sqlite` (isolated test runs)

## Running Tests

### Recommended: Use Composer Scripts

```bash
# Run all tests (isolated from main database)
composer test:isolated

# Run only unit tests
composer test:unit

# Run only feature tests
composer test:feature

# Run tests with coverage report
composer test:coverage
```

### Alternative: Direct PHPUnit

```bash
# Using vendor/bin/phpunit (automatically uses phpunit.xml settings)
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Feature/TenantManagementTest.php

# Run tests matching a name pattern
vendor/bin/phpunit --filter="test_can_filter_tenants"
```

### Manual Environment Variables

```bash
# Set environment variables explicitly
DB_CONNECTION=sqlite DB_DATABASE=database/testing.sqlite php artisan test

# With filters
DB_CONNECTION=sqlite DB_DATABASE=database/testing.sqlite php artisan test --filter=TestName
```

## First-Time Setup

Before running tests for the first time, migrate the test database:

```bash
# Migrate test database
DB_CONNECTION=sqlite DB_DATABASE=database/testing.sqlite php artisan migrate:fresh --force
```

Or let the composer script handle it automatically:

```bash
composer test:isolated
```

## Seeding Data

### Main Database (Development)

```bash
# Seed main database
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=TenantSeeder
```

### Test Database

Tests use `RefreshDatabase` trait which automatically:
1. Runs migrations before each test
2. Wraps tests in transactions
3. Rolls back after each test

**No manual seeding needed for tests!**

## Verifying Database Isolation

Check that your main database is preserved after running tests:

```bash
# Seed main database
php artisan db:seed

# Check tenant count
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM tenants;"
# Output: 6 (or your seeded count)

# Run tests
composer test:isolated

# Check tenant count again (should be unchanged)
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM tenants;"
# Output: 6 (same as before)
```

## Test Database Files

```
database/
├── database.sqlite      # Main database (preserved)
└── testing.sqlite       # Test database (recreated on each test run)
```

Both files are ignored by `.gitignore`.

## Configuration Files

### `.env.testing`

Environment variables for testing:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=database/testing.sqlite
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

### `phpunit.xml`

PHPUnit configuration with test-specific settings:

```xml
<env name="DB_DATABASE" value="database/testing.sqlite"/>
```

## Troubleshooting

### Tests Using Wrong Database

If tests are affecting your main database:

1. Ensure `.env.testing` exists
2. Run with explicit environment variables:
   ```bash
   DB_CONNECTION=sqlite DB_DATABASE=database/testing.sqlite php artisan test
   ```

### Test Database Not Found

Create and migrate the test database:

```bash
touch database/testing.sqlite
DB_CONNECTION=sqlite DB_DATABASE=database/testing.sqlite php artisan migrate:fresh --force
```

### Config Cache Issues

Clear configuration cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## Best Practices

1. **Always use isolated tests**: Use `composer test:isolated` instead of `composer test`
2. **Don't seed test database manually**: Let `RefreshDatabase` handle it
3. **Test in isolation**: Each test should be independent
4. **Use factories**: Create test data with model factories
5. **Clean assertions**: Use provided helper methods like `$this->assertApiValidationErrors()`

## Example Test Workflow

```bash
# 1. Seed main database with demo data
php artisan db:seed

# 2. Verify data exists
sqlite3 database/database.sqlite "SELECT name FROM tenants;"

# 3. Run tests (main database preserved)
composer test:isolated

# 4. Verify data still exists
sqlite3 database/database.sqlite "SELECT name FROM tenants;"

# 5. Check test database (should be empty after tests)
sqlite3 database/testing.sqlite "SELECT COUNT(*) FROM tenants;"
# Output: 0
```

## Questions?

- See `phpunit.xml` for PHPUnit configuration
- Check `.env.testing` for test environment variables
- Review tests in `tests/Feature/` and `tests/Unit/` for examples
