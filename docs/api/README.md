# API Documentation - POS WMS Backend

## Multi-Store & Warehouse Management System (MSWMS)

**Version:** 1.0.0  
**Base URL:** `http://localhost:8000/api/v1` (development)  
**OpenAPI Spec:** [`/swagger/openapi.yaml`](../../swagger/openapi.yaml)  
**Postman Collection:** [`/postman/Super_Admin_Collection.json`](../../postman/Super_Admin_Collection.json)

---

## Table of Contents

- [Quick Start](#quick-start)
- [Authentication](#authentication)
- [Multi-Tenancy](#multi-tenancy)
- [Rate Limiting](#rate-limiting)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Endpoint Reference](#endpoint-reference)
- [Code Examples](#code-examples)
- [Webhooks](#webhooks)
- [Interactive Documentation](#interactive-documentation)

---

## Quick Start

### 1. Get Your API Token

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "tenant_id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

### 2. Make Your First API Call

```bash
curl -X GET http://localhost:8000/api/v1/tenants/1/stores \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

---

## Authentication

### Getting a Token

**Endpoint:** `POST /api/v1/auth/login`

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "store_manager",
      "store_id": 1,
      "warehouse_id": null,
      "is_active": true
    },
    "token": "1|abc123xyz...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

**Error Response (401):**
```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "The provided credentials are incorrect.",
    "details": {
      "email": ["The provided credentials are incorrect."]
    }
  }
}
```

### Using the Token

Include the Bearer token in the `Authorization` header for all authenticated requests:

```bash
Authorization: Bearer {your-token}
```

### Token Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/login` | POST | Get access token |
| `/api/v1/tenants/{tenant_id}/auth/logout` | POST | Invalidate current token |
| `/api/v1/tenants/{tenant_id}/auth/refresh` | POST | Generate new token |
| `/api/v1/tenants/{tenant_id}/auth/me` | GET | Get current user info |

---

## Multi-Tenancy

All authenticated endpoints (except login) require a `tenant_id` path parameter:

```
/api/v1/tenants/{tenant_id}/{resource}
```

**Example:**
```bash
# Get stores for tenant 1
GET /api/v1/tenants/1/stores

# Get products for tenant 5
GET /api/v1/tenants/5/products

# Create order for tenant 2
POST /api/v1/tenants/2/orders
```

### Tenant Scoping

The `EnsureTenantIsScoped` middleware automatically ensures that all queries are scoped to the specified tenant, preventing cross-tenant data access. All database queries include a `WHERE tenant_id = ?` clause.

---

## Rate Limiting

API requests are protected by rate limiters to ensure fair usage and system stability:

| Limiter | Requests/Minute | Applied To |
|---------|-----------------|------------|
| `auth` | 10 | Authentication endpoints (login) |
| `api` | 60 | Standard API endpoints |
| `api-admin` | 120 | Admin-only endpoints (tenant) |
| `api-webhook-test` | 10 | Webhook test endpoints |
| `api-exports` | 30 | Report export endpoints |
| `throttle:api-admin` | 200 | Super Admin endpoints |

When rate limited, you'll receive a `429 Too Many Requests` response:

```json
{
  "success": false,
  "message": "Too many requests. Please try again in 60 seconds.",
  "retry_after": 60
}
```

---

## Response Format

### Success Response

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Example Store",
    "code": "STORE-001",
    "email": "store@example.com",
    "phone": "+1234567890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "country": "USA",
    "postal_code": "10001",
    "is_active": true,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  },
  "message": "Operation successful"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "name": ["The name must be at least 3 characters."]
    }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

### Pagination

List endpoints return paginated results:

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "/api/v1/tenants/1/stores?page=1",
    "last": "/api/v1/tenants/1/stores?page=10",
    "prev": null,
    "next": "/api/v1/tenants/1/stores?page=2"
  }
}
```

**Pagination Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)

---

## Error Handling

### HTTP Status Codes

| Code | Description | Common Scenarios |
|------|-------------|------------------|
| 200 | OK | Successful GET, PUT, PATCH requests |
| 201 | Created | Resource created successfully (POST) |
| 400 | Bad Request | Malformed request or invalid syntax |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Valid auth but insufficient permissions |
| 404 | Not Found | Resource doesn't exist or not accessible |
| 409 | Conflict | Resource conflict (e.g., duplicate SKU) |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |
| 503 | Service Unavailable | Maintenance or overload |

### Common Error Codes

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `AUTHENTICATION_FAILED` | 401 | Invalid credentials |
| `UNAUTHENTICATED` | 401 | Missing or expired token |
| `UNAUTHORIZED` | 403 | Insufficient permissions |
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `TENANT_NOT_FOUND` | 404 | Specified tenant doesn't exist |
| `RESOURCE_NOT_FOUND` | 404 | Resource not found within tenant |
| `DUPLICATE_RESOURCE` | 409 | Resource already exists (e.g., duplicate SKU) |
| `INSUFFICIENT_STOCK` | 422 | Not enough inventory for operation |
| `INVALID_OPERATION` | 422 | Business logic violation |

### Validation Error Example

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "sku": ["The sku field is required."],
      "name": ["The name must be at least 3 characters."],
      "base_price": ["The base price must be a positive number."]
    }
  }
}
```

---

## Endpoint Reference

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/v1/auth/login` | Login user | No |
| POST | `/api/v1/tenants/{id}/auth/logout` | Logout user | Yes |
| POST | `/api/v1/tenants/{id}/auth/refresh` | Refresh token | Yes |
| GET | `/api/v1/tenants/{id}/auth/me` | Get current user | Yes |

### Stores

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/stores` | List all stores |
| POST | `/api/v1/tenants/{id}/stores` | Create store |
| GET | `/api/v1/tenants/{id}/stores/{storeId}` | Get store |
| PUT | `/api/v1/tenants/{id}/stores/{storeId}` | Update store |
| DELETE | `/api/v1/tenants/{id}/stores/{storeId}` | Delete store |

**Create Store Example:**
```json
{
  "name": "Downtown Store",
  "code": "DT-001",
  "email": "downtown@example.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "is_active": true
}
```

### Warehouses

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/warehouses` | List all warehouses |
| POST | `/api/v1/tenants/{id}/warehouses` | Create warehouse |
| GET | `/api/v1/tenants/{id}/warehouses/{warehouseId}` | Get warehouse |
| PUT | `/api/v1/tenants/{id}/warehouses/{warehouseId}` | Update warehouse |
| DELETE | `/api/v1/tenants/{id}/warehouses/{warehouseId}` | Delete warehouse |

**Create Warehouse Example:**
```json
{
  "name": "Main Warehouse",
  "code": "MW-001",
  "address": "456 Industrial Blvd",
  "city": "Chicago",
  "state": "IL",
  "country": "USA",
  "postal_code": "60601",
  "capacity": 10000,
  "is_active": true
}
```

### Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/products` | List all products |
| POST | `/api/v1/tenants/{id}/products` | Create product |
| GET | `/api/v1/tenants/{id}/products/{productId}` | Get product |
| PUT | `/api/v1/tenants/{id}/products/{productId}` | Update product |
| DELETE | `/api/v1/tenants/{id}/products/{productId}` | Delete product |

**Query Parameters:**
- `category_id` - Filter by category
- `search` - Search by name or SKU

**Create Product Example:**
```json
{
  "name": "Widget Pro",
  "sku": "WGT-PRO-001",
  "description": "Professional grade widget",
  "category_id": 1,
  "base_price": 29.99,
  "cost_price": 15.00,
  "min_stock_level": 10,
  "is_active": true
}
```

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/categories` | List all categories |
| POST | `/api/v1/tenants/{id}/categories` | Create category |
| GET | `/api/v1/tenants/{id}/categories/{categoryId}` | Get category |
| PUT | `/api/v1/tenants/{id}/categories/{categoryId}` | Update category |
| DELETE | `/api/v1/tenants/{id}/categories/{categoryId}` | Delete category |

**Create Category Example:**
```json
{
  "name": "Electronics",
  "parent_id": null,
  "description": "Electronic products and accessories",
  "is_active": true
}
```

### Customers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/customers` | List all customers |
| POST | `/api/v1/tenants/{id}/customers` | Create customer |
| GET | `/api/v1/tenants/{id}/customers/{customerId}` | Get customer |
| PUT | `/api/v1/tenants/{id}/customers/{customerId}` | Update customer |
| DELETE | `/api/v1/tenants/{id}/customers/{customerId}` | Delete customer |

**Create Customer Example:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1987654321",
  "address": "789 Customer Lane",
  "city": "Boston",
  "state": "MA",
  "country": "USA",
  "postal_code": "02101",
  "pricing_tier_id": 2
}
```

### Inventory

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/inventory` | List inventory levels |
| POST | `/api/v1/tenants/{id}/inventory` | Create inventory record |
| GET | `/api/v1/tenants/{id}/inventory/{inventoryId}` | Get inventory |
| PUT | `/api/v1/tenants/{id}/inventory/{inventoryId}` | Update inventory |
| DELETE | `/api/v1/tenants/{id}/inventory/{inventoryId}` | Delete inventory |
| POST | `/api/v1/tenants/{id}/inventory/transfer` | Transfer stock |
| GET | `/api/v1/tenants/{id}/inventory/product/{productId}/transferable` | Get transferable qty |

**Query Parameters:**
- `product_id` - Filter by product
- `warehouse_id` - Filter by warehouse
- `store_id` - Filter by store

**Transfer Stock Example:**
```json
{
  "product_id": 1,
  "from_location_type": "warehouse",
  "from_location_id": 1,
  "to_location_type": "store",
  "to_location_id": 2,
  "quantity": 50,
  "notes": "Restocking downtown store"
}
```

### Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/orders` | List all orders |
| POST | `/api/v1/tenants/{id}/orders` | Create order |
| GET | `/api/v1/tenants/{id}/orders/{orderId}` | Get order |
| PUT | `/api/v1/tenants/{id}/orders/{orderId}` | Update order |
| DELETE | `/api/v1/tenants/{id}/orders/{orderId}` | Delete order |
| POST | `/api/v1/tenants/{id}/orders/{orderId}/confirm` | Confirm order |
| POST | `/api/v1/tenants/{id}/orders/{orderId}/fulfill` | Fulfill order |
| POST | `/api/v1/tenants/{id}/orders/{orderId}/cancel` | Cancel order |

**Query Parameters:**
- `status` - Filter by status (pending, confirmed, fulfilling, completed, cancelled)
- `customer_id` - Filter by customer
- `store_id` - Filter by store

**Create Order Example:**
```json
{
  "customer_id": 1,
  "order_date": "2026-03-20T10:00:00Z",
  "notes": "Rush order",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 29.99
    },
    {
      "product_id": 2,
      "quantity": 1,
      "unit_price": 49.99
    }
  ]
}
```

### Pricing

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/pricing-tiers` | List pricing tiers |
| POST | `/api/v1/tenants/{id}/pricing-tiers` | Create pricing tier |
| GET | `/api/v1/tenants/{id}/pricing-tiers/{tierId}` | Get pricing tier |
| PUT | `/api/v1/tenants/{id}/pricing-tiers/{tierId}` | Update pricing tier |
| DELETE | `/api/v1/tenants/{id}/pricing-tiers/{tierId}` | Delete pricing tier |
| GET | `/api/v1/tenants/{id}/pricing-rules` | List pricing rules |
| POST | `/api/v1/tenants/{id}/pricing-rules` | Create pricing rule |
| GET | `/api/v1/tenants/{id}/pricing-rules/{ruleId}` | Get pricing rule |
| PUT | `/api/v1/tenants/{id}/pricing-rules/{ruleId}` | Update pricing rule |
| DELETE | `/api/v1/tenants/{id}/pricing-rules/{ruleId}` | Delete pricing rule |
| POST | `/api/v1/tenants/{id}/prices/calculate` | Calculate price |
| POST | `/api/v1/tenants/{id}/prices/calculate-cart` | Calculate cart total |

**Create Pricing Tier Example:**
```json
{
  "name": "Gold Tier",
  "slug": "gold",
  "description": "Best pricing for VIP customers",
  "discount_percentage": 15.0,
  "is_active": true
}
```

**Calculate Price Example:**
```json
{
  "product_id": 1,
  "customer_id": 1,
  "quantity": 10
}
```

### Roles & Permissions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/roles` | List all roles |
| POST | `/api/v1/tenants/{id}/roles` | Create role |
| GET | `/api/v1/tenants/{id}/roles/{roleId}` | Get role |
| PUT | `/api/v1/tenants/{id}/roles/{roleId}` | Update role |
| DELETE | `/api/v1/tenants/{id}/roles/{roleId}` | Delete role |
| POST | `/api/v1/tenants/{id}/users/{userId}/assign-role` | Assign role to user |
| DELETE | `/api/v1/tenants/{id}/users/{userId}/remove-role/{roleId}` | Remove role from user |
| GET | `/api/v1/tenants/{id}/permissions` | List all permissions |
| POST | `/api/v1/tenants/{id}/permissions` | Create permission |
| DELETE | `/api/v1/tenants/{id}/permissions/{permissionId}` | Delete permission |

### Reports & Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/dashboard` | Get unified dashboard metrics |
| GET | `/api/v1/tenants/{id}/reports/inventory/low-stock` | Low stock report |
| GET | `/api/v1/tenants/{id}/reports/inventory` | Inventory report |
| GET | `/api/v1/tenants/{id}/reports/inventory/stock-levels` | Stock levels report |
| GET | `/api/v1/tenants/{id}/reports/inventory/movements` | Inventory movements |
| GET | `/api/v1/tenants/{id}/reports/sales/revenue` | Sales revenue report |
| GET | `/api/v1/tenants/{id}/reports/sales/orders-by-period` | Orders by period |
| GET | `/api/v1/tenants/{id}/reports/sales/top-products` | Top products report |
| GET | `/api/v1/tenants/{id}/reports/sales/dashboard` | Sales dashboard metrics |

**Admin-Only Export Endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/reports/inventory/export/stock-levels` | Export stock levels (CSV) |
| GET | `/api/v1/tenants/{id}/reports/inventory/export/movements` | Export movements (CSV) |
| GET | `/api/v1/tenants/{id}/reports/inventory/export/low-stock` | Export low stock (CSV) |
| GET | `/api/v1/tenants/{id}/reports/sales/export/revenue` | Export revenue (CSV) |
| GET | `/api/v1/tenants/{id}/reports/sales/export/orders-by-period` | Export orders (CSV) |
| GET | `/api/v1/tenants/{id}/reports/sales/export/top-products` | Export top products (CSV) |

**Query Parameters for Reports:**
- `start_date` - Report start date (YYYY-MM-DD)
- `end_date` - Report end date (YYYY-MM-DD)
- `group_by` - Grouping (day, week, month, year)
- `warehouse_id` - Filter by warehouse
- `store_id` - Filter by store
- `category_id` - Filter by category

### Webhooks

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/webhooks` | List webhooks |
| POST | `/api/v1/tenants/{id}/webhooks` | Create webhook |
| GET | `/api/v1/tenants/{id}/webhooks/{webhookId}` | Get webhook |
| PUT | `/api/v1/tenants/{id}/webhooks/{webhookId}` | Update webhook |
| DELETE | `/api/v1/tenants/{id}/webhooks/{webhookId}` | Delete webhook |
| POST | `/api/v1/tenants/{id}/webhooks/{webhookId}/test` | Test webhook |
| GET | `/api/v1/tenants/{id}/webhooks/{webhookId}/attempts` | Get delivery attempts |
| POST | `/api/v1/tenants/{id}/webhooks/{webhookId}/retry` | Retry failed deliveries |

**Create Webhook Example:**
```json
{
  "name": "Order Notifications",
  "url": "https://your-app.com/webhooks/orders",
  "secret": "your-secret-key",
  "events": ["order.created", "order.updated", "order.cancelled"],
  "active": true,
  "content_type": "json",
  "headers": {
    "X-Custom-Header": "value"
  },
  "retry_count": 3,
  "timeout": 30
}
```

### Audit Logs (Admin Only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{id}/audit-logs` | List audit logs |
| GET | `/api/v1/tenants/{id}/audit-logs/{auditLogId}` | Get audit log entry |
| GET | `/api/v1/tenants/{id}/audit-logs/summary` | Get audit summary |
| GET | `/api/v1/tenants/{id}/audit-logs/by-user/{userId}` | Get logs by user |

**Query Parameters:**
- `event_type` - Filter by event type (created, updated, deleted)
- `user_id` - Filter by user
- `start_date` - Filter by start date
- `end_date` - Filter by end date

---

## Super Admin Endpoints

Super Admin endpoints operate at the system level and are not scoped to tenants. They use a separate authentication guard.

### Super Admin Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/admin/auth/login` | Super admin login |
| POST | `/api/v1/admin/auth/logout` | Super admin logout |
| GET | `/api/v1/admin/auth/me` | Get current super admin info |

### Tenant Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/tenants` | List all tenants |
| POST | `/api/v1/admin/tenants` | Create tenant |
| GET | `/api/v1/admin/tenants/{id}` | Get tenant details |
| PUT | `/api/v1/admin/tenants/{id}` | Update tenant |
| DELETE | `/api/v1/admin/tenants/{id}` | Delete tenant (soft) |
| POST | `/api/v1/admin/tenants/{id}/activate` | Activate tenant |
| POST | `/api/v1/admin/tenants/{id}/suspend` | Suspend tenant |
| GET | `/api/v1/admin/tenants/{id}/stats` | Get tenant statistics |

**Query Parameters for List:**
- `status` - Filter by status (active, suspended, trial)
- `search` - Search by name, email, or company name
- `per_page` - Items per page (default: 15)
- `page` - Page number

**Create Tenant Example:**
```json
{
  "name": "Acme Corporation",
  "slug": "acme-corp",
  "company_name": "Acme Corp Inc.",
  "email": "contact@acme.com",
  "subscription_plan": "premium",
  "trial_ends_at": "2026-04-20T00:00:00Z"
}
```

### Subscription Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/admin/tenants/{id}/trial` | Update trial period |
| POST | `/api/v1/admin/tenants/{id}/trial/extend` | Extend trial |
| POST | `/api/v1/admin/tenants/{id}/subscription` | Update subscription |
| POST | `/api/v1/admin/tenants/{id}/subscription/extend` | Extend subscription |
| POST | `/api/v1/admin/tenants/{id}/subscription/cancel` | Cancel subscription |
| POST | `/api/v1/admin/tenants/{id}/convert-to-paid` | Convert trial to paid |

### System Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/dashboard` | System overview metrics |
| GET | `/api/v1/admin/dashboard/revenue` | Revenue metrics |
| GET | `/api/v1/admin/dashboard/usage` | Usage statistics |
| GET | `/api/v1/admin/dashboard/alerts` | System alerts |

**Dashboard Response Example:**
```json
{
  "success": true,
  "data": {
    "total_tenants": 150,
    "active_tenants": 142,
    "tenants_on_trial": 8,
    "expiring_subscriptions": 3,
    "total_users": 1250,
    "total_stores": 320,
    "total_warehouses": 85,
    "total_products": 5600,
    "total_orders_today": 423,
    "mrr": 14058.00,
    "arr": 168696.00,
    "currency": "USD"
  }
}
```

### User Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/users` | Search all users across tenants |
| GET | `/api/v1/admin/users/{id}` | View user details |
| POST | `/api/v1/admin/users/{id}/impersonate` | Generate impersonation token |
| POST | `/api/v1/admin/users/stop-impersonating` | Stop impersonation |
| GET | `/api/v1/admin/users/{id}/impersonation-sessions` | Get impersonation sessions |
| POST | `/api/v1/admin/users/{id}/revoke-impersonation` | Revoke impersonation tokens |

**Query Parameters for List:**
- `tenant_id` - Filter by tenant
- `role` - Filter by role
- `search` - Search by name or email
- `is_active` - Filter by active status

### System Configuration

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/settings` | Get system settings |
| PUT | `/api/v1/admin/settings` | Update system settings |
| POST | `/api/v1/admin/settings/clear-cache` | Clear application cache |
| GET | `/api/v1/admin/settings/health` | System health check |
| POST | `/api/v1/admin/settings/run-command` | Run artisan command |

### Global Audit Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/audit-logs` | Global audit logs |
| GET | `/api/v1/admin/audit-logs/summary` | Global audit summary |
| GET | `/api/v1/admin/audit-logs/by-user/{userId}` | Logs by user |

---

## Code Examples

### JavaScript (Axios)

```javascript
const axios = require('axios');

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
});

// Login and get token
async function login(email, password) {
  try {
    const response = await api.post('/auth/login', { email, password });
    return response.data.data.token;
  } catch (error) {
    console.error('Login failed:', error.response?.data?.error?.message);
    throw error;
  }
}

// Get stores with pagination
async function getStores(tenantId, token, page = 1, perPage = 20) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  try {
    const response = await api.get(`/tenants/${tenantId}/stores`, {
      params: { page, per_page: perPage }
    });
    return response.data;
  } catch (error) {
    console.error('Failed to fetch stores:', error.response?.data?.error?.message);
    throw error;
  }
}

// Create a new store
async function createStore(tenantId, token, storeData) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  try {
    const response = await api.post(`/tenants/${tenantId}/stores`, storeData);
    return response.data;
  } catch (error) {
    console.error('Failed to create store:', error.response?.data?.error?.details);
    throw error;
  }
}

// Transfer inventory
async function transferInventory(tenantId, token, transferData) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  try {
    const response = await api.post(`/tenants/${tenantId}/inventory/transfer`, transferData);
    return response.data;
  } catch (error) {
    console.error('Transfer failed:', error.response?.data?.error?.message);
    throw error;
  }
}

// Usage example
(async () => {
  const token = await login('admin@example.com', 'password123');
  const stores = await getStores(1, token);
  console.log('Stores:', stores.data);
  
  const newStore = await createStore(1, token, {
    name: 'New Store',
    code: 'NS-001',
    email: 'newstore@example.com',
    phone: '+1234567890',
    address: '123 Main St',
    city: 'New York',
    state: 'NY',
    country: 'USA',
    postal_code: '10001',
    is_active: true
  });
  console.log('Created store:', newStore.data);
})();
```

### PHP (Guzzle)

```php
<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$client = new Client([
    'base_uri' => 'http://localhost:8000/api/v1/',
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

// Login and get token
function login($client, $email, $password) {
    try {
        $response = $client->post('auth/login', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        return $data['data']['token'];
    } catch (RequestException $e) {
        $error = json_decode($e->getResponse()->getBody(), true);
        throw new Exception($error['error']['message'] ?? 'Login failed');
    }
}

// Get stores with pagination
function getStores($client, $tenantId, $token, $page = 1, $perPage = 20) {
    try {
        $response = $client->get("tenants/{$tenantId}/stores", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'query' => [
                'page' => $page,
                'per_page' => $perPage
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        $error = json_decode($e->getResponse()->getBody(), true);
        throw new Exception($error['error']['message'] ?? 'Failed to fetch stores');
    }
}

// Create order with items
function createOrder($client, $tenantId, $token, $orderData) {
    try {
        $response = $client->post("tenants/{$tenantId}/orders", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'json' => $orderData
        ]);
        
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        $error = json_decode($e->getResponse()->getBody(), true);
        throw new Exception($error['error']['details'] ?? 'Failed to create order');
    }
}

// Calculate price with pricing rules
function calculatePrice($client, $tenantId, $token, $productId, $customerId, $quantity) {
    try {
        $response = $client->post("tenants/{$tenantId}/prices/calculate", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'json' => [
                'product_id' => $productId,
                'customer_id' => $customerId,
                'quantity' => $quantity
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        $error = json_decode($e->getResponse()->getBody(), true);
        throw new Exception($error['error']['message'] ?? 'Price calculation failed');
    }
}

// Usage example
try {
    $token = login($client, 'admin@example.com', 'password123');
    
    $stores = getStores($client, 1, $token);
    echo "Stores: " . count($stores['data']) . "\n";
    
    $order = createOrder($client, 1, $token, [
        'customer_id' => 1,
        'order_date' => date('c'),
        'notes' => 'Rush order',
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 29.99
            ],
            [
                'product_id' => 2,
                'quantity' => 1,
                'unit_price' => 49.99
            ]
        ]
    ]);
    
    echo "Order created: " . $order['data']['order_number'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Python (Requests)

```python
import requests
from requests.exceptions import HTTPError

BASE_URL = 'http://localhost:8000/api/v1'

def login(email, password):
    """Authenticate and get access token."""
    try:
        response = requests.post(f'{BASE_URL}/auth/login', json={
            'email': email,
            'password': password
        })
        response.raise_for_status()
        data = response.json()
        return data['data']['token']
    except HTTPError as e:
        error = e.response.json()
        raise Exception(error.get('error', {}).get('message', 'Login failed'))

def get_stores(tenant_id, token, page=1, per_page=20):
    """Get all stores for a tenant."""
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }
    
    try:
        response = requests.get(
            f'{BASE_URL}/tenants/{tenant_id}/stores',
            headers=headers,
            params={'page': page, 'per_page': per_page}
        )
        response.raise_for_status()
        return response.json()
    except HTTPError as e:
        error = e.response.json()
        raise Exception(error.get('error', {}).get('message', 'Failed to fetch stores'))

def create_product(tenant_id, token, product_data):
    """Create a new product."""
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    
    try:
        response = requests.post(
            f'{BASE_URL}/tenants/{tenant_id}/products',
            headers=headers,
            json=product_data
        )
        response.raise_for_status()
        return response.json()
    except HTTPError as e:
        error = e.response.json()
        raise Exception(error.get('error', {}).get('details', 'Failed to create product'))

def fulfill_order(tenant_id, token, order_id):
    """Fulfill an order."""
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    
    try:
        response = requests.post(
            f'{BASE_URL}/tenants/{tenant_id}/orders/{order_id}/fulfill',
            headers=headers
        )
        response.raise_for_status()
        return response.json()
    except HTTPError as e:
        error = e.response.json()
        raise Exception(error.get('error', {}).get('message', 'Failed to fulfill order'))

def get_dashboard_metrics(tenant_id, token):
    """Get unified dashboard metrics."""
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }
    
    try:
        response = requests.get(
            f'{BASE_URL}/tenants/{tenant_id}/dashboard',
            headers=headers
        )
        response.raise_for_status()
        return response.json()
    except HTTPError as e:
        error = e.response.json()
        raise Exception(error.get('error', {}).get('message', 'Failed to fetch dashboard'))

# Usage example
if __name__ == '__main__':
    try:
        # Login
        token = login('admin@example.com', 'password123')
        print(f"Logged in successfully")
        
        # Get stores
        stores = get_stores(1, token)
        print(f"Found {len(stores['data'])} stores")
        
        # Create product
        product = create_product(1, token, {
            'name': 'Widget Pro',
            'sku': 'WGT-PRO-001',
            'description': 'Professional grade widget',
            'category_id': 1,
            'base_price': 29.99,
            'cost_price': 15.00,
            'min_stock_level': 10,
            'is_active': True
        })
        print(f"Created product: {product['data']['name']}")
        
        # Get dashboard
        dashboard = get_dashboard_metrics(1, token)
        print(f"Dashboard metrics: {dashboard['data']}")
        
    except Exception as e:
        print(f"Error: {e}")
```

### cURL Examples

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'

# Get stores (replace TOKEN with actual token)
curl -X GET http://localhost:8000/api/v1/tenants/1/stores \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"

# Create product
curl -X POST http://localhost:8000/api/v1/tenants/1/products \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Widget Pro",
    "sku": "WGT-PRO-001",
    "description": "Professional grade widget",
    "category_id": 1,
    "base_price": 29.99,
    "cost_price": 15.00,
    "min_stock_level": 10,
    "is_active": true
  }'

# Transfer inventory
curl -X POST http://localhost:8000/api/v1/tenants/1/inventory/transfer \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "from_location_type": "warehouse",
    "from_location_id": 1,
    "to_location_type": "store",
    "to_location_id": 2,
    "quantity": 50,
    "notes": "Restocking downtown store"
  }'

# Fulfill order
curl -X POST http://localhost:8000/api/v1/tenants/1/orders/123/fulfill \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json"

# Get dashboard metrics
curl -X GET http://localhost:8000/api/v1/tenants/1/dashboard \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
```

---

## Webhooks

### Webhook Payload Format

When a webhook event is triggered, your endpoint will receive:

```json
{
  "event": "order.created",
  "timestamp": "2026-03-20T10:00:00+00:00",
  "data": {
    "order_id": 123,
    "order_number": "ORD-2026-0001",
    "customer_id": 456,
    "total_amount": 99.99,
    "status": "pending",
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "unit_price": 49.99
      }
    ]
  },
  "signature": "hmac-sha256-signature-here"
}
```

### Available Events

| Event | Description |
|-------|-------------|
| `order.created` | New order placed |
| `order.updated` | Order modified |
| `order.confirmed` | Order confirmed |
| `order.fulfilled` | Order fulfilled |
| `order.cancelled` | Order cancelled |
| `inventory.updated` | Inventory level changed |
| `inventory.low_stock` | Stock below threshold |
| `product.created` | New product added |
| `product.updated` | Product modified |
| `customer.created` | New customer registered |

### Verifying Webhook Signatures

Always verify webhook signatures to ensure requests are from our system:

**PHP Example:**
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$secret = 'your-webhook-secret';

$expectedSignature = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// Process webhook
$data = json_decode($payload, true);
// Handle $data['event'] and $data['data']
```

**Node.js Example:**
```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}

// In your webhook handler
app.post('/webhooks/orders', (req, res) => {
  const signature = req.headers['x-signature'];
  const payload = JSON.stringify(req.body);
  
  if (!verifyWebhook(payload, signature, WEBHOOK_SECRET)) {
    return res.status(403).json({ error: 'Invalid signature' });
  }
  
  // Process webhook
  const { event, data } = req.body;
  console.log(`Received event: ${event}`);
  
  res.status(200).json({ received: true });
});
```

**Python Example:**
```python
import hmac
import hashlib
from flask import Flask, request, abort

app = Flask(__name__)
WEBHOOK_SECRET = 'your-webhook-secret'

def verify_signature(payload, signature, secret):
    expected = hmac.new(
        secret.encode('utf-8'),
        payload.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    
    return hmac.compare_digest(signature, expected)

@app.route('/webhooks/orders', methods=['POST'])
def handle_webhook():
    payload = request.get_data(as_text=True)
    signature = request.headers.get('X-Signature', '')
    
    if not verify_signature(payload, signature, WEBHOOK_SECRET):
        abort(403)
    
    data = request.json
    event = data.get('event')
    print(f"Received event: {event}")
    
    return {'received': True}
```

### Webhook Response

Your webhook endpoint should return a `2xx` status code to acknowledge receipt:

```json
{
  "received": true
}
```

If we don't receive a successful response, we'll retry according to the webhook's retry configuration.

---

## Interactive Documentation

### Swagger UI

View interactive API documentation using the OpenAPI specification:

#### Option 1: Swagger Editor Online

1. Visit [Swagger Editor](https://editor.swagger.io/)
2. Upload or paste the contents of `swagger/openapi.yaml`

#### Option 2: Local Swagger UI with Docker

```bash
# From project root
docker run -d -p 8080:8080 \
  -e SWAGGER_JSON=/swagger/openapi.yaml \
  -v $(pwd)/swagger:/swagger \
  swaggerapi/swagger-ui
```

Then visit: `http://localhost:8080`

#### Option 3: VS Code Extension

Install the "Swagger Viewer" extension in VS Code and open `swagger/openapi.yaml`.

### Postman Collection

Import the Postman collection for easy API testing:

1. Open Postman
2. Click **Import**
3. Select `postman/Super_Admin_Collection.json`
4. Set environment variables:
   - `base_url`: `http://localhost:8000`
   - `token`: Your authentication token

---

## Support

For API support, please contact:
- **Email:** api-support@poswms.com
- **Documentation:** https://docs.poswms.com/api
- **Status Page:** https://status.poswms.com

---

## Changelog

### Version 1.0.0 (2026-03-20)
- Initial API release
- Multi-tenant architecture
- Complete CRUD for all entities
- Webhook system with signature verification
- Audit logging
- Multi-level pricing
- Order fulfillment workflow
- Inventory management with transfers
- Sales and inventory reporting
- Unified dashboard metrics
- Super Admin module for SaaS management
