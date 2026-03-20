# API Documentation

## Multi-Store & Warehouse Management System (MSWMS)

**Version:** 1.0.0  
**Base URL:** `http://localhost:8000/api/v1` (development)  
**OpenAPI Spec:** [`openapi.yaml`](./openapi.yaml)

---

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Multi-Tenancy](#multi-tenancy)
- [Rate Limiting](#rate-limiting)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Endpoints](#endpoints)
- [Webhooks](#webhooks)
- [Viewing Swagger UI](#viewing-swagger-ui)

---

## Overview

The MSWMS API is a RESTful API that allows you to manage stores, warehouses, inventory, orders, and more. All API access requires authentication via Laravel Sanctum.

### Available Resources

| Resource | Description |
|----------|-------------|
| **Authentication** | User login, logout, token refresh |
| **Stores** | Retail store locations |
| **Warehouses** | Storage facilities |
| **Products** | Product catalog |
| **Categories** | Product categorization |
| **Customers** | Customer management |
| **Inventory** | Stock tracking and transfers |
| **Orders** | Order processing and fulfillment |
| **Pricing** | Multi-level pricing tiers and rules |
| **Reports** | Sales and inventory reports |
| **Dashboard** | Unified metrics and KPIs |
| **Webhooks** | External system integrations |
| **Audit Logs** | Activity tracking |

---

## Authentication

### Getting a Token

Make a POST request to `/auth/login` with your credentials:

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
    "token": "1|abc123def456...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### Using the Token

Include the token in the `Authorization` header for all authenticated requests:

```bash
curl -X GET http://localhost:8000/api/v1/tenants/1/stores \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

### Token Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/auth/logout` | POST | Invalidate current token |
| `/auth/refresh` | POST | Generate new token |
| `/auth/me` | GET | Get current user info |

---

## Multi-Tenancy

All authenticated endpoints are scoped by tenant ID. The tenant ID must be included in the URL path:

```
/api/v1/tenants/{tenant_id}/{resource}
```

**Example:**
```bash
# Get stores for tenant 1
GET /api/v1/tenants/1/stores

# Get products for tenant 5
GET /api/v1/tenants/5/products
```

### Tenant Scoping

The `EnsureTenantIsScoped` middleware automatically ensures that all queries are scoped to the specified tenant, preventing cross-tenant data access.

---

## Rate Limiting

API endpoints are protected by rate limiters to ensure fair usage:

| Limiter | Requests/Minute | Applied To |
|---------|-----------------|------------|
| `auth` | 10 | Authentication endpoints |
| `api` | 60 | Standard API endpoints |
| `api-admin` | 120 | Admin-only endpoints |

When rate limited, you'll receive a `429 Too Many Requests` response:

```json
{
  "success": false,
  "message": "Too many requests. Please try again in 60 seconds."
}
```

---

## Response Format

All API responses follow a consistent JSON structure:

### Success Response

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Example Store",
    ...
  },
  "message": "Operation successful",
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
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
    "total": 200
  }
}
```

**Pagination Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)

---

## Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthenticated |
| 403 | Forbidden (Insufficient permissions) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Validation Errors

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "name": ["The name must be at least 3 characters."]
  }
}
```

---

## Endpoints

### Stores

#### List Stores
```http
GET /api/v1/tenants/{tenant_id}/stores
```

#### Create Store
```http
POST /api/v1/tenants/{tenant_id}/stores
Content-Type: application/json

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

#### Get Store
```http
GET /api/v1/tenants/{tenant_id}/stores/{storeId}
```

#### Update Store
```http
PUT /api/v1/tenants/{tenant_id}/stores/{storeId}
```

#### Delete Store
```http
DELETE /api/v1/tenants/{tenant_id}/stores/{storeId}
```

---

### Warehouses

#### List Warehouses
```http
GET /api/v1/tenants/{tenant_id}/warehouses
```

#### Create Warehouse
```http
POST /api/v1/tenants/{tenant_id}/warehouses
Content-Type: application/json

{
  "name": "Main Warehouse",
  "code": "MW-001",
  "address": "456 Industrial Blvd",
  "city": "Chicago",
  "state": "IL",
  "country": "USA",
  "postal_code": "60601",
  "is_active": true
}
```

---

### Products

#### List Products
```http
GET /api/v1/tenants/{tenant_id}/products
```

**Query Parameters:**
- `category_id` - Filter by category
- `search` - Search by name or SKU

#### Create Product
```http
POST /api/v1/tenants/{tenant_id}/products
Content-Type: application/json

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

---

### Inventory

#### Get Inventory Levels
```http
GET /api/v1/tenants/{tenant_id}/inventory
```

**Query Parameters:**
- `product_id` - Filter by product
- `warehouse_id` - Filter by warehouse
- `store_id` - Filter by store

#### Transfer Stock
```http
POST /api/v1/tenants/{tenant_id}/inventory/transfer
Content-Type: application/json

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

---

### Orders

#### List Orders
```http
GET /api/v1/tenants/{tenant_id}/orders
```

**Query Parameters:**
- `status` - Filter by status (pending, confirmed, fulfilling, completed, cancelled)
- `customer_id` - Filter by customer

#### Create Order
```http
POST /api/v1/tenants/{tenant_id}/orders
Content-Type: application/json

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

#### Fulfill Order
```http
POST /api/v1/tenants/{tenant_id}/orders/{orderId}/fulfill
```

#### Cancel Order
```http
POST /api/v1/tenants/{tenant_id}/orders/{orderId}/cancel
```

---

### Pricing

#### List Pricing Tiers
```http
GET /api/v1/tenants/{tenant_id}/pricing-tiers
```

#### Create Pricing Tier
```http
POST /api/v1/tenants/{tenant_id}/pricing-tiers
Content-Type: application/json

{
  "name": "Gold Tier",
  "slug": "gold",
  "description": "Best pricing for VIP customers",
  "discount_percentage": 15.0
}
```

#### Calculate Price
```http
POST /api/v1/tenants/{tenant_id}/prices/calculate
Content-Type: application/json

{
  "product_id": 1,
  "customer_id": 1,
  "quantity": 10
}
```

---

### Reports

#### Sales Revenue Report
```http
GET /api/v1/tenants/{tenant_id}/reports/sales/revenue
  ?start_date=2026-01-01
  &end_date=2026-03-31
  &group_by=month
```

#### Stock Levels Report
```http
GET /api/v1/tenants/{tenant_id}/reports/inventory/stock-levels
  ?warehouse_id=1
```

#### Low Stock Report
```http
GET /api/v1/tenants/{tenant_id}/reports/inventory/low-stock
```

---

### Dashboard

#### Get Dashboard Metrics
```http
GET /api/v1/tenants/{tenant_id}/dashboard
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sales": {
      "today_revenue": 1250.00,
      "month_revenue": 45000.00,
      "orders_count": 156
    },
    "inventory": {
      "total_products": 500,
      "low_stock_count": 12,
      "out_of_stock_count": 3
    },
    "orders": {
      "pending": 8,
      "fulfilling": 5,
      "completed_today": 23
    }
  }
}
```

---

### Webhooks

#### List Webhooks
```http
GET /api/v1/tenants/{tenant_id}/webhooks
```

#### Create Webhook
```http
POST /api/v1/tenants/{tenant_id}/webhooks
Content-Type: application/json

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

#### Test Webhook
```http
POST /api/v1/tenants/{tenant_id}/webhooks/{webhookId}/test
```

#### Get Delivery Attempts
```http
GET /api/v1/tenants/{tenant_id}/webhooks/{webhookId}/attempts
```

#### Retry Failed Deliveries
```http
POST /api/v1/tenants/{tenant_id}/webhooks/{webhookId}/retry
```

### Webhook Payload Format

When a webhook is triggered, your endpoint will receive:

```json
{
  "event": "order.created",
  "timestamp": "2026-03-20T10:00:00+00:00",
  "data": {
    "order_id": 123,
    "order_number": "ORD-2026-0001",
    "customer_id": 456,
    "total_amount": 99.99,
    ...
  },
  "signature": "hmac-sha256-signature-here"
}
```

### Verifying Webhook Signatures

```php
$payload = $request->all();
$signature = $request->header('X-Signature');
$secret = 'your-webhook-secret';

$expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

if (!hash_equals($expectedSignature, $signature)) {
    // Invalid signature
    abort(403);
}
```

---

### Audit Logs

#### List Audit Logs
```http
GET /api/v1/tenants/{tenant_id}/audit-logs
  ?event_type=created
  &user_id=1
  &start_date=2026-01-01
  &end_date=2026-03-31
```

#### Get Audit Log Entry
```http
GET /api/v1/tenants/{tenant_id}/audit-logs/{auditLogId}
```

#### Get Audit Summary
```http
GET /api/v1/tenants/{tenant_id}/audit-logs/summary
  ?start_date=2026-01-01
  &end_date=2026-03-31
```

---

## Viewing Swagger UI

To view interactive API documentation:

### Option 1: Using Swagger Editor Online

1. Visit [Swagger Editor](https://editor.swagger.io/)
2. Upload or paste the contents of `docs/openapi.yaml`

### Option 2: Local Swagger UI (Recommended)

Install Swagger UI locally:

```bash
# Using Docker
docker run -d -p 8080:8080 \
  -e SWAGGER_JSON=/openapi.yaml \
  -v $(pwd)/docs:/openapi.yaml \
  swaggerapi/swagger-ui
```

Then visit: `http://localhost:8080`

### Option 3: VS Code Extension

Install the "Swagger Viewer" extension in VS Code and open `docs/openapi.yaml`.

---

## SDK Examples

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

// Login
async function login(email, password) {
  const response = await api.post('/auth/login', { email, password });
  return response.data.data.token;
}

// Get stores
async function getStores(tenantId, token) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  const response = await api.get(`/tenants/${tenantId}/stores`);
  return response.data.data;
}

// Usage
const token = await login('admin@example.com', 'password123');
const stores = await getStores(1, token);
console.log(stores);
```

### PHP (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000/api/v1/',
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

// Login
$response = $client->post('auth/login', [
    'json' => [
        'email' => 'admin@example.com',
        'password' => 'password123'
    ]
]);

$data = json_decode($response->getBody(), true);
$token = $data['data']['token'];

// Get stores
$response = $client->get('tenants/1/stores', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token
    ]
]);

$stores = json_decode($response->getBody(), true);
```

### Python (Requests)

```python
import requests

BASE_URL = 'http://localhost:8000/api/v1'

# Login
response = requests.post(f'{BASE_URL}/auth/login', json={
    'email': 'admin@example.com',
    'password': 'password123'
})

token = response.json()['data']['token']
headers = {
    'Authorization': f'Bearer {token}',
    'Accept': 'application/json'
}

# Get stores
response = requests.get(f'{BASE_URL}/tenants/1/stores', headers=headers)
stores = response.json()['data']
print(stores)
```

---

## Support

For API support, please contact:
- **Email:** api-support@poswms.com
- **Documentation:** https://docs.poswms.com/api

---

## Changelog

### Version 1.0.0 (2026-03-20)
- Initial API release
- Multi-tenant architecture
- Complete CRUD for all entities
- Webhook system
- Audit logging
- Multi-level pricing
- Order fulfillment
- Inventory management
- Sales and inventory reporting
