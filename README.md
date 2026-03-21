# MSWMS Backend - Multi-Store & Warehouse Management System

[![Tests](https://github.com/your-org/mswms-backend/actions/workflows/tests.yml/badge.svg)](https://github.com/your-org/mswms-backend/actions/workflows/tests.yml)
[![Code Quality](https://github.com/your-org/mswms-backend/actions/workflows/code-quality.yml/badge.svg)](https://github.com/your-org/mswms-backend/actions/workflows/code-quality.yml)
[![PHP Version](https://img.shields.io/badge/php-8.3-blue.svg)](https://www.php.net/releases/8.3/)
[![Laravel Version](https://img.shields.io/badge/laravel-13.x-red.svg)](https://laravel.com/docs/13.x)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**A comprehensive SaaS platform for managing multiple retail stores and warehouses with inventory tracking, order management, and multi-level pricing.**

---

## Features

- **Multi-Tenant Architecture** - Secure tenant isolation with scoped data
- **Store & Warehouse Management** - Manage multiple locations
- **Inventory Tracking** - Real-time stock levels with low-stock alerts
- **Order Management** - Complete order lifecycle with fulfillment
- **Multi-Level Pricing** - Flexible pricing tiers and rules
- **Role-Based Access Control** - Granular permissions and roles
- **Audit Logging** - Track all sensitive operations
- **Webhooks** - Event-driven integrations with external systems
- **Export Functionality** - CSV/PDF exports for reports
- **RESTful API** - Versioned API with comprehensive documentation

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13.x |
| PHP Version | 8.3 |
| Database | SQLite (dev), PostgreSQL/MySQL (prod) |
| Authentication | Laravel Sanctum |
| Testing | PHPUnit 12.x |
| Code Style | Laravel Pint |
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
git clone https://github.com/your-org/mswms-backend.git
cd mswms-backend

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

---

## Documentation

- **[API Design](API_DESIGN.md)** - API architecture and conventions
- **[Environment Configuration](docs/ENVIRONMENT_CONFIGURATION.md)** - Environment setup guide
- **[CI/CD Pipeline](docs/CI_CD_PIPELINE.md)** - Continuous integration and deployment
- **[Development Roadmap](docs/DEVELOPMENT_ROADMAP.md)** - Feature roadmap and timeline
- **[Progress Tracker](docs/PROGRESS_TRACKER.md)** - Current development progress
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
vendor/bin/pint

# Check formatting without fixing
vendor/bin/pint --test
```

### Static Analysis

```bash
# Run PHPStan
vendor/bin/phpstan analyse --memory-limit=1G
```

### Git Workflow

```bash
# Create feature branch
git checkout develop
git checkout -b feature/feature-name

# Commit changes
git add .
git commit -m "feat: add new feature"

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

### Authentication
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
```

### Resources
```
GET    /api/v1/tenants/{tenant_id}/stores
POST   /api/v1/tenants/{tenant_id}/stores
GET    /api/v1/tenants/{tenant_id}/stores/{id}
PUT    /api/v1/tenants/{tenant_id}/stores/{id}
DELETE /api/v1/tenants/{tenant_id}/stores/{id}
```

*(Similar patterns for warehouses, products, customers, inventory, orders)*

### Reports
```
GET    /api/v1/tenants/{tenant_id}/reports/sales
GET    /api/v1/tenants/{tenant_id}/reports/inventory
GET    /api/v1/tenants/{tenant_id}/reports/low-stock
GET    /api/v1/tenants/{tenant_id}/dashboard
```

See [API Design](API_DESIGN.md) and [OpenAPI Spec](swagger/openapi.yaml) for complete documentation.

### API Documentation

The project includes interactive API documentation via Swagger UI. After starting the development server, you can access it at:

- **Swagger UI**: `http://localhost:8000/docs/api`
- **OpenAPI JSON**: `http://localhost:8000/api/v1/docs/openapi.json`

The Swagger UI provides:
- Interactive exploration of all API endpoints
- Request/response schema documentation
- Try-it-out functionality for testing endpoints
- Authentication support for testing protected routes

---

## Project Structure

```
mswms-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Request handlers
│   │   ├── Middleware/      # Custom middleware
│   │   └── Requests/        # Form requests
│   ├── Models/              # Eloquent models
│   ├── Services/            # Business logic services
│   └── Jobs/                # Queue jobs
├── bootstrap/
├── config/
├── database/
│   ├── factories/           # Model factories
│   ├── migrations/          # Database migrations
│   └── seeders/             # Database seeders
├── docs/                    # Documentation
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
│   ├── Feature/             # Feature tests
│   └── Unit/                # Unit tests
└── .github/
    └── workflows/           # GitHub Actions workflows
```

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please read our [Contributing Guide](CONTRIBUTING.md) for details.

---

## Security

If you discover a security vulnerability, please contact the development team immediately. All security vulnerabilities will be promptly addressed.

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Support

For questions or issues:
- Check the [documentation](docs/)
- Review existing [GitHub Issues](https://github.com/your-org/mswms-backend/issues)
- Contact the development team

---

**Maintained by:** Development Team  
**Last Updated:** March 21, 2026
