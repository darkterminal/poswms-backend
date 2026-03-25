# User Guides - POS WMS Backend

This directory contains comprehensive guides for integrating with the Multi-Store & Warehouse Management System API.

---

## Available Guides

### 📱 For Frontend Developers

#### 1. [Frontend Integration Guide](./FRONTEND_INTEGRATION_GUIDE.md)
**Complete API integration documentation for frontend developers.**

**Contents:**
- Quick start setup
- Authentication flow
- Standard response formats
- Error handling strategies
- Core modules integration (Stores, Warehouses, Products, Inventory, Orders, Customers)
- Super Admin module integration
- Rate limiting handling
- Best practices and patterns
- Code examples (React, Vue, vanilla JavaScript)
- Complete API endpoints reference

**Best for:** Frontend developers starting fresh with the API

---

#### 2. [Frontend Cheat Sheet](./FRONTEND_CHEAT_SHEET.md)
**Quick reference for common API operations.**

**Contents:**
- Base URLs for all environments
- Authentication endpoints
- URL structure patterns
- HTTP status codes
- Core endpoints overview
- Super Admin endpoints
- Rate limits
- Common request bodies
- JavaScript service templates
- React hooks examples
- Vue composables examples
- Error handling patterns
- Pagination patterns
- File download patterns

**Best for:** Quick lookups during development

---

#### 3. [API Testing Guide](./API_TESTING_GUIDE.md)
**Setup and usage guide for Postman and Insomnia.**

**Contents:**
- Postman setup instructions
- Insomnia setup instructions
- Environment variables configuration
- Collection structure
- Pre-request scripts
- Test scripts
- Example requests for all endpoints
- Collection runner setup
- Debugging tips
- Security best practices
- Troubleshooting guide

**Best for:** QA testing and API exploration

---

## Quick Start by Role

### Frontend Developer (New to Project)

1. **Start Here:** [Frontend Integration Guide](./FRONTEND_INTEGRATION_GUIDE.md)
   - Read sections 1-3 for setup
   - Review section 6 for your specific module
   - Keep section 11 handy for reference

2. **During Development:** [Cheat Sheet](./FRONTEND_CHEAT_SHEET.md)
   - Quick endpoint lookups
   - Copy-paste code examples
   - Reference request/response formats

3. **For Testing:** [API Testing Guide](./API_TESTING_GUIDE.md)
   - Import collection into Postman/Insomnia
   - Set up environment variables
   - Test endpoints before implementing

---

### QA Engineer

1. **Setup:** [API Testing Guide](./API_TESTING_GUIDE.md)
   - Follow Postman/Insomnia setup
   - Configure environment variables
   - Import collection

2. **Testing:** Use Collection Runner
   - Run full test sequences
   - Use data files for multiple scenarios
   - Generate test reports

3. **Reference:** [Cheat Sheet](./FRONTEND_CHEAT_SHEET.md)
   - Quick endpoint reference
   - Rate limit information
   - Common test scenarios

---

### Backend Developer (API Documentation)

1. **Complete API Design:** [API_DESIGN.md](../../API_DESIGN.md)
   - Architecture overview
   - Database schema
   - Security considerations
   - Implementation phases

2. **Endpoint Details:** [docs/api/](../api/)
   - Individual endpoint documentation
   - Request/response examples
   - Validation rules

---

## Environment Configuration

### Development

```javascript
{
  "base_url": "http://localhost:8000",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}"
}
```

### Staging

```javascript
{
  "base_url": "https://staging-api.yourdomain.com",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}"
}
```

### Production

```javascript
{
  "base_url": "https://api.yourdomain.com",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}"
}
```

---

## Authentication Overview

### Tenant Users

**Login Endpoint:** `POST /api/v1/auth/login`

**Request:**
```json
{
  "email": "user@store.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": { ... },
    "token": "Bearer_TOKEN",
    "token_type": "Bearer"
  }
}
```

**Usage:** Add header to all requests:
```
Authorization: Bearer {{access_token}}
```

### Super Admin

**Login Endpoint:** `POST /api/v1/admin/auth/login`

**Note:** Uses separate authentication guard and doesn't require tenant ID in URLs.

---

## URL Structure

### Tenant Routes
```
/api/v1/tenants/{tenant_id}/{resource}
```

**Example:**
```
GET /api/v1/tenants/550e8400-e29b-41d4-a716-446655440000/stores
```

### Super Admin Routes
```
/api/v1/admin/{resource}
```

**Example:**
```
GET /api/v1/admin/tenants
```

---

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful",
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable message",
    "details": { ... }
  }
}
```

---

## Common HTTP Status Codes

| Code | Meaning | Typical Action |
|------|---------|----------------|
| 200 | OK | Request succeeded |
| 201 | Created | Resource created |
| 400 | Bad Request | Fix request data |
| 401 | Unauthorized | Login required |
| 403 | Forbidden | Check permissions |
| 404 | Not Found | Check resource ID |
| 422 | Validation Error | Fix form data |
| 429 | Rate Limited | Wait and retry |
| 500 | Server Error | Contact support |

---

## Core Modules

### 1. Store Management
- List, create, update, delete stores
- Filter by tenant
- Store settings and configuration

### 2. Warehouse Management
- List, create, update, delete warehouses
- Track capacity and location
- Manager assignments

### 3. Product Management
- Product catalog management
- SKU and barcode support
- Category organization
- Pricing (base and cost)

### 4. Inventory Management
- Track stock levels
- Warehouse and store inventory
- Stock transfers
- Low-stock alerts

### 5. Order Management
- Create and process orders
- Order lifecycle (pending → confirmed → fulfilled)
- Order items and totals
- Customer association

### 6. Customer Management
- Customer profiles
- Contact information
- Order history
- Pricing tiers (optional)

### 7. Reports & Analytics
- Dashboard metrics
- Inventory reports
- Sales reports
- Export functionality

### 8. Super Admin Module
- Tenant management
- System dashboard
- User management
- Audit logs
- System settings

---

## Rate Limiting

| Endpoint Type | Limit | Retry-After |
|--------------|-------|-------------|
| Authentication | 10/min | 60s |
| Tenant API | 100/min | 60s |
| Super Admin API | 200/min | 60s |
| Export Reports | 10/min | 60s |
| Webhook Test | 5/min | 60s |

**Headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
Retry-After: 60
```

---

## Best Practices

### 1. Authentication
- Store tokens securely
- Implement token refresh
- Handle 401 responses (logout)
- Use HTTPS in production

### 2. Error Handling
- Check response status codes
- Parse error response structure
- Show user-friendly messages
- Log errors for debugging

### 3. Performance
- Implement request debouncing
- Use pagination for lists
- Cache frequently accessed data
- Handle rate limits gracefully

### 4. Security
- Validate input before sending
- Never expose tokens in client code
- Use environment variables
- Implement CSRF protection

### 5. Testing
- Test with Postman/Insomnia first
- Write integration tests
- Mock API responses for UI dev
- Test error scenarios

---

## Code Examples

### JavaScript/axios Setup

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add auth token
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

### React Hook Example

```javascript
import { useState, useEffect } from 'react';
import api from '../services/api';

function useProducts(tenantId) {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    async function fetchProducts() {
      setLoading(true);
      const response = await api.get(`/tenants/${tenantId}/products`);
      setProducts(response.data.data.products);
      setLoading(false);
    }
    fetchProducts();
  }, [tenantId]);

  return { products, loading };
}
```

---

## Additional Resources

### Internal Documentation
- [API Design Document](../../API_DESIGN.md) - Complete API architecture
- [API Endpoints](../api/) - Detailed endpoint documentation
- [Progress Tracker](../PROGRESS_TRACKER.md) - Development status

### External Resources
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [RESTful API Best Practices](https://restfulapi.net/)
- [OpenAPI Specification](https://swagger.io/specification/)

### API Documentation Endpoint
```
GET /api/v1/docs/openapi.json
```

Returns the complete OpenAPI 3.0 specification for import into Postman, Insomnia, or Swagger UI.

---

## Support

For questions or issues:

1. **Check Documentation:** Review the guides above
2. **Test with Postman:** Verify API behavior
3. **Check Logs:** Review application logs for errors
4. **Contact Team:** Reach out to backend developers

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-03-25 | Initial release |

---

**Last Updated:** March 25, 2026  
**Maintained By:** Backend Development Team
