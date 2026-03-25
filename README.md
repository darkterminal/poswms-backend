# POS WMS Backend - Multi-Store & Warehouse Management System

[![Tests](https://github.com/your-org/poswms-backend/actions/workflows/tests.yml/badge.svg)](https://github.com/your-org/poswms-backend/actions/workflows/tests.yml)
[![Code Quality](https://github.com/your-org/poswms-backend/actions/workflows/code-quality.yml/badge.svg)](https://github.com/your-org/poswms-backend/actions/workflows/code-quality.yml)
[![PHP Version](https://img.shields.io/badge/php-8.3-blue.svg)](https://www.php.net/releases/8.3/)
[![Laravel Version](https://img.shields.io/badge/laravel-13.x-red.svg)](https://laravel.com/docs/13.x)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**A comprehensive SaaS platform for managing multiple retail stores and warehouses with inventory tracking, order management, multi-level pricing, and advanced reporting.**

---

## Features

### Core Features
- **Multi-Tenant Architecture** - Secure tenant isolation with scoped data
- **Super Admin Dashboard** - Centralized tenant and system management
- **Store & Warehouse Management** - Manage multiple retail locations and storage facilities
- **Inventory Tracking** - Real-time stock levels with FIFO/LIFO layering, batch tracking, and low-stock alerts
- **Order Management** - Complete order lifecycle (confirm, fulfill, cancel) with fulfillment tracking
- **Multi-Level Pricing** - Flexible pricing tiers, rules, and product price levels
- **Role-Based Access Control** - Granular permissions with 5 default roles and 18 permissions
- **Customer Management** - Customer profiles with optional pricing tier assignment
- **Product Catalog** - Products with categories, SKU/barcode tracking, and attributes

### Advanced Features
- **Audit Logging** - Comprehensive tracking of all sensitive operations with user attribution
- **Webhooks** - Event-driven integrations with external systems (retry logic, delivery tracking)
- **Export Functionality** - CSV/PDF exports for inventory and sales reports
- **Inventory Transfers** - Transfer stock between warehouses and stores
- **Stock Movements** - Complete history of all inventory changes with reason tracking
- **Sales Reporting** - Revenue tracking, order analytics, and top products analysis
- **Dashboard Metrics** - Unified dashboard with key business metrics

### Technical Features
- **RESTful API** - Versioned API (`/api/v1/`) with comprehensive documentation
- **Rate Limiting** - Tiered rate limiting (auth, api, api-admin, api-exports, api-webhook-test)
- **Data Encryption** - Sensitive data encryption with version tracking
- **Queue System** - Background job processing for time-consuming operations
- **Event System** - Laravel events for webhook triggers and audit logging

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13.x |
| PHP Version | 8.3 |
| Database | SQLite (dev), PostgreSQL/MySQL (prod) |
| Authentication | Laravel Sanctum (Bearer tokens) |
| Testing | PHPUnit 12.x |
| Code Style | Laravel Pint |
| Static Analysis | PHPStan |
| CI/CD | GitHub Actions |

---

## Quick Start

### Prerequisites

- PHP 8.3 or higher
- Composer
- SQLite (for development) or PostgreSQL/MySQL (for production)

### Installation

```bash
# Clone the repository
git clone https://github.com/your-org/poswms-backend.git
cd poswms-backend

# Install dependencies
composer install

# Copy environment file
cp .env.development .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed demo data (optional)
php artisan db:seed

# Start development server
php artisan serve
```

Visit `http://localhost:8000` to access the application.

### Development Mode

For active development with auto-reload:

```bash
# Start all development services (server, queue, logs, vite)
composer run dev
```

---

## Documentation

- **[API Design](API_DESIGN.md)** - API architecture and conventions
- **[Development Roadmap](docs/DEVELOPMENT_ROADMAP.md)** - Feature roadmap and timeline
- **[Progress Tracker](docs/PROGRESS_TRACKER.md)** - Current development progress
- **[Tracking Guide](docs/TRACKING_GUIDE.md)** - Development tracking procedures
- **[OpenAPI Spec](swagger/openapi.yaml)** - API specification
- **[Swagger UI](http://localhost:8000/docs/api)** - Interactive API documentation

---

## Development

### Running Tests

```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/ExampleTest.php

# Run tests matching a pattern
php artisan test --compact --filter=TestName
```

### Code Formatting

```bash
# Format code with Laravel Pint
vendor/bin/pint --format agent

# Check formatting without fixing
vendor/bin/pint --test
```

### Static Analysis

```bash
# Run PHPStan
vendor/bin/phpstan analyse --memory-limit=1G
```

### Development Sessions

Track development progress with session management:

```bash
# Start a new development session
composer session:start

# Check current progress
composer session

# End session and update tracking
composer session:end

# View detailed progress report
composer progress
```

### Git Workflow

```bash
# Create feature branch
git checkout develop
git checkout -b feature/feature-name

# Commit changes (include task ID from DEVELOPMENT_ROADMAP.md)
git add .
git commit -m "feat: add new feature [Phase X.X]"

# Push and create PR
git push origin feature/feature-name
```

---

## CI/CD

The project uses GitHub Actions for continuous integration and deployment:

- **Tests** - Automated testing on every push and PR
- **Code Quality** - Pint formatting and PHPStan analysis
- **Deploy to Staging** - Automatic deployment on merge to `develop`
- **Deploy to Production** - Automatic deployment on merge to `main`

See [CI/CD Pipeline Documentation](docs/CI_CD_PIPELINE.md) for setup and usage.

---

## API Endpoints

### Public Authentication
```
POST   /api/v1/auth/login                          # User login
POST   /api/v1/admin/auth/login                    # Super admin login
```

### Super Admin Routes (Bearer token required)
```
# Auth
POST   /api/v1/admin/auth/logout
GET    /api/v1/admin/auth/me

# Tenant Management
GET    /api/v1/admin/tenants
POST   /api/v1/admin/tenants
GET    /api/v1/admin/tenants/{tenant}
PUT    /api/v1/admin/tenants/{tenant}
DELETE /api/v1/admin/tenants/{tenant}
POST   /api/v1/admin/tenants/{tenant}/activate
POST   /api/v1/admin/tenants/{tenant}/suspend
GET    /api/v1/admin/tenants/{tenant}/stats

# Tenant Subscription Management
POST   /api/v1/admin/tenants/{tenant}/trial
POST   /api/v1/admin/tenants/{tenant}/trial/extend
POST   /api/v1/admin/tenants/{tenant}/subscription
POST   /api/v1/admin/tenants/{tenant}/subscription/extend
POST   /api/v1/admin/tenants/{tenant}/subscription/cancel
POST   /api/v1/admin/tenants/{tenant}/convert-to-paid

# System Dashboard
GET    /api/v1/admin/dashboard
GET    /api/v1/admin/dashboard/revenue
GET    /api/v1/admin/dashboard/usage
GET    /api/v1/admin/dashboard/alerts

# User Management
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{user}
POST   /api/v1/admin/users/{user}/impersonate
POST   /api/v1/admin/users/stop-impersonating
GET    /api/v1/admin/users/{user}/impersonation-sessions
POST   /api/v1/admin/users/{user}/revoke-impersonation

# Global Audit Logs
GET    /api/v1/admin/audit-logs
GET    /api/v1/admin/audit-logs/summary
GET    /api/v1/admin/audit-logs/by-user/{userId}

# System Configuration
GET    /api/v1/admin/settings
PUT    /api/v1/admin/settings
POST   /api/v1/admin/settings/clear-cache
GET    /api/v1/admin/settings/health
POST   /api/v1/admin/settings/run-command
```

### Tenant-Scoped Routes (Bearer token + tenant_id required)
```
# Auth
POST   /api/v1/tenants/{tenant_id}/auth/logout
POST   /api/v1/tenants/{tenant_id}/auth/refresh
GET    /api/v1/tenants/{tenant_id}/auth/me

# Roles & Permissions (Admin only)
GET    /api/v1/tenants/{tenant_id}/roles
POST   /api/v1/tenants/{tenant_id}/roles
GET    /api/v1/tenants/{tenant_id}/roles/{id}
PUT    /api/v1/tenants/{tenant_id}/roles/{id}
DELETE /api/v1/tenants/{tenant_id}/roles/{id}
POST   /api/v1/tenants/{tenant_id}/users/{userId}/assign-role
DELETE /api/v1/tenants/{tenant_id}/users/{userId}/remove-role/{roleId}
GET    /api/v1/tenants/{tenant_id}/permissions
POST   /api/v1/tenants/{tenant_id}/permissions
GET    /api/v1/tenants/{tenant_id}/permissions/{id}
PUT    /api/v1/tenants/{tenant_id}/permissions/{id}
DELETE /api/v1/tenants/{tenant_id}/permissions/{id}

# Pricing (Admin only)
GET    /api/v1/tenants/{tenant_id}/pricing-tiers
POST   /api/v1/tenants/{tenant_id}/pricing-tiers
GET    /api/v1/tenants/{tenant_id}/pricing-tiers/{id}
PUT    /api/v1/tenants/{tenant_id}/pricing-tiers/{id}
DELETE /api/v1/tenants/{tenant_id}/pricing-tiers/{id}
GET    /api/v1/tenants/{tenant_id}/pricing-rules
POST   /api/v1/tenants/{tenant_id}/pricing-rules
GET    /api/v1/tenants/{tenant_id}/pricing-rules/{id}
PUT    /api/v1/tenants/{tenant_id}/pricing-rules/{id}
DELETE /api/v1/tenants/{tenant_id}/pricing-rules/{id}

# Audit Logs (Admin only)
GET    /api/v1/tenants/{tenant_id}/audit-logs
GET    /api/v1/tenants/{tenant_id}/audit-logs/{id}
GET    /api/v1/tenants/{tenant_id}/audit-logs/summary
GET    /api/v1/tenants/{tenant_id}/audit-logs/by-user/{userId}

# Webhooks (Admin only)
GET    /api/v1/tenants/{tenant_id}/webhooks
POST   /api/v1/tenants/{tenant_id}/webhooks
GET    /api/v1/tenants/{tenant_id}/webhooks/{id}
PUT    /api/v1/tenants/{tenant_id}/webhooks/{id}
DELETE /api/v1/tenants/{tenant_id}/webhooks/{id}
POST   /api/v1/tenants/{tenant_id}/webhooks/{webhook}/test
GET    /api/v1/tenants/{tenant_id}/webhooks/{webhook}/attempts
POST   /api/v1/tenants/{tenant_id}/webhooks/{webhook}/retry

# Core Entities
GET    /api/v1/tenants/{tenant_id}/stores
POST   /api/v1/tenants/{tenant_id}/stores
GET    /api/v1/tenants/{tenant_id}/stores/{storeId}
PUT    /api/v1/tenants/{tenant_id}/stores/{storeId}
DELETE /api/v1/tenants/{tenant_id}/stores/{storeId}

GET    /api/v1/tenants/{tenant_id}/warehouses
POST   /api/v1/tenants/{tenant_id}/warehouses
GET    /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}
PUT    /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}
DELETE /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}

GET    /api/v1/tenants/{tenant_id}/categories
POST   /api/v1/tenants/{tenant_id}/categories
GET    /api/v1/tenants/{tenant_id}/categories/{categoryId}
PUT    /api/v1/tenants/{tenant_id}/categories/{categoryId}
DELETE /api/v1/tenants/{tenant_id}/categories/{categoryId}

GET    /api/v1/tenants/{tenant_id}/products
POST   /api/v1/tenants/{tenant_id}/products
GET    /api/v1/tenants/{tenant_id}/products/{productId}
PUT    /api/v1/tenants/{tenant_id}/products/{productId}
DELETE /api/v1/tenants/{tenant_id}/products/{productId}

GET    /api/v1/tenants/{tenant_id}/customers
POST   /api/v1/tenants/{tenant_id}/customers
GET    /api/v1/tenants/{tenant_id}/customers/{customerId}
PUT    /api/v1/tenants/{tenant_id}/customers/{customerId}
DELETE /api/v1/tenants/{tenant_id}/customers/{customerId}

GET    /api/v1/tenants/{tenant_id}/inventory
POST   /api/v1/tenants/{tenant_id}/inventory
GET    /api/v1/tenants/{tenant_id}/inventory/{inventoryId}
PUT    /api/v1/tenants/{tenant_id}/inventory/{inventoryId}
DELETE /api/v1/tenants/{tenant_id}/inventory/{inventoryId}

GET    /api/v1/tenants/{tenant_id}/orders
POST   /api/v1/tenants/{tenant_id}/orders
GET    /api/v1/tenants/{tenant_id}/orders/{orderId}
PUT    /api/v1/tenants/{tenant_id}/orders/{orderId}
DELETE /api/v1/tenants/{tenant_id}/orders/{orderId}

# Inventory Actions
POST   /api/v1/tenants/{tenant_id}/inventory/transfer
GET    /api/v1/tenants/{tenant_id}/inventory/product/{productId}/transferable

# Order Actions
POST   /api/v1/tenants/{tenant_id}/orders/{orderId}/confirm
POST   /api/v1/tenants/{tenant_id}/orders/{orderId}/fulfill
POST   /api/v1/tenants/{tenant_id}/orders/{orderId}/cancel

# Inventory Reports
GET    /api/v1/tenants/{tenant_id}/reports/inventory/low-stock
GET    /api/v1/tenants/{tenant_id}/reports/inventory
GET    /api/v1/tenants/{tenant_id}/reports/inventory/stock-levels
GET    /api/v1/tenants/{tenant_id}/reports/inventory/movements

# Inventory Exports (Admin only, rate-limited)
GET    /api/v1/tenants/{tenant_id}/reports/inventory/export/stock-levels
GET    /api/v1/tenants/{tenant_id}/reports/inventory/export/movements
GET    /api/v1/tenants/{tenant_id}/reports/inventory/export/low-stock

# Sales Reports
GET    /api/v1/tenants/{tenant_id}/reports/sales/revenue
GET    /api/v1/tenants/{tenant_id}/reports/sales/orders-by-period
GET    /api/v1/tenants/{tenant_id}/reports/sales/top-products
GET    /api/v1/tenants/{tenant_id}/reports/sales/dashboard

# Sales Exports (Admin only, rate-limited)
GET    /api/v1/tenants/{tenant_id}/reports/sales/export/revenue
GET    /api/v1/tenants/{tenant_id}/reports/sales/export/orders-by-period
GET    /api/v1/tenants/{tenant_id}/reports/sales/export/top-products

# Dashboard
GET    /api/v1/tenants/{tenant_id}/dashboard

# Price Calculation
POST   /api/v1/tenants/{tenant_id}/prices/calculate
POST   /api/v1/tenants/{tenant_id}/prices/calculate-cart
```

### API Documentation

The project includes interactive API documentation via Swagger UI:

- **Swagger UI**: `http://localhost:8000/docs/api`
- **OpenAPI JSON**: `http://localhost:8000/api/v1/docs/openapi.json`

Features:
- Interactive exploration of all API endpoints
- Request/response schema documentation
- Try-it-out functionality for testing endpoints
- Authentication support for testing protected routes

---

## Project Structure

```
poswms-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # Super admin controllers
│   │   │   ├── Auth/             # Authentication controllers
│   │   │   └── Concerns/         # Controller traits
│   │   ├── Middleware/           # Custom middleware
│   │   └── Requests/             # Form request validators
│   ├── Models/
│   │   ├── Concerns/             # Model traits
│   │   └── Scopes/               # Eloquent scopes
│   ├── Services/                 # Business logic services
│   ├── Jobs/                     # Queue jobs
│   ├── Observers/                # Model observers
│   └── Providers/                # Service providers
├── bootstrap/
├── config/
├── database/
│   ├── factories/                # Model factories
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── docs/                         # Documentation
├── public/
├── resources/
├── routes/
├── storage/
├── swagger/                      # OpenAPI specification
├── tests/
│   ├── Feature/                  # Feature tests
│   └── Unit/                     # Unit tests
└── .github/
    └── workflows/                # GitHub Actions workflows
```

---

## Database Schema

### Core Tables
- `tenants` - Multi-tenant business entities
- `users` - System users with role assignments
- `roles` / `permissions` - RBAC system
- `stores` / `warehouses` - Location management
- `categories` / `products` / `customers` - Core business entities
- `inventory` / `inventory_batches` / `inventory_layers` - Stock tracking
- `stock_movements` - Inventory change history
- `orders` / `order_items` - Order management
- `pricing_tiers` / `pricing_rules` / `product_price_levels` - Pricing system
- `audit_logs` - Activity tracking
- `webhooks` / `webhook_delivery_attempts` - Event integrations

### Reference Tables
- `countries` / `currencies` - Global reference data

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature [Phase X.X]'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please read our [Contributing Guide](CONTRIBUTING.md) and [Development Roadmap](docs/DEVELOPMENT_ROADMAP.md) for details.

### Development Checklist

Before submitting PRs:
- [ ] Run tests: `php artisan test --compact --filter=RelatedTest`
- [ ] Format code: `vendor/bin/pint --format agent`
- [ ] Run static analysis: `vendor/bin/phpstan analyse`
- [ ] Update session tracking: `composer session:end`
- [ ] Include task ID in commit message

---

## Security

If you discover a security vulnerability, please contact the development team immediately. All security vulnerabilities will be promptly addressed.

Security features:
- Tenant data isolation via middleware
- Rate limiting on all API endpoints
- Audit logging for sensitive operations
- Data encryption for sensitive fields
- Role-based access control

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Support

For questions or issues:
- Check the [documentation](docs/)
- Review existing [GitHub Issues](https://github.com/your-org/poswms-backend/issues)
- Contact the development team

---

**Repository:** poswms-backend  
**Maintained by:** Development Team  
**Last Updated:** March 25, 2026
