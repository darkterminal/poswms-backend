# API Design Document: Multi Stores & Warehouses Management System

## Table of Contents
1. [Introduction](#introduction)
2. [System Overview](#system-overview)
3. [Architecture](#architecture)
4. [Core Entities and Data Models](#core-entities-and-data-models)
5. [Database Schema](#database-schema)
6. [API Design Principles](#api-design-principles)
7. [Authentication and Authorization](#authentication-and-authorization)
8. [API Endpoints](#api-endpoints)
9. [Multi-Level Pricing Feature](#multi-level-pricing-feature)
10. [Error Handling](#error-handling)
11. [Security Considerations](#security-considerations)
12. [Testing Strategy](#testing-strategy)
13. [Deployment and Scaling](#deployment-and-scaling)
14. [Conclusion](#conclusion)

## Introduction

### Project Name
Multi Stores & Warehouses Management System (MSWMS)

### Purpose
This document outlines the comprehensive API design for a Software-as-a-Service (SaaS) platform that enables businesses to manage multiple stores and warehouses efficiently. The system supports inventory tracking, order management, and multi-level pricing strategies across different business units.

### Key Features
- **Multi-Tenant SaaS**: Single database architecture supporting multiple independent businesses
- **Store Management**: Create and manage multiple retail locations
- **Warehouse Management**: Centralized inventory control across warehouses
- **Inventory Tracking**: Real-time stock management with low-stock alerts
- **Order Processing**: Complete order lifecycle management
- **Multi-Level Pricing**: Optional tiered pricing system (optional feature)
- **User Management**: Role-based access control
- **Reporting**: Basic analytics and reporting capabilities

### Assumptions
- Single database architecture for cost efficiency
- No AI/ML features required
- RESTful API design
- JSON responses
- Laravel framework backend
- Stateless API (no sessions)

## System Overview

### Business Context
The system serves retail businesses that operate multiple stores and maintain centralized warehouses. Each tenant (business) can have multiple stores and warehouses, with inventory flowing between them.

### User Roles
1. **Super Admin**: System-wide administration
2. **Tenant Admin**: Business owner/manager
3. **Store Manager**: Individual store management
4. **Warehouse Manager**: Inventory and warehouse operations
5. **Sales Associate**: Basic sales operations

### Key Workflows
1. **Inventory Management**: Products added to warehouses, distributed to stores
2. **Order Processing**: Orders placed at stores, fulfilled from warehouse inventory
3. **Stock Transfers**: Movement of goods between warehouses and stores
4. **Pricing Management**: Dynamic pricing based on customer tiers (optional)

## Architecture

### Technology Stack
- **Backend**: Laravel 13.x (PHP 8.3)
- **Database**: SQLite (development), PostgreSQL/MySQL (production)
- **API**: RESTful JSON API
- **Authentication**: Laravel Sanctum (token-based)
- **Documentation**: OpenAPI/Swagger

### Multi-Tenant Architecture
- **Single Database**: All tenants share the same database schema
- **Tenant Isolation**: `tenant_id` column on all tables
- **Data Scoping**: All queries filtered by tenant context
- **Shared Resources**: Common tables (countries, currencies) without tenant_id

### API Architecture
- **Versioning**: `/api/v1/` prefix
- **Rate Limiting**: Applied per tenant/API key
- **Caching**: Redis for frequently accessed data
- **Background Jobs**: Laravel Queues for heavy operations

## Core Entities and Data Models

### Tenant
Represents a business/client using the system.
```json
{
  "id": "uuid",
  "name": "Business Name",
  "domain": "business.example.com",
  "subscription_plan": "premium",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### User
System users with role-based access.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "name": "John Doe",
  "email": "john@business.com",
  "role": "store_manager",
  "store_id": "uuid", // optional
  "warehouse_id": "uuid", // optional
  "is_active": true,
  "created_at": "2024-01-01T00:00:00Z"
}
```

### Store
Physical or virtual retail locations.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "name": "Downtown Store",
  "address": "123 Main St",
  "phone": "+1234567890",
  "manager_id": "uuid",
  "is_active": true,
  "created_at": "2024-01-01T00:00:00Z"
}
```

### Warehouse
Storage facilities for inventory.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "name": "Central Warehouse",
  "address": "456 Industrial Ave",
  "manager_id": "uuid",
  "capacity": 10000,
  "is_active": true,
  "created_at": "2024-01-01T00:00:00Z"
}
```

### Product
Items sold in stores.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "sku": "PROD-001",
  "name": "Wireless Headphones",
  "description": "High-quality wireless headphones",
  "category_id": "uuid",
  "base_price": 99.99,
  "cost_price": 50.00,
  "is_active": true,
  "created_at": "2024-01-01T00:00:00Z"
}
```

### Inventory
Stock levels across warehouses and stores.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "product_id": "uuid",
  "warehouse_id": "uuid", // null for store inventory
  "store_id": "uuid", // null for warehouse inventory
  "quantity": 150,
  "min_stock_level": 10,
  "max_stock_level": 500,
  "last_updated": "2024-01-01T00:00:00Z"
}
```

### Order
Customer purchase orders.
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "store_id": "uuid",
  "customer_id": "uuid", // optional
  "order_number": "ORD-2024-001",
  "status": "pending",
  "total_amount": 199.98,
  "tax_amount": 19.99,
  "discount_amount": 0.00,
  "created_at": "2024-01-01T00:00:00Z"
}
```

### OrderItem
Individual items within an order.
```json
{
  "id": "uuid",
  "order_id": "uuid",
  "product_id": "uuid",
  "quantity": 2,
  "unit_price": 99.99,
  "total_price": 199.98,
  "discount": 0.00
}
```

## Database Schema

### Core Tables
- `tenants` (id, name, domain, subscription_plan, timestamps)
- `users` (id, tenant_id, name, email, role, store_id, warehouse_id, is_active, timestamps)
- `stores` (id, tenant_id, name, address, phone, manager_id, is_active, timestamps)
- `warehouses` (id, tenant_id, name, address, manager_id, capacity, is_active, timestamps)
- `categories` (id, tenant_id, name, parent_id, is_active, timestamps)
- `products` (id, tenant_id, sku, name, description, category_id, base_price, cost_price, is_active, timestamps)
- `inventory` (id, tenant_id, product_id, warehouse_id, store_id, quantity, min_stock_level, max_stock_level, last_updated, timestamps)
- `customers` (id, tenant_id, name, email, phone, address, pricing_tier_id, timestamps)
- `orders` (id, tenant_id, store_id, customer_id, order_number, status, total_amount, tax_amount, discount_amount, created_at, updated_at)
- `order_items` (id, order_id, product_id, quantity, unit_price, total_price, discount)

### Optional Multi-Level Pricing Tables
- `pricing_tiers` (id, tenant_id, name, description, is_active, timestamps)
- `pricing_rules` (id, tenant_id, pricing_tier_id, product_id, rule_type, adjustment_type, adjustment_value, conditions, is_active, timestamps)

### Shared Tables (No tenant_id)
- `countries` (id, name, code)
- `currencies` (id, name, code, symbol)
- `product_attributes` (id, name, type) - for extensible product properties

## API Design Principles

### RESTful Design
- Use appropriate HTTP methods (GET, POST, PUT, DELETE)
- Resource-based URLs
- Consistent response formats
- Proper status codes

### URL Structure
```
/api/v1/tenants/{tenant_id}/stores
/api/v1/tenants/{tenant_id}/warehouses
/api/v1/tenants/{tenant_id}/products
/api/v1/tenants/{tenant_id}/inventory
/api/v1/tenants/{tenant_id}/orders
```

### Response Format
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful",
  "meta": {
    "pagination": { ... },
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

### Error Response Format
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": { ... }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

## Authentication and Authorization

### Authentication
- **Method**: Laravel Sanctum (Bearer tokens)
- **Token Generation**: Via login endpoint
- **Token Storage**: Client-side (localStorage, secure cookies)
- **Expiration**: 24 hours (configurable)

### Authorization
- **Role-Based Access Control (RBAC)**
- **Permissions**: CRUD operations per resource
- **Context-Aware**: Store managers can only access their store's data
- **Middleware**: Automatic tenant scoping and permission checks

### API Key Authentication (Optional)
- For third-party integrations
- Rate limited per key
- Scoped permissions

## API Endpoints

### Authentication Endpoints
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
```

### Store Management
```
GET    /api/v1/tenants/{tenant_id}/stores
POST   /api/v1/tenants/{tenant_id}/stores
GET    /api/v1/tenants/{tenant_id}/stores/{store_id}
PUT    /api/v1/tenants/{tenant_id}/stores/{store_id}
DELETE /api/v1/tenants/{tenant_id}/stores/{store_id}
```

### Warehouse Management
```
GET    /api/v1/tenants/{tenant_id}/warehouses
POST   /api/v1/tenants/{tenant_id}/warehouses
GET    /api/v1/tenants/{tenant_id}/warehouses/{warehouse_id}
PUT    /api/v1/tenants/{tenant_id}/warehouses/{warehouse_id}
DELETE /api/v1/tenants/{tenant_id}/warehouses/{warehouse_id}
```

### Product Management
```
GET    /api/v1/tenants/{tenant_id}/products
POST   /api/v1/tenants/{tenant_id}/products
GET    /api/v1/tenants/{tenant_id}/products/{product_id}
PUT    /api/v1/tenants/{tenant_id}/products/{product_id}
DELETE /api/v1/tenants/{tenant_id}/products/{product_id}
```

### Inventory Management
```
GET    /api/v1/tenants/{tenant_id}/inventory
GET    /api/v1/tenants/{tenant_id}/warehouses/{warehouse_id}/inventory
GET    /api/v1/tenants/{tenant_id}/stores/{store_id}/inventory
PUT    /api/v1/tenants/{tenant_id}/inventory/{inventory_id}
POST   /api/v1/tenants/{tenant_id}/inventory/transfer
```

### Order Management
```
GET    /api/v1/tenants/{tenant_id}/orders
POST   /api/v1/tenants/{tenant_id}/orders
GET    /api/v1/tenants/{tenant_id}/orders/{order_id}
PUT    /api/v1/tenants/{tenant_id}/orders/{order_id}
DELETE /api/v1/tenants/{tenant_id}/orders/{order_id}
POST   /api/v1/tenants/{tenant_id}/orders/{order_id}/fulfill
```

### Reporting Endpoints
```
GET    /api/v1/tenants/{tenant_id}/reports/sales
GET    /api/v1/tenants/{tenant_id}/reports/inventory
GET    /api/v1/tenants/{tenant_id}/reports/low-stock
```

## Multi-Level Pricing Feature

### Overview
Optional feature allowing businesses to set different prices based on customer tiers, bulk quantities, or other conditions.

### Pricing Tiers
- Bronze, Silver, Gold customer tiers
- Wholesale vs Retail pricing
- Seasonal pricing

### Pricing Rules
- **Rule Types**: Fixed price, percentage discount, markup
- **Conditions**: Customer tier, quantity thresholds, date ranges
- **Adjustment Types**: Absolute amount, percentage

### API Endpoints for Pricing
```
GET    /api/v1/tenants/{tenant_id}/pricing-tiers
POST   /api/v1/tenants/{tenant_id}/pricing-tiers
GET    /api/v1/tenants/{tenant_id}/pricing-rules
POST   /api/v1/tenants/{tenant_id}/pricing-rules
PUT    /api/v1/tenants/{tenant_id}/pricing-rules/{rule_id}
DELETE /api/v1/tenants/{tenant_id}/pricing-rules/{rule_id}
POST   /api/v1/tenants/{tenant_id}/products/{product_id}/calculate-price
```

### Price Calculation Logic
1. Start with base product price
2. Apply customer-specific pricing rules
3. Apply quantity-based discounts
4. Apply promotional pricing
5. Calculate final price with tax

## Error Handling

### HTTP Status Codes
- `200 OK`: Successful request
- `201 Created`: Resource created
- `400 Bad Request`: Invalid input
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `409 Conflict`: Resource conflict (e.g., duplicate SKU)
- `422 Unprocessable Entity`: Validation errors
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Error Response Structure
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "sku": ["The sku field is required."],
      "name": ["The name field must be a string."]
    }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

### Validation Errors
- Field-level validation messages
- Nested object validation
- Custom validation rules for business logic

## Security Considerations

### Data Protection
- Input sanitization and validation
- SQL injection prevention (Eloquent ORM)
- XSS protection in responses
- CSRF protection for web endpoints

### Authentication Security
- Secure token storage
- Token expiration and refresh
- Brute force protection
- Account lockout policies

### Authorization Security
- Principle of least privilege
- Resource ownership validation
- Tenant data isolation
- Audit logging for sensitive operations

### API Security
- Rate limiting (100 requests/minute per user)
- Request size limits
- CORS configuration
- API versioning for backward compatibility

## Testing Strategy

### Unit Tests
- Model methods and business logic
- Service classes
- Utility functions
- Validation rules

### Feature Tests
- API endpoints
- Authentication flows
- Authorization checks
- Business workflows

### Integration Tests
- Database operations
- External service integrations
- Queue job processing

### Test Data
- Factory classes for test data generation
- Seeded test tenants and users
- Mock external services

### Test Coverage
- Target: 80%+ code coverage
- Critical path testing
- Edge case coverage

## Deployment and Scaling

### Environment Configuration
- Separate configs for dev/staging/production
- Environment variables for secrets
- Database connection pooling

### Scaling Considerations
- Horizontal scaling with load balancers
- Database read replicas
- Redis for caching and sessions
- Queue workers for background jobs

### Monitoring
- Application performance monitoring
- Error tracking and alerting
- Database query monitoring
- API usage analytics

### Backup and Recovery
- Automated database backups
- Point-in-time recovery
- Data retention policies
- Disaster recovery plan

## Conclusion

This API design document provides a comprehensive blueprint for building a scalable, multi-tenant SaaS platform for retail businesses. The single database architecture ensures cost efficiency while maintaining data isolation through tenant scoping.

Key implementation priorities:
1. Core authentication and tenant isolation
2. Basic CRUD operations for stores, warehouses, and products
3. Inventory management system
4. Order processing workflow
5. Multi-level pricing (optional feature)

The design follows RESTful principles, includes proper error handling, and incorporates security best practices. Regular reviews and updates to this document should be conducted as the system evolves.

### Next Steps
1. Review and approval of this design document
2. Create detailed implementation specifications
3. Set up development environment
4. Begin implementation of core authentication
5. Develop API endpoints iteratively
6. Implement comprehensive testing suite
7. Plan deployment and scaling strategy

---

**Document Version**: 1.0  
**Date**: March 19, 2026  
**Author**: AI Assistant  
**Review Status**: Draft