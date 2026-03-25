# Frontend Integration Guide: Multi-Store & Warehouse Management System API

## Document Information

- **Version**: 1.0
- **Last Updated**: March 25, 2026
- **Audience**: Frontend Developers
- **API Version**: v1
- **Base URL**: `http://localhost:8000/api/v1` (development)

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [API Architecture Overview](#api-architecture-overview)
3. [Authentication](#authentication)
4. [Standard Response Format](#standard-response-format)
5. [Error Handling](#error-handling)
6. [Core Modules Integration](#core-modules-integration)
7. [Super Admin Module](#super-admin-module)
8. [Rate Limiting](#rate-limiting)
9. [Best Practices](#best-practices)
10. [Code Examples](#code-examples)
11. [API Endpoints Reference](#api-endpoints-reference)

---

## Quick Start

### 1. Environment Configuration

```javascript
// config/api.js
export const API_CONFIG = {
  baseURL: 'http://localhost:8000/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
};
```

### 2. Setup HTTP Client

```javascript
// services/api.js
import axios from 'axios';
import { API_CONFIG } from '../config/api';

const apiClient = axios.create({
  baseURL: API_CONFIG.baseURL,
  timeout: API_CONFIG.timeout,
  headers: API_CONFIG.headers,
});

// Request interceptor - Add auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor - Handle errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized - redirect to login
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### 3. Authentication Service

```javascript
// services/auth.service.js
import apiClient from './api';

export const authService = {
  async login(email, password) {
    const response = await apiClient.post('/auth/login', {
      email,
      password,
    });
    
    if (response.data.success) {
      localStorage.setItem('auth_token', response.data.data.token);
      localStorage.setItem('user', JSON.stringify(response.data.data.user));
    }
    
    return response.data;
  },

  async logout() {
    await apiClient.post('/auth/logout');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
  },

  async getCurrentUser() {
    const response = await apiClient.get('/auth/me');
    return response.data.data.user;
  },

  getToken() {
    return localStorage.getItem('auth_token');
  },

  getUser() {
    return JSON.parse(localStorage.getItem('user'));
  },
};
```

---

## API Architecture Overview

### Multi-Tenant Structure

This is a **multi-tenant SaaS** application. All tenant-scoped operations require:
1. **Authentication token** (Bearer)
2. **Tenant ID** in the URL path

**URL Structure:**
```
/api/v1/tenants/{tenant_id}/{resource}
```

**Example:**
```
GET /api/v1/tenants/550e8400-e29b-41d4-a716-446655440000/stores
```

### Two Authentication Types

| Type | Endpoint Prefix | Purpose | Rate Limit |
|------|----------------|---------|------------|
| **Tenant User** | `/api/v1/tenants/{id}/` | Regular users (store managers, warehouse staff) | 100 req/min |
| **Super Admin** | `/api/v1/admin/` | Platform administrators | 200 req/min |

---

## Authentication

### Login Flow

**Endpoint:** `POST /api/v1/auth/login`

**Request:**
```javascript
{
  "email": "user@store.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "tenant_id": "uuid",
      "name": "John Doe",
      "email": "user@store.com",
      "role": "store_manager",
      "store_id": "uuid",
      "is_active": true
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

**Security Features:**
- Account lockout after multiple failed attempts
- Progressive delay between attempts
- Suspicious login detection
- Token expiration (24 hours by default)

### Token Management

```javascript
// utils/token-manager.js
export const tokenManager = {
  setToken(token) {
    localStorage.setItem('auth_token', token);
  },

  getToken() {
    return localStorage.getItem('auth_token');
  },

  removeToken() {
    localStorage.removeItem('auth_token');
  },

  isTokenExpired(token) {
    // Implement JWT expiration check if needed
    // Sanctum tokens are database-backed, so server validates
    return false;
  },

  async refreshToken() {
    const response = await apiClient.post('/auth/refresh');
    const newToken = response.data.data.token;
    this.setToken(newToken);
    return newToken;
  },
};
```

### Super Admin Login

**Endpoint:** `POST /api/v1/admin/auth/login`

**Note:** Super Admin uses a **separate authentication guard** and does **not** require tenant ID in URLs.

---

## Standard Response Format

All API responses follow a consistent structure:

### Success Response

```json
{
  "success": true,
  "data": {
    // Resource data here
  },
  "message": "Operation successful",
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z",
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7,
      "has_more": true
    }
  }
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
      "name": ["The name must be a string."]
    }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

### HTTP Status Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Duplicate resource |
| 422 | Unprocessable | Validation errors |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal error |

---

## Error Handling

### Global Error Handler

```javascript
// services/error-handler.service.js
export const errorHandler = {
  handle(error) {
    if (!error.response) {
      // Network error
      return {
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Unable to connect to server. Please check your internet connection.',
        },
      };
    }

    const { status, data } = error.response;

    switch (status) {
      case 401:
        return this.handleUnauthorized(error);
      case 403:
        return this.handleForbidden(error);
      case 404:
        return this.handleNotFound(error);
      case 422:
        return this.handleValidation(error);
      case 429:
        return this.handleRateLimit(error);
      default:
        return this.handleServer(error);
    }
  },

  handleValidation(error) {
    return {
      success: false,
      error: {
        code: 'VALIDATION_ERROR',
        message: error.response.data.error?.message || 'Validation failed',
        details: error.response.data.error?.details || {},
      },
    };
  },

  handleRateLimit(error) {
    const retryAfter = error.response.headers['retry-after'] || 60;
    return {
      success: false,
      error: {
        code: 'RATE_LIMIT_EXCEEDED',
        message: `Too many requests. Please wait ${retryAfter} seconds.`,
        retryAfter: parseInt(retryAfter),
      },
    };
  },

  handleUnauthorized(error) {
    // Clear auth and redirect
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    window.location.href = '/login';
    return { success: false, error: { code: 'UNAUTHORIZED', message: 'Session expired' } };
  },

  handleForbidden(error) {
    return { success: false, error: { code: 'FORBIDDEN', message: 'You do not have permission to perform this action' } };
  },

  handleNotFound(error) {
    return { success: false, error: { code: 'NOT_FOUND', message: 'The requested resource was not found' } };
  },

  handleServer(error) {
    return { success: false, error: { code: 'SERVER_ERROR', message: 'An unexpected error occurred' } };
  },
};
```

### Form Validation Errors

```javascript
// Example: Handling validation errors in forms
async function handleSubmit(formData) {
  try {
    const response = await apiClient.post('/tenants/' + tenantId + '/products', formData);
    // Handle success
  } catch (error) {
    if (error.response?.status === 422) {
      const errors = error.response.data.error?.details || {};
      
      // Display field-specific errors
      Object.entries(errors).forEach(([field, messages]) => {
        showFieldError(field, messages[0]);
      });
    }
  }
}
```

---

## Core Modules Integration

### 1. Store Management

**Base URL:** `/api/v1/tenants/{tenant_id}/stores`

#### List All Stores

```javascript
// services/store.service.js
export const storeService = {
  async getAll(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/stores`, { params });
    return response.data.data.stores;
  },

  async getById(tenantId, storeId) {
    const response = await apiClient.get(`/tenants/${tenantId}/stores/${storeId}`);
    return response.data.data.store;
  },

  async create(tenantId, storeData) {
    const response = await apiClient.post(`/tenants/${tenantId}/stores`, storeData);
    return response.data;
  },

  async update(tenantId, storeId, storeData) {
    const response = await apiClient.put(`/tenants/${tenantId}/stores/${storeId}`, storeData);
    return response.data;
  },

  async delete(tenantId, storeId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/stores/${storeId}`);
    return response.data;
  },
};
```

**Store Object Structure:**
```json
{
  "id": "uuid",
  "tenant_id": "uuid",
  "name": "Downtown Store",
  "code": "DT-001",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "phone": "+1234567890",
  "email": "downtown@store.com",
  "manager_id": "uuid",
  "settings": {},
  "active": true,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### 2. Warehouse Management

**Base URL:** `/api/v1/tenants/{tenant_id}/warehouses`

```javascript
// services/warehouse.service.js
export const warehouseService = {
  async getAll(tenantId) {
    const response = await apiClient.get(`/tenants/${tenantId}/warehouses`);
    return response.data.data.warehouses;
  },

  async getById(tenantId, warehouseId) {
    const response = await apiClient.get(`/tenants/${tenantId}/warehouses/${warehouseId}`);
    return response.data.data.warehouse;
  },

  async create(tenantId, warehouseData) {
    const response = await apiClient.post(`/tenants/${tenantId}/warehouses`, warehouseData);
    return response.data;
  },

  async update(tenantId, warehouseId, warehouseData) {
    const response = await apiClient.put(`/tenants/${tenantId}/warehouses/${warehouseId}`, warehouseData);
    return response.data;
  },

  async delete(tenantId, warehouseId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/warehouses/${warehouseId}`);
    return response.data;
  },
};
```

### 3. Product Management

**Base URL:** `/api/v1/tenants/{tenant_id}/products`

**Query Parameters:**
- `search` - Search by name or SKU
- `category_id` - Filter by category
- `active` - Filter by active status
- `per_page` - Items per page (default: 15)
- `page` - Page number

```javascript
// services/product.service.js
export const productService = {
  async getAll(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/products`, { params });
    return response.data;
  },

  async getById(tenantId, productId) {
    const response = await apiClient.get(`/tenants/${tenantId}/products/${productId}`);
    return response.data.data.product;
  },

  async create(tenantId, productData) {
    const response = await apiClient.post(`/tenants/${tenantId}/products`, productData);
    return response.data;
  },

  async update(tenantId, productId, productData) {
    const response = await apiClient.put(`/tenants/${tenantId}/products/${productId}`, productData);
    return response.data;
  },

  async delete(tenantId, productId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/products/${productId}`);
    return response.data;
  },
};
```

**Product Object:**
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
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### 4. Inventory Management

**Base URL:** `/api/v1/tenants/{tenant_id}/inventory`

#### Get Inventory by Location

```javascript
// services/inventory.service.js
export const inventoryService = {
  // Get all inventory
  async getAll(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/inventory`, { params });
    return response.data.data.inventory;
  },

  // Get warehouse inventory
  async getWarehouseInventory(tenantId, warehouseId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/warehouses/${warehouseId}/inventory`, { params });
    return response.data.data.inventory;
  },

  // Get store inventory
  async getStoreInventory(tenantId, storeId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/stores/${storeId}/inventory`, { params });
    return response.data.data.inventory;
  },

  // Transfer inventory
  async transfer(tenantId, transferData) {
    const response = await apiClient.post(`/tenants/${tenantId}/inventory/transfer`, transferData);
    return response.data;
  },

  // Get transferable inventory
  async getTransferable(tenantId, productId) {
    const response = await apiClient.get(`/tenants/${tenantId}/inventory/product/${productId}/transferable`);
    return response.data.data;
  },
};
```

**Inventory Transfer Request:**
```javascript
{
  "product_id": "uuid",
  "from_location_type": "warehouse", // or "store"
  "from_location_id": "uuid",
  "to_location_type": "store", // or "warehouse"
  "to_location_id": "uuid",
  "quantity": 10
}
```

### 5. Order Management

**Base URL:** `/api/v1/tenants/{tenant_id}/orders`

```javascript
// services/order.service.js
export const orderService = {
  async getAll(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/orders`, { params });
    return response.data;
  },

  async getById(tenantId, orderId) {
    const response = await apiClient.get(`/tenants/${tenantId}/orders/${orderId}`);
    return response.data.data.order;
  },

  async create(tenantId, orderData) {
    const response = await apiClient.post(`/tenants/${tenantId}/orders`, orderData);
    return response.data;
  },

  async update(tenantId, orderId, orderData) {
    const response = await apiClient.put(`/tenants/${tenantId}/orders/${orderId}`, orderData);
    return response.data;
  },

  async delete(tenantId, orderId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/orders/${orderId}`);
    return response.data;
  },

  // Order actions
  async confirm(tenantId, orderId) {
    const response = await apiClient.post(`/tenants/${tenantId}/orders/${orderId}/confirm`);
    return response.data;
  },

  async fulfill(tenantId, orderId) {
    const response = await apiClient.post(`/tenants/${tenantId}/orders/${orderId}/fulfill`);
    return response.data;
  },

  async cancel(tenantId, orderId) {
    const response = await apiClient.post(`/tenants/${tenantId}/orders/${orderId}/cancel`);
    return response.data;
  },
};
```

**Create Order Request:**
```javascript
{
  "store_id": "uuid",
  "customer_id": "uuid", // optional
  "warehouse_id": "uuid", // optional
  "type": "sale", // sale, return, exchange
  "status": "pending", // pending, confirmed, fulfilled, cancelled
  "items": [
    {
      "product_id": "uuid",
      "quantity": 2,
      "unit_price": 99.99
    }
  ],
  "tax": 19.99,
  "discount": 0,
  "shipping": 5.00,
  "notes": "Customer notes"
}
```

### 6. Customer Management

**Base URL:** `/api/v1/tenants/{tenant_id}/customers`

```javascript
// services/customer.service.js
export const customerService = {
  async getAll(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/customers`, { params });
    return response.data.data.customers;
  },

  async getById(tenantId, customerId) {
    const response = await apiClient.get(`/tenants/${tenantId}/customers/${customerId}`);
    return response.data.data.customer;
  },

  async create(tenantId, customerData) {
    const response = await apiClient.post(`/tenants/${tenantId}/customers`, customerData);
    return response.data;
  },

  async update(tenantId, customerId, customerData) {
    const response = await apiClient.put(`/tenants/${tenantId}/customers/${customerId}`, customerData);
    return response.data;
  },

  async delete(tenantId, customerId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/customers/${customerId}`);
    return response.data;
  },
};
```

### 7. Category Management

**Base URL:** `/api/v1/tenants/{tenant_id}/categories`

```javascript
// services/category.service.js
export const categoryService = {
  async getAll(tenantId) {
    const response = await apiClient.get(`/tenants/${tenantId}/categories`);
    return response.data.data.categories;
  },

  async getById(tenantId, categoryId) {
    const response = await apiClient.get(`/tenants/${tenantId}/categories/${categoryId}`);
    return response.data.data.category;
  },

  async create(tenantId, categoryData) {
    const response = await apiClient.post(`/tenants/${tenantId}/categories`, categoryData);
    return response.data;
  },

  async update(tenantId, categoryId, categoryData) {
    const response = await apiClient.put(`/tenants/${tenantId}/categories/${categoryId}`, categoryData);
    return response.data;
  },

  async delete(tenantId, categoryId) {
    const response = await apiClient.delete(`/tenants/${tenantId}/categories/${categoryId}`);
    return response.data;
  },
};
```

### 8. Reports & Analytics

#### Dashboard Metrics

```javascript
// services/dashboard.service.js
export const dashboardService = {
  async getMetrics(tenantId) {
    const response = await apiClient.get(`/tenants/${tenantId}/dashboard`);
    return response.data.data;
  },
};
```

#### Inventory Reports

```javascript
// services/inventory-report.service.js
export const inventoryReportService = {
  async lowStock(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/inventory/low-stock`, { params });
    return response.data.data;
  },

  async stockLevels(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/inventory/stock-levels`, { params });
    return response.data.data;
  },

  async movements(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/inventory/movements`, { params });
    return response.data.data;
  },

  // Export reports (admin only)
  async exportStockLevels(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/inventory/export/stock-levels`, {
      params,
      responseType: 'blob', // For file download
    });
    return response.data;
  },
};
```

#### Sales Reports

```javascript
// services/sales-report.service.js
export const salesReportService = {
  async revenue(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/sales/revenue`, { params });
    return response.data.data;
  },

  async ordersByPeriod(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/sales/orders-by-period`, { params });
    return response.data.data;
  },

  async topProducts(tenantId, params = {}) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/sales/top-products`, { params });
    return response.data.data;
  },

  async dashboardMetrics(tenantId) {
    const response = await apiClient.get(`/tenants/${tenantId}/reports/sales/dashboard`);
    return response.data.data;
  },
};
```

---

## Super Admin Module

### Overview

The Super Admin Module operates **outside tenant scope** and uses a **separate authentication guard**. All endpoints are prefixed with `/api/v1/admin/`.

### Authentication

```javascript
// services/super-admin-auth.service.js
export const superAdminAuthService = {
  async login(email, password) {
    const response = await apiClient.post('/admin/auth/login', {
      email,
      password,
    });
    
    if (response.data.success) {
      localStorage.setItem('admin_token', response.data.data.token);
      localStorage.setItem('admin_user', JSON.stringify(response.data.data.user));
    }
    
    return response.data;
  },

  async logout() {
    await apiClient.post('/admin/auth/logout');
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
  },

  async getCurrentUser() {
    const response = await apiClient.get('/admin/auth/me');
    return response.data.data.user;
  },

  getToken() {
    return localStorage.getItem('admin_token');
  },
};
```

### Tenant Management

```javascript
// services/admin/tenant.service.js
export const adminTenantService = {
  async getAll(params = {}) {
    const response = await apiClient.get('/admin/tenants', { params });
    return response.data;
  },

  async getById(tenantId) {
    const response = await apiClient.get(`/admin/tenants/${tenantId}`);
    return response.data.data.tenant;
  },

  async create(tenantData) {
    const response = await apiClient.post('/admin/tenants', tenantData);
    return response.data;
  },

  async update(tenantId, tenantData) {
    const response = await apiClient.put(`/admin/tenants/${tenantId}`, tenantData);
    return response.data;
  },

  async delete(tenantId) {
    const response = await apiClient.delete(`/admin/tenants/${tenantId}`);
    return response.data;
  },

  async activate(tenantId) {
    const response = await apiClient.post(`/admin/tenants/${tenantId}/activate`);
    return response.data;
  },

  async suspend(tenantId) {
    const response = await apiClient.post(`/admin/tenants/${tenantId}/suspend`);
    return response.data;
  },

  async getStats(tenantId) {
    const response = await apiClient.get(`/admin/tenants/${tenantId}/stats`);
    return response.data.data.stats;
  },

  // Subscription management
  async updateTrial(tenantId, trialEndsAt) {
    const response = await apiClient.post(`/admin/tenants/${tenantId}/trial`, {
      trial_ends_at: trialEndsAt,
    });
    return response.data;
  },

  async extendTrial(tenantId, days) {
    const response = await apiClient.post(`/admin/tenants/${tenantId}/trial/extend`, { days });
    return response.data;
  },

  async convertToPaid(tenantId, subscriptionEndsAt) {
    const response = await apiClient.post(`/admin/tenants/${tenantId}/convert-to-paid`, {
      subscription_ends_at: subscriptionEndsAt,
    });
    return response.data;
  },
};
```

### System Dashboard

```javascript
// services/admin/dashboard.service.js
export const adminDashboardService = {
  async getOverview() {
    const response = await apiClient.get('/admin/dashboard');
    return response.data.data;
  },

  async getRevenue() {
    const response = await apiClient.get('/admin/dashboard/revenue');
    return response.data.data;
  },

  async getUsage() {
    const response = await apiClient.get('/admin/dashboard/usage');
    return response.data.data;
  },

  async getAlerts() {
    const response = await apiClient.get('/admin/dashboard/alerts');
    return response.data.data;
  },
};
```

**Dashboard Response Example:**
```json
{
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
```

### User Management

```javascript
// services/admin/user.service.js
export const adminUserService = {
  async getAll(params = {}) {
    const response = await apiClient.get('/admin/users', { params });
    return response.data;
  },

  async getById(userId) {
    const response = await apiClient.get(`/admin/users/${userId}`);
    return response.data.data.user;
  },

  // Impersonation
  async impersonate(userId) {
    const response = await apiClient.post(`/admin/users/${userId}/impersonate`);
    return response.data;
  },

  async stopImpersonating() {
    const response = await apiClient.post('/admin/users/stop-impersonating');
    return response.data;
  },

  async getImpersonationSessions(userId) {
    const response = await apiClient.get(`/admin/users/${userId}/impersonation-sessions`);
    return response.data.data;
  },

  async revokeImpersonation(userId) {
    const response = await apiClient.post(`/admin/users/${userId}/revoke-impersonation`);
    return response.data;
  },
};
```

### Audit Logs

```javascript
// services/admin/audit-log.service.js
export const adminAuditLogService = {
  async getAll(params = {}) {
    const response = await apiClient.get('/admin/audit-logs', { params });
    return response.data;
  },

  async getSummary() {
    const response = await apiClient.get('/admin/audit-logs/summary');
    return response.data.data;
  },

  async getByUser(userId, params = {}) {
    const response = await apiClient.get(`/admin/audit-logs/by-user/${userId}`, { params });
    return response.data;
  },
};
```

### System Settings

```javascript
// services/admin/settings.service.js
export const adminSettingsService = {
  async get() {
    const response = await apiClient.get('/admin/settings');
    return response.data.data;
  },

  async update(settingsData) {
    const response = await apiClient.put('/admin/settings', settingsData);
    return response.data;
  },

  async clearCache() {
    const response = await apiClient.post('/admin/settings/clear-cache');
    return response.data;
  },

  async getHealth() {
    const response = await apiClient.get('/admin/settings/health');
    return response.data.data;
  },
};
```

---

## Rate Limiting

### Rate Limits by Endpoint Type

| Endpoint Type | Limit | Header |
|--------------|-------|--------|
| Auth (login) | 10/min | `X-RateLimit-Limit` |
| Tenant API | 100/min | `X-RateLimit-Limit` |
| Super Admin API | 200/min | `X-RateLimit-Limit` |
| Export Reports | 10/min | `X-RateLimit-Limit` |
| Webhook Test | 5/min | `X-RateLimit-Limit` |

### Handling Rate Limits

```javascript
// Rate limit handling in interceptor
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 429) {
      const retryAfter = error.response.headers['retry-after'] || 60;
      
      // Show user-friendly message
      showToast(
        'warning',
        `Too many requests. Please wait ${retryAfter} seconds.`,
        { duration: retryAfter * 1000 }
      );
      
      // Optionally queue the request
      return queueRequest(error.config, retryAfter);
    }
    
    return Promise.reject(error);
  }
);
```

---

## Best Practices

### 1. Token Storage

**Recommended:**
- Use `localStorage` for development
- Use secure HTTP-only cookies for production
- Implement token refresh before expiration

```javascript
// Production-ready token management
export const secureTokenManager = {
  setToken(token, rememberMe = false) {
    if (rememberMe) {
      localStorage.setItem('auth_token', token);
    } else {
      sessionStorage.setItem('auth_token', token);
    }
  },

  getToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
  },

  removeToken() {
    localStorage.removeItem('auth_token');
    sessionStorage.removeItem('auth_token');
  },
};
```

### 2. Request Debouncing

```javascript
// Debounce search requests
import { debounce } from 'lodash';

const searchProducts = debounce(async (searchTerm) => {
  const results = await productService.getAll(tenantId, { search: searchTerm });
  return results;
}, 300);
```

### 3. Pagination Handling

```javascript
// Pagination helper
export const paginationHelper = {
  currentPage: 1,
  perPage: 15,
  total: 0,
  lastPage: 1,

  setPagination(pagination) {
    this.currentPage = pagination.current_page;
    this.perPage = pagination.per_page;
    this.total = pagination.total;
    this.lastPage = pagination.last_page;
  },

  nextPage() {
    if (this.currentPage < this.lastPage) {
      this.currentPage++;
    }
  },

  prevPage() {
    if (this.currentPage > 1) {
      this.currentPage--;
    }
  },

  goToPage(page) {
    if (page >= 1 && page <= this.lastPage) {
      this.currentPage = page;
    }
  },
};
```

### 4. Caching Strategy

```javascript
// Simple cache implementation
const apiCache = {
  cache: new Map(),
  ttl: 5 * 60 * 1000, // 5 minutes

  get(key) {
    const item = this.cache.get(key);
    if (item && Date.now() - item.timestamp < this.ttl) {
      return item.data;
    }
    this.cache.delete(key);
    return null;
  },

  set(key, data) {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
    });
  },

  invalidate(key) {
    this.cache.delete(key);
  },

  clear() {
    this.cache.clear();
  },
};

// Usage
async function getProducts(tenantId) {
  const cacheKey = `products_${tenantId}`;
  const cached = apiCache.get(cacheKey);
  
  if (cached) {
    return cached;
  }
  
  const data = await productService.getAll(tenantId);
  apiCache.set(cacheKey, data);
  return data;
}
```

### 5. Optimistic Updates

```javascript
// Optimistic UI update pattern
async function deleteProduct(tenantId, productId) {
  const previousProducts = [...products];
  
  // Optimistically update UI
  setProducts(products.filter(p => p.id !== productId));
  
  try {
    await productService.delete(tenantId, productId);
  } catch (error) {
    // Rollback on error
    setProducts(previousProducts);
    showToast('error', 'Failed to delete product');
  }
}
```

### 6. File Downloads

```javascript
// Download export reports
async function downloadReport(tenantId, reportType, params = {}) {
  try {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/export/${reportType}`,
      {
        params,
        responseType: 'blob',
      }
    );
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${reportType}-${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    showToast('error', 'Failed to download report');
  }
}
```

---

## Code Examples

### React Hook Example

```javascript
// hooks/useProducts.js
import { useState, useEffect, useCallback } from 'react';
import { productService } from '../services/product.service';

export function useProducts(tenantId) {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(null);

  const fetchProducts = useCallback(async (params = {}) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await productService.getAll(tenantId, params);
      setProducts(response.data.products);
      setPagination(response.data.pagination);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [tenantId]);

  useEffect(() => {
    if (tenantId) {
      fetchProducts();
    }
  }, [tenantId, fetchProducts]);

  return {
    products,
    loading,
    error,
    pagination,
    refetch: fetchProducts,
  };
}

// Usage in component
function ProductList() {
  const { products, loading, error, pagination } = useProducts(tenantId);
  
  if (loading) return <Spinner />;
  if (error) return <Error message={error} />;
  
  return (
    <div>
      {products.map(product => (
        <ProductCard key={product.id} product={product} />
      ))}
      <Pagination {...pagination} />
    </div>
  );
}
```

### Vue Composable Example

```javascript
// composables/useOrders.js
import { ref, computed } from 'vue';
import { orderService } from '@/services/order.service';

export function useOrders(tenantId) {
  const orders = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const pagination = ref(null);

  const fetchOrders = async (params = {}) => {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await orderService.getAll(tenantId.value, params);
      orders.value = response.data.orders;
      pagination.value = response.data.pagination;
    } catch (err) {
      error.value = err.message;
    } finally {
      loading.value = false;
    }
  };

  const confirmOrder = async (orderId) => {
    try {
      await orderService.confirm(tenantId.value, orderId);
      await fetchOrders();
      return { success: true };
    } catch (err) {
      return { success: false, error: err.message };
    }
  };

  return {
    orders,
    loading,
    error,
    pagination,
    fetchOrders,
    confirmOrder,
  };
}
```

### Form Submission Example

```javascript
// React Hook Form example
import { useForm } from 'react-hook-form';
import { productService } from '../services/product.service';

function CreateProductForm({ tenantId, onSuccess }) {
  const { register, handleSubmit, formState: { errors } } = useForm();
  const [submitting, setSubmitting] = useState(false);

  const onSubmit = async (data) => {
    setSubmitting(true);
    
    try {
      const response = await productService.create(tenantId, data);
      onSuccess(response.data.product);
    } catch (error) {
      if (error.response?.status === 422) {
        // Handle validation errors
        const details = error.response.data.error?.details || {};
        Object.entries(details).forEach(([field, messages]) => {
          setError(field, { type: 'api', message: messages[0] });
        });
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register('name', { required: 'Name is required' })} />
      {errors.name && <span>{errors.name.message}</span>}
      
      <input {...register('sku', { required: 'SKU is required' })} />
      {errors.sku && <span>{errors.sku.message}</span>}
      
      <input type="number" {...register('base_price', { required: true })} />
      
      <button type="submit" disabled={submitting}>
        {submitting ? 'Creating...' : 'Create Product'}
      </button>
    </form>
  );
}
```

---

## API Endpoints Reference

### Authentication (Tenant Users)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | User login |
| POST | `/auth/logout` | User logout |
| POST | `/auth/refresh` | Refresh token |
| GET | `/auth/me` | Get current user |

### Super Admin Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/admin/auth/login` | Super admin login |
| POST | `/admin/auth/logout` | Super admin logout |
| GET | `/admin/auth/me` | Get current admin |

### Stores

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/stores` | List stores |
| POST | `/tenants/{id}/stores` | Create store |
| GET | `/tenants/{id}/stores/{id}` | Get store |
| PUT | `/tenants/{id}/stores/{id}` | Update store |
| DELETE | `/tenants/{id}/stores/{id}` | Delete store |

### Warehouses

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/warehouses` | List warehouses |
| POST | `/tenants/{id}/warehouses` | Create warehouse |
| GET | `/tenants/{id}/warehouses/{id}` | Get warehouse |
| PUT | `/tenants/{id}/warehouses/{id}` | Update warehouse |
| DELETE | `/tenants/{id}/warehouses/{id}` | Delete warehouse |

### Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/products` | List products |
| POST | `/tenants/{id}/products` | Create product |
| GET | `/tenants/{id}/products/{id}` | Get product |
| PUT | `/tenants/{id}/products/{id}` | Update product |
| DELETE | `/tenants/{id}/products/{id}` | Delete product |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/categories` | List categories |
| POST | `/tenants/{id}/categories` | Create category |
| GET | `/tenants/{id}/categories/{id}` | Get category |
| PUT | `/tenants/{id}/categories/{id}` | Update category |
| DELETE | `/tenants/{id}/categories/{id}` | Delete category |

### Inventory

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/inventory` | List inventory |
| POST | `/tenants/{id}/inventory` | Create inventory |
| GET | `/tenants/{id}/inventory/{id}` | Get inventory |
| PUT | `/tenants/{id}/inventory/{id}` | Update inventory |
| DELETE | `/tenants/{id}/inventory/{id}` | Delete inventory |
| POST | `/tenants/{id}/inventory/transfer` | Transfer inventory |
| GET | `/tenants/{id}/inventory/product/{id}/transferable` | Get transferable qty |

### Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/orders` | List orders |
| POST | `/tenants/{id}/orders` | Create order |
| GET | `/tenants/{id}/orders/{id}` | Get order |
| PUT | `/tenants/{id}/orders/{id}` | Update order |
| DELETE | `/tenants/{id}/orders/{id}` | Delete order |
| POST | `/tenants/{id}/orders/{id}/confirm` | Confirm order |
| POST | `/tenants/{id}/orders/{id}/fulfill` | Fulfill order |
| POST | `/tenants/{id}/orders/{id}/cancel` | Cancel order |

### Customers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/customers` | List customers |
| POST | `/tenants/{id}/customers` | Create customer |
| GET | `/tenants/{id}/customers/{id}` | Get customer |
| PUT | `/tenants/{id}/customers/{id}` | Update customer |
| DELETE | `/tenants/{id}/customers/{id}` | Delete customer |

### Pricing (Optional Feature)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/pricing-tiers` | List pricing tiers |
| POST | `/tenants/{id}/pricing-tiers` | Create pricing tier |
| GET | `/tenants/{id}/pricing-rules` | List pricing rules |
| POST | `/tenants/{id}/pricing-rules` | Create pricing rule |
| PUT | `/tenants/{id}/pricing-rules/{id}` | Update pricing rule |
| DELETE | `/tenants/{id}/pricing-rules/{id}` | Delete pricing rule |
| POST | `/tenants/{id}/prices/calculate` | Calculate price |
| POST | `/tenants/{id}/prices/calculate-cart` | Calculate cart |

### Roles & Permissions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/roles` | List roles |
| POST | `/tenants/{id}/roles` | Create role |
| PUT | `/tenants/{id}/roles/{id}` | Update role |
| DELETE | `/tenants/{id}/roles/{id}` | Delete role |
| POST | `/tenants/{id}/users/{id}/assign-role` | Assign role to user |
| GET | `/tenants/{id}/permissions` | List permissions |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tenants/{id}/dashboard` | Get dashboard metrics |
| GET | `/tenants/{id}/reports/inventory/low-stock` | Low stock report |
| GET | `/tenants/{id}/reports/inventory/stock-levels` | Stock levels report |
| GET | `/tenants/{id}/reports/inventory/movements` | Inventory movements |
| GET | `/tenants/{id}/reports/sales/revenue` | Revenue report |
| GET | `/tenants/{id}/reports/sales/orders-by-period` | Orders by period |
| GET | `/tenants/{id}/reports/sales/top-products` | Top products |

### Super Admin - Tenant Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/tenants` | List all tenants |
| POST | `/admin/tenants` | Create tenant |
| GET | `/admin/tenants/{id}` | Get tenant |
| PUT | `/admin/tenants/{id}` | Update tenant |
| DELETE | `/admin/tenants/{id}` | Delete tenant |
| POST | `/admin/tenants/{id}/activate` | Activate tenant |
| POST | `/admin/tenants/{id}/suspend` | Suspend tenant |
| GET | `/admin/tenants/{id}/stats` | Tenant statistics |

### Super Admin - Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/dashboard` | System overview |
| GET | `/admin/dashboard/revenue` | Revenue metrics |
| GET | `/admin/dashboard/usage` | Usage statistics |
| GET | `/admin/dashboard/alerts` | System alerts |

### Super Admin - User Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/users` | List all users |
| GET | `/admin/users/{id}` | Get user |
| POST | `/admin/users/{id}/impersonate` | Impersonate user |
| POST | `/admin/users/stop-impersonating` | Stop impersonation |

### Super Admin - Audit Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/audit-logs` | List audit logs |
| GET | `/admin/audit-logs/summary` | Audit log summary |
| GET | `/admin/audit-logs/by-user/{id}` | Logs by user |

### Super Admin - Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/settings` | Get settings |
| PUT | `/admin/settings` | Update settings |
| POST | `/admin/settings/clear-cache` | Clear cache |
| GET | `/admin/settings/health` | System health |

---

## Additional Resources

### OpenAPI Specification

Access the complete API specification at:
```
GET /api/v1/docs/openapi.json
```

Or view Swagger UI (if enabled):
```
http://localhost:8000/api/docs
```

### Related Documentation

- [API Design Document](../../API_DESIGN.md)
- [API Overview](../api/01-overview.md)
- [Authentication Guide](../api/02-authentication.md)
- [Super Admin Guide](../api/00-super-admin.md)

### Support

For API issues or questions:
1. Check existing documentation
2. Review API error responses
3. Contact backend development team

---

**Last Updated:** March 25, 2026  
**API Version:** v1  
**Document Version:** 1.0
