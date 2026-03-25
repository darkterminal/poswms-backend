# Frontend API Integration Cheat Sheet

## Quick Reference for Multi-Store & Warehouse Management System

---

## Base URLs

| Environment | URL |
|-------------|-----|
| Development | `http://localhost:8000/api/v1` |
| Production | `https://your-domain.com/api/v1` |

---

## Authentication

### Tenant User Login
```javascript
POST /api/v1/auth/login
{
  "email": "user@store.com",
  "password": "password"
}

// Response
{
  "success": true,
  "data": {
    "user": { ... },
    "token": "Bearer_TOKEN_HERE",
    "token_type": "Bearer"
  }
}
```

### Super Admin Login
```javascript
POST /api/v1/admin/auth/login
// Same request/response format, different endpoint
```

### Auth Headers
```javascript
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
Accept: application/json
```

---

## URL Structure

**Tenant Routes:**
```
/api/v1/tenants/{tenant_id}/{resource}
```

**Super Admin Routes:**
```
/api/v1/admin/{resource}
```

---

## Standard Response Format

### Success
```json
{
  "success": true,
  "data": { ... },
  "message": "Success message",
  "meta": { ... }
}
```

### Error
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error message",
    "details": { ... }
  }
}
```

---

## HTTP Status Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | OK | Success |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Login required |
| 403 | Forbidden | No permission |
| 404 | Not Found | Doesn't exist |
| 422 | Validation Error | Fix form data |
| 429 | Rate Limited | Wait & retry |
| 500 | Server Error | Contact support |

---

## Core Endpoints

### Stores
```
GET    /tenants/{id}/stores
POST   /tenants/{id}/stores
GET    /tenants/{id}/stores/{storeId}
PUT    /tenants/{id}/stores/{storeId}
DELETE /tenants/{id}/stores/{storeId}
```

### Warehouses
```
GET    /tenants/{id}/warehouses
POST   /tenants/{id}/warehouses
GET    /tenants/{id}/warehouses/{warehouseId}
PUT    /tenants/{id}/warehouses/{warehouseId}
DELETE /tenants/{id}/warehouses/{warehouseId}
```

### Products
```
GET    /tenants/{id}/products?search=keyword&page=1&per_page=15
POST   /tenants/{id}/products
GET    /tenants/{id}/products/{productId}
PUT    /tenants/{id}/products/{productId}
DELETE /tenants/{id}/products/{productId}
```

### Inventory
```
GET    /tenants/{id}/inventory
POST   /tenants/{id}/inventory/transfer
GET    /tenants/{id}/inventory/product/{productId}/transferable
```

### Orders
```
GET    /tenants/{id}/orders?status=pending&page=1
POST   /tenants/{id}/orders
GET    /tenants/{id}/orders/{orderId}
POST   /tenants/{id}/orders/{orderId}/confirm
POST   /tenants/{id}/orders/{orderId}/fulfill
POST   /tenants/{id}/orders/{orderId}/cancel
```

### Customers
```
GET    /tenants/{id}/customers
POST   /tenants/{id}/customers
GET    /tenants/{id}/customers/{customerId}
PUT    /tenants/{id}/customers/{customerId}
DELETE /tenants/{id}/customers/{customerId}
```

### Dashboard
```
GET    /tenants/{id}/dashboard
GET    /tenants/{id}/reports/sales/revenue
GET    /tenants/{id}/reports/inventory/low-stock
```

---

## Super Admin Endpoints

### Tenant Management
```
GET    /admin/tenants?search=keyword&status=active
POST   /admin/tenants
GET    /admin/tenants/{id}
PUT    /admin/tenants/{id}
DELETE /admin/tenants/{id}
POST   /admin/tenants/{id}/activate
POST   /admin/tenants/{id}/suspend
GET    /admin/tenants/{id}/stats
```

### Dashboard
```
GET    /admin/dashboard
GET    /admin/dashboard/revenue
GET    /admin/dashboard/usage
GET    /admin/dashboard/alerts
```

### Users
```
GET    /admin/users?role=admin&search=name
POST   /admin/users/{id}/impersonate
POST   /admin/users/stop-impersonating
```

### Audit Logs
```
GET    /admin/audit-logs
GET    /admin/audit-logs/summary
GET    /admin/audit-logs/by-user/{userId}
```

---

## Rate Limits

| Endpoint Type | Limit |
|--------------|-------|
| Login | 10/min |
| Tenant API | 100/min |
| Super Admin API | 200/min |
| Exports | 10/min |

---

## Common Request Bodies

### Create Store
```json
{
  "name": "Downtown Store",
  "code": "DT-001",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "phone": "+1234567890",
  "email": "downtown@store.com",
  "active": true
}
```

### Create Product
```json
{
  "sku": "PROD-001",
  "name": "Wireless Headphones",
  "description": "High-quality headphones",
  "category_id": "uuid",
  "base_price": 99.99,
  "cost_price": 50.00,
  "is_active": true
}
```

### Create Order
```json
{
  "store_id": "uuid",
  "customer_id": "uuid",
  "warehouse_id": "uuid",
  "type": "sale",
  "status": "pending",
  "items": [
    {
      "product_id": "uuid",
      "quantity": 2,
      "unit_price": 99.99
    }
  ],
  "tax": 19.99,
  "discount": 0,
  "shipping": 5.00
}
```

### Inventory Transfer
```json
{
  "product_id": "uuid",
  "from_location_type": "warehouse",
  "from_location_id": "uuid",
  "to_location_type": "store",
  "to_location_id": "uuid",
  "quantity": 10
}
```

### Create Tenant (Super Admin)
```json
{
  "name": "Business Name",
  "slug": "business-name",
  "company_name": "Business Corp",
  "email": "contact@business.com",
  "phone": "+1234567890",
  "address": "123 Business St",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "timezone": "America/New_York",
  "currency": "USD",
  "status": "active",
  "trial_ends_at": "2024-02-01T00:00:00Z"
}
```

---

## JavaScript Service Template

```javascript
// services/api.service.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

## React Hook Example

```javascript
// hooks/useApi.js
import { useState, useEffect } from 'react';
import api from '../services/api.service';

export function useApi(endpoint, tenantId) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetch = async (params = {}) => {
    setLoading(true);
    try {
      const response = await api.get(`/tenants/${tenantId}/${endpoint}`, { params });
      setData(response.data);
      return response.data;
    } catch (err) {
      setError(err);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const post = async (payload) => {
    setLoading(true);
    try {
      const response = await api.post(`/tenants/${tenantId}/${endpoint}`, payload);
      return response.data;
    } catch (err) {
      setError(err);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return { data, loading, error, fetch, post };
}
```

---

## Vue Composable Example

```javascript
// composables/useApi.js
import { ref } from 'vue';
import api from '@/services/api.service';

export function useApi(endpoint, tenantId) {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);

  const fetch = async (params = {}) => {
    loading.value = true;
    try {
      const response = await api.get(`/tenants/${tenantId.value}/${endpoint}`, { params });
      data.value = response.data;
      return response.data;
    } catch (err) {
      error.value = err;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return { data, loading, error, fetch };
}
```

---

## Error Handling Pattern

```javascript
try {
  const response = await api.post('/tenants/' + tenantId + '/products', formData);
  // Handle success
} catch (error) {
  if (error.response?.status === 422) {
    // Validation errors
    const errors = error.response.data.error?.details || {};
    Object.entries(errors).forEach(([field, messages]) => {
      showFieldError(field, messages[0]);
    });
  } else if (error.response?.status === 401) {
    // Unauthorized
    logout();
  } else if (error.response?.status === 429) {
    // Rate limited
    const retryAfter = error.response.headers['retry-after'] || 60;
    showMessage(`Please wait ${retryAfter} seconds`);
  } else {
    // Generic error
    showMessage('An error occurred');
  }
}
```

---

## Pagination Pattern

```javascript
// Response includes pagination
{
  "data": {
    "products": [...],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7,
      "has_more": true
    }
  }
}

// Request with pagination
GET /tenants/{id}/products?page=2&per_page=15
```

---

## File Download Pattern

```javascript
// Download export report
const downloadReport = async (tenantId, reportType) => {
  const response = await api.get(
    `/tenants/${tenantId}/reports/inventory/export/${reportType}`,
    { responseType: 'blob' }
  );
  
  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', `${reportType}-${Date.now()}.csv`);
  document.body.appendChild(link);
  link.click();
  link.remove();
};
```

---

## Query Parameters

### Common Parameters
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)
- `search` - Search keyword
- `sort_by` - Field to sort by
- `sort_order` - asc or desc
- `status` - Filter by status
- `active` - Filter by active status

### Example
```
GET /tenants/{id}/products?search=headphones&active=true&page=1&per_page=15&sort_by=name&sort_order=asc
```

---

## Security Notes

1. **Store tokens securely** - Use HTTP-only cookies in production
2. **Refresh tokens** - Implement token refresh before expiration
3. **Logout on 401** - Clear tokens and redirect to login
4. **Validate input** - Always validate before sending to API
5. **Handle errors** - Show user-friendly error messages
6. **Rate limiting** - Implement retry logic with backoff

---

## Testing

### Test Credentials (Development)
```
Tenant User:
Email: user@store.com
Password: password

Super Admin:
Email: admin@platform.com
Password: password
```

### Test Tenant ID
```
550e8400-e29b-41d4-a716-446655440000
```

---

## Useful Links

- [Full Integration Guide](./FRONTEND_INTEGRATION_GUIDE.md)
- [API Design Document](../../API_DESIGN.md)
- [OpenAPI Spec](http://localhost:8000/api/v1/docs/openapi.json)

---

**Quick Start:**
1. Login to get token
2. Store token in localStorage
3. Add token to Authorization header
4. Make requests to tenant-scoped endpoints
5. Handle errors gracefully

**Remember:** All tenant routes require `{tenant_id}` in the URL path!
