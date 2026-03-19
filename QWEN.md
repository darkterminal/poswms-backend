# POS WMS Backend - Project Context

## Project Overview

**POS WMS Backend** is a Laravel 13 application serving as the backend for a **Multi-Store & Warehouse Management System (SaaS)**. The system enables businesses to manage multiple retail locations and warehouses with features including:

- Multi-tenant architecture (single database, tenant-scoped data)
- Store and warehouse management
- Inventory tracking with low-stock alerts
- Order processing and fulfillment
- Multi-level pricing (optional tiered pricing)
- Role-based access control

### Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13.x |
| PHP | 8.3 |
| Database | SQLite (dev), PostgreSQL/MySQL (production) |
| Testing | PHPUnit 12.x |
| Code Formatter | Laravel Pint |
| AI Tools | Laravel Boost v2 |

## Building and Running

### Initial Setup

```bash
# Install dependencies and set up the project
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Development Server

```bash
# Start Laravel development server
php artisan serve
```

### Running Tests

```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/ExampleTest.php

# Run tests matching a name pattern
php artisan test --compact --filter=TestName
```

### Code Formatting

```bash
# Format PHP files with Laravel Pint
vendor/bin/pint --format agent
```

### Useful Artisan Commands

```bash
# List available commands
php artisan list

# Check command options
php artisan [command] --help

# Common development commands
php artisan route:list          # List all routes
php artisan config:show [key]   # View configuration
php artisan tinker              # Interactive PHP shell
php artisan make:model          # Create a model
php artisan make:controller     # Create a controller
php artisan make:test           # Create a test
php artisan migrate             # Run migrations
```

## Development Workflow

### Progress Tracking (Required Before Each Session)

**Always check and update progress tracking before and after development work:**

```bash
# Using Composer (recommended)
composer session:start    # Before starting work
composer session          # Check current progress
composer session:end      # After completing work

# Or using direct scripts
./scripts/session-check.sh start
./scripts/session-check.sh
./scripts/session-check.sh end
```

**Pre-Work Checklist:**
- [ ] Run `composer session:start`
- [ ] Review task in `docs/DEVELOPMENT_ROADMAP.md`
- [ ] Update `docs/progress.json`: task status → `in_progress`
- [ ] Create/update session log in `docs/session-logs/`

**Post-Work Checklist:**
- [ ] Run code formatter: `vendor/bin/pint --format agent`
- [ ] Run tests: `php artisan test --compact --filter=YourTest`
- [ ] Update `docs/progress.json`: completed tasks → `completed`
- [ ] Update `docs/PROGRESS_TRACKER.md` with time and status
- [ ] Complete session log in `docs/session-logs/`
- [ ] Commit with task ID in message: `feat: description [Phase X.X]`

**Tracking Files:**
- `docs/DEVELOPMENT_ROADMAP.md` - Complete task specifications
- `docs/PROGRESS_TRACKER.md` - Visual progress dashboard
- `docs/progress.json` - Machine-readable progress data
- `docs/session-logs/` - Individual session documentation
- `docs/TRACKING_GUIDE.md` - Detailed usage instructions

**Useful Commands:**
```bash
composer session:start    # Start new development session
composer session          # Check progress
composer session:end      # End session
composer progress         # Detailed progress report
```

---

## Development Conventions

### PHP Code Style

- **Curly braces**: Always use for control structures, even single-line bodies
- **Type declarations**: Always use explicit return types and parameter type hints
- **Constructor property promotion**: Use PHP 8+ syntax
- **PHPDoc**: Prefer PHPDoc blocks over inline comments
- **Naming**: Use descriptive names (e.g., `isRegisteredForDiscounts`, not `discount()`)

### Laravel Best Practices

- **Validation**: Use Form Request classes for validation (not inline in controllers)
- **Database**: Use Eloquent models and relationships; avoid `DB::` queries
- **N+1 Prevention**: Use eager loading (`with()`) for relationships
- **Configuration**: Use `config()` helper; never use `env()` outside config files
- **URL Generation**: Use named routes with `route()` function
- **API Design**: Use Eloquent API Resources with versioning (`/api/v1/`)

### Testing Practices

- **Test Framework**: PHPUnit (not Pest)
- **Test Location**: Most tests should be feature tests in `tests/Feature/`
- **Factories**: Use model factories for test data; check for custom states
- **Coverage**: Tests should cover happy paths, failure paths, and edge cases
- **Run Tests**: Run affected tests after each change

### File Creation

Use Artisan make commands for consistency:

```bash
php artisan make:model ModelName
php artisan make:controller ControllerName
php artisan make:request RequestName
php artisan make:test TestName
php artisan make:migration create_table_name
```

Pass `--no-interaction` to all Artisan commands for non-interactive execution.

## Project Structure

```
poswms-backend/
├── app/
│   ├── Http/Controllers/    # Request handlers
│   ├── Models/              # Eloquent models
│   └── Providers/           # Service providers
├── bootstrap/               # Application bootstrap files
├── config/                  # Configuration files
├── database/
│   ├── factories/           # Model factories for testing
│   ├── migrations/          # Database migrations
│   └── seeders/             # Database seeders
├── public/                  # Public assets
├── resources/               # Lang files (API-only, no views)
├── routes/
│   ├── web.php             # Web routes
│   └── console.php         # Console routes
├── storage/                 # Logs, compiled views, cache
├── tests/
│   ├── Feature/            # Feature/integration tests
│   └── Unit/               # Unit tests
└── .env.example            # Environment template
```

## API Design

The application follows RESTful API design principles as documented in `API_DESIGN.md`:

- **Base URL**: `/api/v1/`
- **Authentication**: Laravel Sanctum (Bearer tokens)
- **Multi-tenant**: All tenant resources scoped by `tenant_id`
- **Response Format**: JSON with `success`, `data`, `message`, `meta` keys

### Core Entities (Planned)

- `Tenant` - Business/subscription holder
- `User` - System users with roles
- `Store` - Retail locations
- `Warehouse` - Storage facilities
- `Product` - Items for sale
- `Inventory` - Stock levels
- `Order` / `OrderItem` - Customer orders
- `Customer` - Customer records
- `PricingTier` / `PricingRule` - Optional tiered pricing

## Debugging

- **Application Logs**: Check `storage/logs/laravel.log`
- **Database**: Use `database-query` and `database-schema` tools
- **Tinker**: `php artisan tinker --execute "code here"`
- **Routes**: `php artisan route:list`
- **Config**: `php artisan config:show [key]` or read config files directly
- **Environment**: Read `.env` file directly

## Key Documentation

- `README.md` - Laravel framework overview
- `API_DESIGN.md` - Comprehensive API design specification
- `AGENTS.md` - Laravel Boost guidelines and project conventions
- `docs/DEVELOPMENT_ROADMAP.md` - Complete development roadmap with phases
- `docs/PROGRESS_TRACKER.md` - Main progress tracking dashboard
- `docs/TRACKING_GUIDE.md` - Guide for using the progress tracking system
- `docs/progress.json` - Machine-readable progress data
- `docs/session-logs/` - Individual development session logs
