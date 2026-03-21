# POS WMS Backend API Documentation

Complete API documentation for the Multi-Store & Warehouse Management System (SaaS).

## Table of Contents

### Getting Started
- [API Overview](01-overview.md) - Base URL, authentication, response formats
- [Authentication](02-authentication.md) - Login, logout, token refresh

### Core Resources
- [Stores](03-stores.md) - Store management endpoints
- [Warehouses](04-warehouses.md) - Warehouse management endpoints
- [Products](05-products.md) - Product catalog management
- [Categories](06-categories.md) - Product categorization
- [Customers](09-customers.md) - Customer management

### Inventory & Orders
- [Inventory](07-inventory.md) - Stock management and transfers
- [Orders](08-orders.md) - Order processing and fulfillment

### Pricing
- [Pricing](10-pricing.md) - Pricing tiers, rules, and calculations

### Access Control
- [Roles & Permissions](11-roles-permissions.md) - RBAC management

### Reporting
- [Reports & Dashboard](12-reports.md) - Analytics and exports

### System
- [Webhooks & Audit Logs](13-webhooks-audit.md) - Event notifications and logging

---

## Quick Reference

### Base URL
```
/api/v1
```

### Authentication
All endpoints (except `/auth/login`) require Bearer token authentication:
```
Authorization: Bearer {token}
```

### Multi-Tenant
All resource endpoints require a tenant ID:
```
/api/v1/tenants/{tenant_id}/resources
```

---

## Endpoint Summary

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | Login and get token |
| POST | `/tenants/{id}/auth/logout` | Logout (revoke token) |
| POST | `/tenants/{id}/auth/refresh` | Refresh token |
| GET | `/tenants/{id}/auth/me` | Get current user |

### Stores
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/stores` | List stores |
| POST | `/tenants/{id}/stores` | Create store |
| GET | `/tenants/{id}/stores/{storeId}` | Get store |
| PUT | `/tenants/{id}/stores/{storeId}` | Update store |
| DELETE | `/tenants/{id}/stores/{storeId}` | Delete store |

### Warehouses
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/warehouses` | List warehouses |
| POST | `/tenants/{id}/warehouses` | Create warehouse |
| GET | `/tenants/{id}/warehouses/{warehouseId}` | Get warehouse |
| PUT | `/tenants/{id}/warehouses/{warehouseId}` | Update warehouse |
| DELETE | `/tenants/{id}/warehouses/{warehouseId}` | Delete warehouse |

### Products
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/products` | List products |
| POST | `/tenants/{id}/products` | Create product |
| GET | `/tenants/{id}/products/{productId}` | Get product |
| PUT | `/tenants/{id}/products/{productId}` | Update product |
| DELETE | `/tenants/{id}/products/{productId}` | Delete product |

### Categories
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/categories` | List categories |
| POST | `/tenants/{id}/categories` | Create category |
| GET | `/tenants/{id}/categories/{categoryId}` | Get category |
| PUT | `/tenants/{id}/categories/{categoryId}` | Update category |
| DELETE | `/tenants/{id}/categories/{categoryId}` | Delete category |

### Customers
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/customers` | List customers |
| POST | `/tenants/{id}/customers` | Create customer |
| GET | `/tenants/{id}/customers/{customerId}` | Get customer |
| PUT | `/tenants/{id}/customers/{customerId}` | Update customer |
| DELETE | `/tenants/{id}/customers/{customerId}` | Delete customer |

### Inventory
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/inventory` | List inventory |
| POST | `/tenants/{id}/inventory` | Create inventory |
| GET | `/tenants/{id}/inventory/{inventoryId}` | Get inventory |
| PUT | `/tenants/{id}/inventory/{inventoryId}` | Update inventory |
| DELETE | `/tenants/{id}/inventory/{inventoryId}` | Delete inventory |
| POST | `/tenants/{id}/inventory/transfer` | Transfer stock |
| GET | `/tenants/{id}/inventory/product/{id}/transferable` | Get transferable stock |

### Orders
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/orders` | List orders |
| POST | `/tenants/{id}/orders` | Create order |
| GET | `/tenants/{id}/orders/{orderId}` | Get order |
| PUT | `/tenants/{id}/orders/{orderId}` | Update order |
| DELETE | `/tenants/{id}/orders/{orderId}` | Delete order |
| POST | `/tenants/{id}/orders/{orderId}/confirm` | Confirm order |
| POST | `/tenants/{id}/orders/{orderId}/fulfill` | Fulfill order |
| POST | `/tenants/{id}/orders/{orderId}/cancel` | Cancel order |

### Pricing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/pricing-tiers` | List pricing tiers |
| POST | `/tenants/{id}/pricing-tiers` | Create pricing tier |
| GET | `/tenants/{id}/pricing-tiers/{tierId}` | Get pricing tier |
| PUT | `/tenants/{id}/pricing-tiers/{tierId}` | Update pricing tier |
| DELETE | `/tenants/{id}/pricing-tiers/{tierId}` | Delete pricing tier |
| GET | `/tenants/{id}/pricing-rules` | List pricing rules |
| POST | `/tenants/{id}/pricing-rules` | Create pricing rule |
| GET | `/tenants/{id}/pricing-rules/{ruleId}` | Get pricing rule |
| PUT | `/tenants/{id}/pricing-rules/{ruleId}` | Update pricing rule |
| DELETE | `/tenants/{id}/pricing-rules/{ruleId}` | Delete pricing rule |
| POST | `/tenants/{id}/prices/calculate` | Calculate product price |
| POST | `/tenants/{id}/prices/calculate-cart` | Calculate cart price |

### Roles & Permissions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/roles` | List roles |
| POST | `/tenants/{id}/roles` | Create role |
| GET | `/tenants/{id}/roles/{roleId}` | Get role |
| PUT | `/tenants/{id}/roles/{roleId}` | Update role |
| DELETE | `/tenants/{id}/roles/{roleId}` | Delete role |
| POST | `/tenants/{id}/users/{userId}/assign-role` | Assign role to user |
| DELETE | `/tenants/{id}/users/{userId}/remove-role/{roleId}` | Remove role from user |
| GET | `/tenants/{id}/permissions` | List permissions |
| POST | `/tenants/{id}/permissions` | Create permission |
| GET | `/tenants/{id}/permissions/{permissionId}` | Get permission |
| PUT | `/tenants/{id}/permissions/{permissionId}` | Update permission |
| DELETE | `/tenants/{id}/permissions/{permissionId}` | Delete permission |

### Reports & Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/dashboard` | Get dashboard metrics |
| GET | `/tenants/{id}/reports/sales/revenue` | Revenue report |
| GET | `/tenants/{id}/reports/sales/orders-by-period` | Orders by period |
| GET | `/tenants/{id}/reports/sales/top-products` | Top products |
| GET | `/tenants/{id}/reports/sales/export/revenue` | Export revenue (CSV) |
| GET | `/tenants/{id}/reports/sales/export/orders-by-period` | Export orders (CSV) |
| GET | `/tenants/{id}/reports/sales/export/top-products` | Export products (CSV) |
| GET | `/tenants/{id}/reports/inventory` | Inventory report |
| GET | `/tenants/{id}/reports/inventory/stock-levels` | Stock levels |
| GET | `/tenants/{id}/reports/inventory/movements` | Inventory movements |
| GET | `/tenants/{id}/reports/inventory/low-stock` | Low stock alerts |
| GET | `/tenants/{id}/reports/inventory/export/stock-levels` | Export stock (CSV) |
| GET | `/tenants/{id}/reports/inventory/export/movements` | Export movements (CSV) |
| GET | `/tenants/{id}/reports/inventory/export/low-stock` | Export low stock (CSV) |

### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/webhooks` | List webhooks |
| POST | `/tenants/{id}/webhooks` | Create webhook |
| GET | `/tenants/{id}/webhooks/{webhook}` | Get webhook |
| PUT | `/tenants/{id}/webhooks/{webhook}` | Update webhook |
| DELETE | `/tenants/{id}/webhooks/{webhook}` | Delete webhook |
| POST | `/tenants/{id}/webhooks/{webhook}/test` | Test webhook |
| GET | `/tenants/{id}/webhooks/{webhook}/attempts` | Get delivery attempts |
| POST | `/tenants/{id}/webhooks/{webhook}/retry` | Retry failed deliveries |

### Audit Logs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/audit-logs` | List audit logs |
| GET | `/tenants/{id}/audit-logs/{auditLog}` | Get audit log |
| GET | `/tenants/{id}/audit-logs/by-user/{userId}` | Get user's audit logs |
| GET | `/tenants/{id}/audit-logs/summary` | Get audit summary |

---

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description",
    "details": { ... }
  }
}
```

---

## HTTP Status Codes

| Code | Status | Description |
|------|--------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid request |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

---

## Testing

### REST Client

A mini REST client is available for testing all API endpoints:

```bash
# Run all tests
php tests/api/RestClient.php

# Run with custom configuration
php tests/api/RestClient.php --base-url=http://localhost:8000 --tenant=1 --email=admin@example.com

# Test specific endpoint
php tests/api/RestClient.php --endpoint=products

# Enable verbose output
php tests/api/RestClient.php --verbose
```

For complete documentation, see [REST Client Documentation](../../tests/api/README.md).

---

## Support

For API support, contact your system administrator or refer to the internal documentation.

**API Version:** v1
**Last Updated:** March 20, 2026
