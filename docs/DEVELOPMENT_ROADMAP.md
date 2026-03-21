# Development Roadmap - Multi-Store & Warehouse Management System (MSWMS)

**Document Version:** 1.0  
**Last Updated:** March 19, 2026  
**Project:** POSWMS Backend  
**Framework:** Laravel 13.x (PHP 8.3)

---

## Overview

This roadmap outlines the complete development plan for the Multi-Store & Warehouse Management System (MSWMS) SaaS platform. The system enables businesses to manage multiple stores and warehouses with inventory tracking, order management, and optional multi-level pricing.

---

## Implementation Priority Order

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 8 (tests) → Phase 5 → Phase 6 → Phase 7 → Phase 8 (deployment)
```

---

## Phase 1: Foundation & Authentication 🔴 CRITICAL

**Goal:** Establish core infrastructure and security

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 1.1 | Install Laravel Sanctum | API token authentication | 1h |
| 1.2 | Create Tenant Model & Migration | Multi-tenant architecture foundation | 2h |
| 1.3 | Update Users Table | Add `tenant_id`, `role`, `store_id`, `warehouse_id` columns | 1h |
| 1.4 | Create Tenant Middleware | Automatic tenant scoping for all queries | 2h |
| 1.5 | Build Authentication Endpoints | `/api/v1/auth/login`, `/logout`, `/refresh`, `/me` | 3h |
| 1.6 | Create Role & Permission System | RBAC middleware and policies | 3h |
| 1.7 | Write Auth Tests | Authentication & authorization feature tests | 3h |

**Total Estimated Effort:** ~15 hours

### Deliverables
- [ ] `tenants` table migration
- [ ] Updated `users` table migration
- [ ] `Tenant` and updated `User` models
- [ ] `EnsureTenantIsScoped` middleware
- [ ] Authentication controllers
- [ ] API routes for auth
- [ ] Feature tests for authentication

---

## Phase 2: Core Entities 🔴 HIGH

**Goal:** Build foundational business models

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 2.1 | Stores Module | Model, migration, factory, seeder, CRUD API endpoints | 4h |
| 2.2 | Warehouses Module | Model, migration, factory, CRUD API endpoints | 4h |
| 2.3 | Categories Module | Product categorization with parent-child relationships | 2h |
| 2.4 | Products Module | Model with SKU, pricing, relationships | 4h |
| 2.5 | Customers Module | Customer management with optional pricing tier | 3h |
| 2.6 | Shared Reference Tables | Countries, currencies, product_attributes | 2h |
| 2.7 | API Resources | JSON resource classes for all entities | 3h |
| 2.8 | Form Requests | Validation classes for all endpoints | 3h |

**Total Estimated Effort:** ~25 hours

### Deliverables
- [ ] Migrations: `stores`, `warehouses`, `categories`, `products`, `customers`, `countries`, `currencies`
- [ ] Models with relationships
- [ ] Factories and seeders
- [ ] API Resource classes
- [ ] Form Request validators
- [ ] CRUD controllers
- [ ] Feature tests

---

## Phase 3: Inventory Management 🔴 HIGH

**Goal:** Core business functionality for stock tracking

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 3.1 | Inventory Model & Migration | Track stock per warehouse/store | 2h |
| 3.2 | Inventory CRUD Endpoints | Get, update stock levels | 3h |
| 3.3 | Stock Transfer System | Transfer between warehouses/stores | 4h |
| 3.4 | Low Stock Alerts | Automatic notifications when below threshold | 2h |
| 3.5 | Inventory Reporting | Stock levels, movement history | 3h |
| 3.6 | Inventory Jobs | Queue jobs for stock adjustments | 2h |

**Total Estimated Effort:** ~16 hours

### Deliverables
- [ ] `inventory` and `stock_movements` tables
- [ ] `Inventory` model with scopes
- [ ] Inventory management controllers
- [ ] Stock transfer job classes
- [ ] Low stock notification system
- [ ] Inventory report endpoints
- [ ] Feature tests

---

## Phase 4: Order Management 🔴 HIGH

**Goal:** Revenue-generating workflows

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 4.1 | Orders Model & Migration | Order header with status tracking | 2h |
| 4.2 | Order Items Migration | Line items for orders | 1h |
| 4.3 | Order CRUD Endpoints | Create, read, update, cancel | 4h |
| 4.4 | Order Fulfillment | `/fulfill` endpoint to process orders | 3h |
| 4.5 | Order Number Generation | Sequential numbering per tenant | 2h |
| 4.6 | Inventory Deduction | Auto-decrement stock on order fulfillment | 3h |
| 4.7 | Order Tests | Full workflow testing | 4h |

**Total Estimated Effort:** ~19 hours

### Deliverables
- [ ] `orders` and `order_items` tables
- [ ] `Order` and `OrderItem` models
- [ ] Order controllers
- [ ] Order fulfillment service
- [ ] Order number generator
- [ ] Inventory deduction logic
- [ ] Comprehensive feature tests

---

## Phase 5: Multi-Level Pricing 🟡 MEDIUM

**Goal:** Optional advanced pricing feature

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 5.1 | Pricing Tiers Module | Bronze/Silver/Gold customer tiers | 2h |
| 5.2 | Pricing Rules Engine | Flexible rule-based pricing | 4h |
| 5.3 | Price Calculation Service | Apply rules to calculate final price | 4h |
| 5.4 | Pricing API Endpoints | CRUD for tiers and rules | 3h |
| 5.5 | Price Calculation Endpoint | `/calculate-price` with all rules applied | 3h |

**Total Estimated Effort:** ~16 hours

### Deliverables
- [ ] `pricing_tiers` and `pricing_rules` tables
- [ ] `PricingTier` and `PricingRule` models
- [ ] Price calculation service class
- [ ] Pricing controllers
- [ ] Price calculation API endpoint
- [ ] Feature tests

---

## Phase 6: Reporting & Analytics 🟡 MEDIUM

**Goal:** Business intelligence capabilities

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 6.1 | Sales Reports | Revenue, orders by period, top products | 4h |
| 6.2 | Inventory Reports | Stock levels, valuation, movement | 3h |
| 6.3 | Low Stock Report | Items below minimum threshold | 2h |
| 6.4 | Dashboard Metrics | KPIs for tenant admin | 3h |

**Total Estimated Effort:** ~12 hours

### Deliverables
- [ ] Report controller classes
- [ ] Report query builders
- [ ] Report API endpoints
- [ ] Dashboard metrics endpoint
- [ ] Feature tests

---

## Phase 7: Advanced Features 🟢 LOW

**Goal:** Enhancements and polish

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 7.1 | API Rate Limiting | Per-tenant/user limits | 2h |
| 7.2 | Audit Logging | Track sensitive operations | 3h |
| 7.3 | Export Functionality | CSV/PDF exports for reports | 4h |
| 7.4 | Webhooks | Notify external systems of events | 4h |
| 7.5 | API Documentation | OpenAPI/Swagger specs | 4h |

**Total Estimated Effort:** ~17 hours

### Deliverables
- [ ] Rate limiter configuration
- [ ] Audit log model and listeners
- [ ] Export service classes
- [ ] Webhook system
- [ ] OpenAPI documentation

---

## Phase 8: Production Readiness 🔴 CRITICAL (before launch)

**Goal:** Deployment preparation

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 8.1 | Comprehensive Test Suite | 80%+ code coverage | 20h |
| 8.2 | Database Seeders | Demo data for development | 4h |
| 8.3 | Environment Configuration | Dev/staging/production configs | 2h |
| 8.4 | CI/CD Pipeline | Automated testing & deployment | 8h |
| 8.5 | Performance Optimization | Query optimization, caching | 8h |
| 8.6 | Security Hardening | Penetration testing, security review | 8h |

**Total Estimated Effort:** ~50 hours

### Deliverables
- [x] Complete test suite
- [x] Database seeders
- [x] Environment configs
- [x] CI/CD pipeline configuration
- [x] Performance optimization report
- [x] Security audit report

---

## Phase 9: Super Admin Module 🔴 CRITICAL (SaaS Management)

**Goal:** Enable SaaS owners to manage all tenants and system-wide operations

| # | Task | Description | Estimated Effort |
|---|------|-------------|------------------|
| 9.1 | Super Admin Authentication | Separate auth guard and middleware | 5h |
| 9.2 | Tenant Management API | CRUD operations for tenants | 10h |
| 9.3 | System Dashboard | Platform-wide metrics and analytics | 7.5h |
| 9.4 | Advanced Features | User search, impersonation, subscriptions | 8h |
| 9.5 | Documentation & Polish | OpenAPI specs, tests, code review | 5h |

**Total Estimated Effort:** ~35.5 hours

### Deliverables
- [ ] Super Admin authentication system
- [ ] Tenant CRUD API endpoints
- [ ] System dashboard with metrics
- [ ] User impersonation feature
- [ ] Global audit logs
- [ ] API documentation
- [ ] Comprehensive test suite

### API Endpoints

#### Super Admin Authentication
```
POST   /api/v1/admin/auth/login              # Super admin login
POST   /api/v1/admin/auth/logout             # Logout
GET    /api/v1/admin/auth/me                 # Get current admin info
```

#### Tenant Management
```
GET    /api/v1/admin/tenants                 # List all tenants (paginated)
POST   /api/v1/admin/tenants                 # Create new tenant
GET    /api/v1/admin/tenants/{id}            # View tenant details
PUT    /api/v1/admin/tenants/{id}            # Update tenant
DELETE /api/v1/admin/tenants/{id}            # Soft delete tenant
POST   /api/v1/admin/tenants/{id}/activate   # Activate tenant
POST   /api/v1/admin/tenants/{id}/suspend    # Suspend tenant
GET    /api/v1/admin/tenants/{id}/stats      # Tenant statistics
```

#### System Dashboard
```
GET    /api/v1/admin/dashboard               # System overview metrics
GET    /api/v1/admin/dashboard/revenue       # Revenue-specific metrics
GET    /api/v1/admin/dashboard/usage         # Usage statistics
GET    /api/v1/admin/dashboard/alerts        # System alerts
```

#### User Management
```
GET    /api/v1/admin/users                   # Search all users
GET    /api/v1/admin/users/{id}              # View user details
POST   /api/v1/admin/users/{id}/impersonate  # Generate impersonation token
```

---

## Database Schema Summary

### Core Tables (Phase 1-2)
| Table | Description |
|-------|-------------|
| `tenants` | Multi-tenant business entities |
| `users` | System users with roles |
| `stores` | Retail locations |
| `warehouses` | Storage facilities |
| `categories` | Product categories |
| `products` | Sellable items |
| `customers` | Customer records |

### Inventory Tables (Phase 3)
| Table | Description |
|-------|-------------|
| `inventory` | Stock levels per location |
| `stock_movements` | Inventory transaction history |

### Order Tables (Phase 4)
| Table | Description |
|-------|-------------|
| `orders` | Order headers |
| `order_items` | Order line items |

### Pricing Tables (Phase 5)
| Table | Description |
|-------|-------------|
| `pricing_tiers` | Customer pricing tiers |
| `pricing_rules` | Pricing adjustment rules |

### Shared Reference Tables
| Table | Description |
|-------|-------------|
| `countries` | Country list |
| `currencies` | Currency list |
| `product_attributes` | Extensible product properties |

---

## API Endpoint Structure

All endpoints follow RESTful conventions with the prefix `/api/v1/tenants/{tenant_id}/`

### Authentication
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
```

### Resources
```
GET    /stores
POST   /stores
GET    /stores/{id}
PUT    /stores/{id}
DELETE /stores/{id}
```

*(Similar pattern for warehouses, products, customers, inventory, orders)*

### Reports
```
GET    /reports/sales
GET    /reports/inventory
GET    /reports/low-stock
```

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13.x |
| PHP Version | 8.3 |
| Database | SQLite (dev), PostgreSQL/MySQL (prod) |
| Authentication | Laravel Sanctum |
| Testing | PHPUnit 12.x |
| Code Style | Laravel Pint |
| API Documentation | OpenAPI/Swagger |

---

## Development Guidelines

1. **Follow Laravel Conventions** - Use Artisan commands, Eloquent ORM, Form Requests
2. **Test-Driven Development** - Write tests before or alongside features
3. **Tenant Isolation** - All queries must be scoped to tenant
4. **API Versioning** - All endpoints prefixed with `/api/v1/`
5. **Documentation** - Keep API docs and code in sync

---

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Tenant data leakage | Critical | Middleware enforcement, comprehensive tests |
| Performance degradation | High | Query optimization, indexing, caching |
| Scope creep | Medium | Strict adherence to phased approach |
| Integration complexity | Medium | Well-defined API contracts |

---

## Success Metrics

- [ ] All Phase 1-4 features implemented and tested
- [ ] 80%+ code coverage
- [ ] API response time < 200ms for 95th percentile
- [ ] Zero critical security vulnerabilities
- [ ] Successful deployment to staging environment

---

## Appendix: Quick Start Commands

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development
composer run dev

# Run tests
composer run test
```

---

**Document Maintainer:** Development Team  
**Review Cycle:** Bi-weekly during active development
