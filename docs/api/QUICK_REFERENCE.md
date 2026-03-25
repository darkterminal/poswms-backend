# API Quick Reference Guide

## Quick Start

### 1. Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

### 2. Use Token in Requests
```bash
curl -X GET http://localhost:8000/api/v1/tenants/1/stores \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Authentication

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/v1/auth/login` | POST | No | Login user |
| `/api/v1/tenants/{id}/auth/logout` | POST | Yes | Logout |
| `/api/v1/tenants/{id}/auth/refresh` | POST | Yes | Refresh token |
| `/api/v1/tenants/{id}/auth/me` | GET | Yes | Get current user |

---

## Core Resources

### Stores
```
GET    /api/v1/tenants/{id}/stores
POST   /api/v1/tenants/{id}/stores
GET    /api/v1/tenants/{id}/stores/{storeId}
PUT    /api/v1/tenants/{id}/stores/{storeId}
DELETE /api/v1/tenants/{id}/stores/{storeId}
```

### Warehouses
```
GET    /api/v1/tenants/{id}/warehouses
POST   /api/v1/tenants/{id}/warehouses
GET    /api/v1/tenants/{id}/warehouses/{warehouseId}
PUT    /api/v1/tenants/{id}/warehouses/{warehouseId}
DELETE /api/v1/tenants/{id}/warehouses/{warehouseId}
```

### Products
```
GET    /api/v1/tenants/{id}/products?category_id=1&search=widget
POST   /api/v1/tenants/{id}/products
GET    /api/v1/tenants/{id}/products/{productId}
PUT    /api/v1/tenants/{id}/products/{productId}
DELETE /api/v1/tenants/{id}/products/{productId}
```

### Categories
```
GET    /api/v1/tenants/{id}/categories
POST   /api/v1/tenants/{id}/categories
GET    /api/v1/tenants/{id}/categories/{categoryId}
PUT    /api/v1/tenants/{id}/categories/{categoryId}
DELETE /api/v1/tenants/{id}/categories/{categoryId}
```

### Customers
```
GET    /api/v1/tenants/{id}/customers
POST   /api/v1/tenants/{id}/customers
GET    /api/v1/tenants/{id}/customers/{customerId}
PUT    /api/v1/tenants/{id}/customers/{customerId}
DELETE /api/v1/tenants/{id}/customers/{customerId}
```

---

## Inventory

### Inventory Management
```
GET    /api/v1/tenants/{id}/inventory?product_id=1&warehouse_id=2
POST   /api/v1/tenants/{id}/inventory
GET    /api/v1/tenants/{id}/inventory/{inventoryId}
PUT    /api/v1/tenants/{id}/inventory/{inventoryId}
DELETE /api/v1/tenants/{id}/inventory/{inventoryId}
```

### Stock Transfers
```
POST   /api/v1/tenants/{id}/inventory/transfer
GET    /api/v1/tenants/{id}/inventory/product/{productId}/transferable
```

**Transfer Request:**
```json
{
  "product_id": 1,
  "from_location_type": "warehouse",
  "from_location_id": 1,
  "to_location_type": "store",
  "to_location_id": 2,
  "quantity": 50,
  "notes": "Restocking"
}
```

---

## Orders

### Order Management
```
GET    /api/v1/tenants/{id}/orders?status=pending&customer_id=1
POST   /api/v1/tenants/{id}/orders
GET    /api/v1/tenants/{id}/orders/{orderId}
PUT    /api/v1/tenants/{id}/orders/{orderId}
DELETE /api/v1/tenants/{id}/orders/{orderId}
```

### Order Actions
```
POST   /api/v1/tenants/{id}/orders/{orderId}/confirm
POST   /api/v1/tenants/{id}/orders/{orderId}/fulfill
POST   /api/v1/tenants/{id}/orders/{orderId}/cancel
```

**Create Order:**
```json
{
  "customer_id": 1,
  "order_date": "2026-03-20T10:00:00Z",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 29.99
    }
  ]
}
```

---

## Pricing

### Pricing Tiers & Rules
```
GET    /api/v1/tenants/{id}/pricing-tiers
POST   /api/v1/tenants/{id}/pricing-tiers
GET    /api/v1/tenants/{id}/pricing-rules
POST   /api/v1/tenants/{id}/pricing-rules
PUT    /api/v1/tenants/{id}/pricing-rules/{ruleId}
DELETE /api/v1/tenants/{id}/pricing-rules/{ruleId}
```

### Price Calculation
```
POST   /api/v1/tenants/{id}/prices/calculate
POST   /api/v1/tenants/{id}/prices/calculate-cart
```

**Calculate Price:**
```json
{
  "product_id": 1,
  "customer_id": 1,
  "quantity": 10
}
```

---

## Roles & Permissions (Admin Only)

```
GET    /api/v1/tenants/{id}/roles
POST   /api/v1/tenants/{id}/roles
GET    /api/v1/tenants/{id}/roles/{roleId}
PUT    /api/v1/tenants/{id}/roles/{roleId}
DELETE /api/v1/tenants/{id}/roles/{roleId}
POST   /api/v1/tenants/{id}/users/{userId}/assign-role
DELETE /api/v1/tenants/{id}/users/{userId}/remove-role/{roleId}
GET    /api/v1/tenants/{id}/permissions
POST   /api/v1/tenants/{id}/permissions
DELETE /api/v1/tenants/{id}/permissions/{permissionId}
```

---

## Reports & Dashboard

### Dashboard
```
GET    /api/v1/tenants/{id}/dashboard
```

### Inventory Reports
```
GET    /api/v1/tenants/{id}/reports/inventory/low-stock
GET    /api/v1/tenants/{id}/reports/inventory
GET    /api/v1/tenants/{id}/reports/inventory/stock-levels
GET    /api/v1/tenants/{id}/reports/inventory/movements
```

### Sales Reports
```
GET    /api/v1/tenants/{id}/reports/sales/revenue?start_date=2026-01-01&end_date=2026-03-31
GET    /api/v1/tenants/{id}/reports/sales/orders-by-period?group_by=month
GET    /api/v1/tenants/{id}/reports/sales/top-products?limit=10
GET    /api/v1/tenants/{id}/reports/sales/dashboard
```

### Export Reports (Admin Only)
```
GET    /api/v1/tenants/{id}/reports/inventory/export/stock-levels
GET    /api/v1/tenants/{id}/reports/inventory/export/movements
GET    /api/v1/tenants/{id}/reports/inventory/export/low-stock
GET    /api/v1/tenants/{id}/reports/sales/export/revenue
GET    /api/v1/tenants/{id}/reports/sales/export/orders-by-period
GET    /api/v1/tenants/{id}/reports/sales/export/top-products
```

---

## Webhooks (Admin Only)

```
GET    /api/v1/tenants/{id}/webhooks
POST   /api/v1/tenants/{id}/webhooks
GET    /api/v1/tenants/{id}/webhooks/{webhookId}
PUT    /api/v1/tenants/{id}/webhooks/{webhookId}
DELETE /api/v1/tenants/{id}/webhooks/{webhookId}
POST   /api/v1/tenants/{id}/webhooks/{webhookId}/test
GET    /api/v1/tenants/{id}/webhooks/{webhookId}/attempts
POST   /api/v1/tenants/{id}/webhooks/{webhookId}/retry
```

**Create Webhook:**
```json
{
  "name": "Order Notifications",
  "url": "https://your-app.com/webhooks/orders",
  "secret": "your-secret-key",
  "events": ["order.created", "order.updated"],
  "active": true,
  "content_type": "json",
  "headers": {"X-Custom-Header": "value"},
  "retry_count": 3,
  "timeout": 30
}
```

---

## Audit Logs (Admin Only)

```
GET    /api/v1/tenants/{id}/audit-logs?event_type=created&user_id=1
GET    /api/v1/tenants/{id}/audit-logs/{auditLogId}
GET    /api/v1/tenants/{id}/audit-logs/summary?start_date=2026-01-01
GET    /api/v1/tenants/{id}/audit-logs/by-user/{userId}
```

---

## Super Admin Endpoints

### Authentication
```
POST   /api/v1/admin/auth/login
POST   /api/v1/admin/auth/logout
GET    /api/v1/admin/auth/me
```

### Tenant Management
```
GET    /api/v1/admin/tenants?status=active&search=acme
POST   /api/v1/admin/tenants
GET    /api/v1/admin/tenants/{id}
PUT    /api/v1/admin/tenants/{id}
DELETE /api/v1/admin/tenants/{id}
POST   /api/v1/admin/tenants/{id}/activate
POST   /api/v1/admin/tenants/{id}/suspend
GET    /api/v1/admin/tenants/{id}/stats
```

### Subscription Management
```
POST   /api/v1/admin/tenants/{id}/trial
POST   /api/v1/admin/tenants/{id}/trial/extend
POST   /api/v1/admin/tenants/{id}/subscription
POST   /api/v1/admin/tenants/{id}/subscription/extend
POST   /api/v1/admin/tenants/{id}/subscription/cancel
POST   /api/v1/admin/tenants/{id}/convert-to-paid
```

### System Dashboard
```
GET    /api/v1/admin/dashboard
GET    /api/v1/admin/dashboard/revenue
GET    /api/v1/admin/dashboard/usage
GET    /api/v1/admin/dashboard/alerts
```

### User Management
```
GET    /api/v1/admin/users?tenant_id=1&role=admin
GET    /api/v1/admin/users/{id}
POST   /api/v1/admin/users/{id}/impersonate
POST   /api/v1/admin/users/stop-impersonating
GET    /api/v1/admin/users/{id}/impersonation-sessions
POST   /api/v1/admin/users/{id}/revoke-impersonation
```

### System Configuration
```
GET    /api/v1/admin/settings
PUT    /api/v1/admin/settings
POST   /api/v1/admin/settings/clear-cache
GET    /api/v1/admin/settings/health
POST   /api/v1/admin/settings/run-command
```

### Global Audit Logs
```
GET    /api/v1/admin/audit-logs
GET    /api/v1/admin/audit-logs/summary
GET    /api/v1/admin/audit-logs/by-user/{userId}
```

---

## HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid request |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Resource conflict |
| 422 | Unprocessable | Validation errors |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Server error |

---

## Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."]
    }
  },
  "meta": {
    "timestamp": "2026-03-25T10:00:00Z"
  }
}
```

---

## Success Response Format

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Example"
  },
  "message": "Operation successful"
}
```

---

## Pagination Response

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

---

## Rate Limits

| Limiter | Requests/Min | Applied To |
|---------|--------------|------------|
| `auth` | 10 | Login endpoints |
| `api` | 60 | Standard tenant API endpoints |
| `api-admin` | 120 | Admin-only tenant endpoints |
| `api-webhook-test` | 10 | Webhook test endpoints |
| `api-exports` | 30 | Report export endpoints |
| `throttle:api-admin` (Super Admin) | 200 | Super Admin endpoints |

---

## Common Query Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `page` | Page number | `?page=2` |
| `per_page` | Items per page (max 100) | `?per_page=50` |
| `sort` | Sort field | `?sort=created_at` |
| `order` | Sort order (asc/desc) | `?order=desc` |
| `search` | Search query | `?search=widget` |
| `status` | Filter by status | `?status=active` |
| `start_date` | Date filter start | `?start_date=2026-01-01` |
| `end_date` | Date filter end | `?end_date=2026-03-31` |

---

## Headers

### Required Headers
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

### Optional Headers
```
X-Request-ID: unique-request-id
X-Tenant-ID: tenant-id (if not in URL)
```

---

## Date Format

All dates use ISO 8601 format:
```
2026-03-25T10:00:00Z
```

---

## Resources

- **Full Documentation:** [`docs/api/README.md`](../../docs/api/README.md)
- **Super Admin Docs:** [`docs/api/00-super-admin.md`](../../docs/api/00-super-admin.md)
- **OpenAPI Spec:** [`swagger/openapi.yaml`](../../swagger/openapi.yaml)
- **Postman Collection:** [`postman/Super_Admin_Collection.json`](../../postman/Super_Admin_Collection.json)

---

## Support

- **Email:** api-support@poswms.com
- **Documentation:** https://docs.poswms.com/api
- **Status:** https://status.poswms.com
