# API Testing Guide: Postman & Insomnia Collections

## Setup Guide for Testing the Multi-Store & Warehouse Management System API

---

## Table of Contents

1. [Postman Setup](#postman-setup)
2. [Insomnia Setup](#insomnia-setup)
3. [Environment Variables](#environment-variables)
4. [Collection Structure](#collection-structure)
5. [Pre-request Scripts](#pre-request-scripts)
6. [Test Scripts](#test-scripts)
7. [Example Requests](#example-requests)

---

## Postman Setup

### 1. Import Collection

**Option A: Import from OpenAPI Spec**
1. Open Postman
2. Click **Import**
3. Enter URL: `http://localhost:8000/api/v1/docs/openapi.json`
4. Click **Continue** → **Import**

**Option B: Manual Creation**
1. Click **Collections** → **Create Collection**
2. Name: `POS WMS API`
3. Add folders for each module

### 2. Create Environment

1. Click **Environments** → **Create Environment**
2. Name: `POS WMS - Development`
3. Add variables (see [Environment Variables](#environment-variables))
4. Save and activate the environment

### 3. Configure Authorization

1. Go to Collection → **Authorization** tab
2. Type: **Bearer Token**
3. Token: `{{access_token}}`
4. Check **Save helpers with collection**

---

## Insomnia Setup

### 1. Import Collection

**Option A: Import from OpenAPI Spec**
1. Open Insomnia
2. Click **Import**
3. Enter URL: `http://localhost:8000/api/v1/docs/openapi.json`
4. Click **Import**

**Option B: Manual Creation**
1. Click **+** next to **Collections**
2. Name: `POS WMS API`
3. Create folders for organization

### 2. Create Environment

1. Click **Dashboard** → **Environments**
2. Click **Create Environment**
3. Name: `Development`
4. Add variables (see below)

### 3. Configure Authentication

1. Go to Collection → **Auth** tab
2. Type: **Bearer Token**
3. Token: `{{access_token}}`

---

## Environment Variables

### Development Environment

```json
{
  "base_url": "http://localhost:8000",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}",
  "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
  "access_token": "",
  "admin_access_token": "",
  "user_email": "user@store.com",
  "user_password": "password",
  "admin_email": "admin@platform.com",
  "admin_password": "password",
  "store_id": "",
  "warehouse_id": "",
  "product_id": "",
  "order_id": "",
  "customer_id": "",
  "category_id": ""
}
```

### Staging Environment

```json
{
  "base_url": "https://staging-api.yourdomain.com",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}",
  "tenant_id": "",
  "access_token": "",
  "admin_access_token": ""
}
```

### Production Environment

```json
{
  "base_url": "https://api.yourdomain.com",
  "api_version": "v1",
  "api_base": "{{base_url}}/api/{{api_version}}",
  "tenant_id": "",
  "access_token": "",
  "admin_access_token": ""
}
```

---

## Collection Structure

```
POS WMS API/
├── Authentication/
│   ├── Tenant Login
│   ├── Tenant Logout
│   ├── Tenant Refresh Token
│   ├── Get Current User
│   ├── Super Admin Login
│   ├── Super Admin Logout
│   └── Get Current Admin
├── Super Admin/
│   ├── Tenants/
│   │   ├── List Tenants
│   │   ├── Create Tenant
│   │   ├── Get Tenant
│   │   ├── Update Tenant
│   │   ├── Delete Tenant
│   │   ├── Activate Tenant
│   │   ├── Suspend Tenant
│   │   └── Get Tenant Stats
│   ├── Dashboard/
│   │   ├── Overview
│   │   ├── Revenue
│   │   ├── Usage
│   │   └── Alerts
│   ├── Users/
│   │   ├── List Users
│   │   ├── Get User
│   │   ├── Impersonate User
│   │   └── Stop Impersonating
│   ├── Audit Logs/
│   │   ├── List Logs
│   │   ├── Log Summary
│   │   └── Logs by User
│   └── Settings/
│       ├── Get Settings
│       ├── Update Settings
│       ├── Clear Cache
│       └── Health Check
├── Stores/
│   ├── List Stores
│   ├── Create Store
│   ├── Get Store
│   ├── Update Store
│   └── Delete Store
├── Warehouses/
│   ├── List Warehouses
│   ├── Create Warehouse
│   ├── Get Warehouse
│   ├── Update Warehouse
│   └── Delete Warehouse
├── Products/
│   ├── List Products
│   ├── Create Product
│   ├── Get Product
│   ├── Update Product
│   └── Delete Product
├── Categories/
│   ├── List Categories
│   ├── Create Category
│   ├── Get Category
│   ├── Update Category
│   └── Delete Category
├── Inventory/
│   ├── List Inventory
│   ├── Create Inventory
│   ├── Get Inventory
│   ├── Update Inventory
│   ├── Delete Inventory
│   ├── Transfer Inventory
│   └── Get Transferable Quantity
├── Orders/
│   ├── List Orders
│   ├── Create Order
│   ├── Get Order
│   ├── Update Order
│   ├── Delete Order
│   ├── Confirm Order
│   ├── Fulfill Order
│   └── Cancel Order
├── Customers/
│   ├── List Customers
│   ├── Create Customer
│   ├── Get Customer
│   ├── Update Customer
│   └── Delete Customer
├── Pricing/
│   ├── List Pricing Tiers
│   ├── Create Pricing Tier
│   ├── List Pricing Rules
│   ├── Create Pricing Rule
│   ├── Calculate Price
│   └── Calculate Cart
├── Reports/
│   ├── Dashboard Metrics
│   ├── Inventory Reports/
│   │   ├── Low Stock
│   │   ├── Stock Levels
│   │   └── Movements
│   └── Sales Reports/
│       ├── Revenue
│       ├── Orders by Period
│       └── Top Products
└── Roles & Permissions/
    ├── List Roles
    ├── Create Role
    ├── Update Role
    ├── Delete Role
    ├── Assign Role to User
    ├── List Permissions
    └── Create Permission
```

---

## Pre-request Scripts

### Auto-Login (Authentication Folder)

```javascript
// Check if token exists or is expired
const token = pm.environment.get('access_token');
const tokenExpiry = pm.environment.get('token_expiry');

if (!token || !tokenExpiry || new Date() >= new Date(tokenExpiry)) {
    // Perform login
    const loginRequest = {
        url: pm.environment.get('api_base') + '/auth/login',
        method: 'POST',
        header: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: {
            mode: 'raw',
            raw: JSON.stringify({
                email: pm.environment.get('user_email'),
                password: pm.environment.get('user_password')
            })
        }
    };

    pm.sendRequest(loginRequest, (err, response) => {
        if (!err && response.code === 200) {
            const jsonData = response.json();
            pm.environment.set('access_token', jsonData.data.token);
            // Set expiry (24 hours from now)
            const expiry = new Date();
            expiry.setHours(expiry.getHours() + 24);
            pm.environment.set('token_expiry', expiry.toISOString());
            console.log('Token refreshed automatically');
        } else {
            console.error('Auto-login failed:', err || response.status);
        }
    });
}
```

### Set Tenant ID

```javascript
// Get tenant ID from current user if not set
if (!pm.environment.get('tenant_id')) {
    const token = pm.environment.get('access_token');
    
    pm.sendRequest({
        url: pm.environment.get('api_base') + '/auth/me',
        method: 'GET',
        header: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        }
    }, (err, response) => {
        if (!err && response.code === 200) {
            const jsonData = response.json();
            pm.environment.set('tenant_id', jsonData.data.user.tenant_id);
        }
    });
}
```

---

## Test Scripts

### Common Response Tests

```javascript
// Test for successful response
pm.test("Status code is 200 or 201", function () {
    pm.response.to.have.status([200, 201]);
});

pm.test("Response has success field", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('success');
    pm.expect(jsonData.success).to.be.true;
});

pm.test("Response has data field", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('data');
});

// Save ID from response for subsequent requests
pm.test("Save resource ID", function () {
    const jsonData = pm.response.json();
    if (jsonData.data && jsonData.data.store) {
        pm.environment.set('store_id', jsonData.data.store.id);
    }
    if (jsonData.data && jsonData.data.warehouse) {
        pm.environment.set('warehouse_id', jsonData.data.warehouse.id);
    }
    if (jsonData.data && jsonData.data.product) {
        pm.environment.set('product_id', jsonData.data.product.id);
    }
    if (jsonData.data && jsonData.data.order) {
        pm.environment.set('order_id', jsonData.data.order.id);
    }
});
```

### Validation Error Tests

```javascript
pm.test("Status code is 422", function () {
    pm.response.to.have.status(422);
});

pm.test("Response has validation errors", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.false;
    pm.expect(jsonData.error).to.have.property('details');
});
```

### Authentication Error Tests

```javascript
pm.test("Status code is 401", function () {
    pm.response.to.have.status(401);
});

pm.test("Clear token on unauthorized", function () {
    pm.environment.unset('access_token');
});
```

---

## Example Requests

### 1. Tenant Login

**Request:**
```http
POST {{api_base}}/auth/login
Content-Type: application/json

{
  "email": "{{user_email}}",
  "password": "{{user_password}}"
}
```

**Test Script:**
```javascript
pm.test("Login successful", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    pm.expect(jsonData.success).to.be.true;
    pm.expect(jsonData.data).to.have.property('token');
    pm.environment.set('access_token', jsonData.data.token);
});
```

### 2. Create Store

**Request:**
```http
POST {{api_base}}/tenants/{{tenant_id}}/stores
Authorization: Bearer {{access_token}}
Content-Type: application/json

{
  "name": "Test Store",
  "code": "TS-001",
  "address": "123 Test St",
  "city": "Test City",
  "state": "TS",
  "country": "Test",
  "postal_code": "12345",
  "phone": "+1234567890",
  "email": "test@store.com",
  "active": true
}
```

**Test Script:**
```javascript
pm.test("Store created", function () {
    pm.response.to.have.status(201);
    const jsonData = pm.response.json();
    pm.expect(jsonData.data.store).to.have.property('id');
    pm.environment.set('store_id', jsonData.data.store.id);
});
```

### 3. Create Product

**Request:**
```http
POST {{api_base}}/tenants/{{tenant_id}}/products
Authorization: Bearer {{access_token}}
Content-Type: application/json

{
  "sku": "TEST-PROD-001",
  "name": "Test Product",
  "description": "A test product",
  "base_price": 99.99,
  "cost_price": 50.00,
  "is_active": true
}
```

### 4. Create Order

**Request:**
```http
POST {{api_base}}/tenants/{{tenant_id}}/orders
Authorization: Bearer {{access_token}}
Content-Type: application/json

{
  "store_id": "{{store_id}}",
  "customer_id": "{{customer_id}}",
  "warehouse_id": "{{warehouse_id}}",
  "type": "sale",
  "status": "pending",
  "items": [
    {
      "product_id": "{{product_id}}",
      "quantity": 2,
      "unit_price": 99.99
    }
  ],
  "tax": 19.99,
  "discount": 0,
  "shipping": 5.00
}
```

### 5. Super Admin - Create Tenant

**Request:**
```http
POST {{api_base}}/admin/tenants
Authorization: Bearer {{admin_access_token}}
Content-Type: application/json

{
  "name": "New Business",
  "slug": "new-business",
  "company_name": "New Business Corp",
  "email": "contact@newbusiness.com",
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

### 6. Get Dashboard Metrics

**Request:**
```http
GET {{api_base}}/tenants/{{tenant_id}}/dashboard
Authorization: Bearer {{access_token}}
```

**Test Script:**
```javascript
pm.test("Dashboard metrics retrieved", function () {
    pm.response.to.have.status(200);
    const jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property('metrics');
    console.log('Dashboard metrics:', JSON.stringify(jsonData.data.metrics, null, 2));
});
```

### 7. Transfer Inventory

**Request:**
```http
POST {{api_base}}/tenants/{{tenant_id}}/inventory/transfer
Authorization: Bearer {{access_token}}
Content-Type: application/json

{
  "product_id": "{{product_id}}",
  "from_location_type": "warehouse",
  "from_location_id": "{{warehouse_id}}",
  "to_location_type": "store",
  "to_location_id": "{{store_id}}",
  "quantity": 10
}
```

### 8. Get Low Stock Report

**Request:**
```http
GET {{api_base}}/tenants/{{tenant_id}}/reports/inventory/low-stock
Authorization: Bearer {{access_token}}
```

---

## Collection Runner Setup

### 1. Create Test Sequence

1. Open **Collection Runner**
2. Select collection: `POS WMS API`
3. Select environment: `Development`
4. Arrange requests in order:
   - Login
   - Create Store
   - Create Warehouse
   - Create Category
   - Create Product
   - Create Inventory
   - Create Customer
   - Create Order
   - Confirm Order
   - Fulfill Order
   - Get Reports
   - Logout

### 2. Data File for Testing

**test-data.json:**
```json
[
  {
    "store_name": "Store A",
    "store_code": "SA-001",
    "product_name": "Product A",
    "product_sku": "PROD-A-001",
    "product_price": 99.99
  },
  {
    "store_name": "Store B",
    "store_code": "SB-001",
    "product_name": "Product B",
    "product_sku": "PROD-B-001",
    "product_price": 149.99
  }
]
```

### 3. Run Collection

```bash
# Using Newman (Postman CLI)
newman run "POS WMS API.json" \
  --environment "Development.json" \
  --data test-data.json \
  --reporters cli,json \
  --reporter-json-export results.json
```

---

## Debugging Tips

### 1. View Request/Response Logs

**Postman:**
- Open **Console** (View → Show Postman Console)
- Check request headers and body
- Inspect response details

**Insomnia:**
- Open **Timeline** tab
- View request/response history
- Check network timing

### 2. Environment Variable Debugging

```javascript
// Add to Pre-request Script
console.log('Base URL:', pm.environment.get('api_base'));
console.log('Tenant ID:', pm.environment.get('tenant_id'));
console.log('Token:', pm.environment.get('access_token')?.substring(0, 20) + '...');
```

### 3. Handle Token Expiry

```javascript
// Add to Tests tab
if (pm.response.code === 401) {
    pm.environment.unset('access_token');
    pm.execution.skipRequest();
    // Trigger re-authentication
}
```

---

## Sharing Collections

### Export Collection (Postman)

1. Click **...** next to collection name
2. Select **Export**
3. Choose format: **Collection v2.1**
4. Save JSON file

### Import Collection

1. Click **Import**
2. Select JSON file
3. Collection appears in sidebar

### Export Environment

1. Click **...** next to environment
2. Select **Export**
3. Save JSON file (don't commit tokens!)

---

## Security Best Practices

1. **Never commit tokens** to version control
2. **Use environment variables** for sensitive data
3. **Export collections without environment data**
4. **Use separate environments** for dev/staging/prod
5. **Rotate tokens regularly**
6. **Use .gitignore** for Postman/Insomnia data files

**.gitignore example:**
```
# Postman
*.postman_environment.json
postman_data/

# Insomnia
insomnia_data/
```

---

## Troubleshooting

### Issue: 401 Unauthorized

**Solutions:**
1. Check token is set in environment
2. Verify token hasn't expired
3. Re-login to get new token
4. Check Authorization header format: `Bearer {{access_token}}`

### Issue: 404 Not Found

**Solutions:**
1. Verify tenant_id is correct
2. Check URL path includes tenant_id
3. Ensure resource ID exists
4. Check API base URL is correct

### Issue: 422 Validation Error

**Solutions:**
1. Check request body matches API spec
2. Verify all required fields are present
3. Check field types and formats
4. Review validation error details in response

### Issue: 429 Rate Limited

**Solutions:**
1. Wait for retry-after period
2. Reduce request frequency
3. Implement exponential backoff
4. Check rate limit headers

---

## Additional Resources

- [Postman Documentation](https://learning.postman.com/docs/)
- [Insomnia Documentation](https://docs.insomnia.rest/)
- [Newman CLI](https://github.com/postmanlabs/newman)
- [API Design Document](../../API_DESIGN.md)
- [Frontend Integration Guide](./FRONTEND_INTEGRATION_GUIDE.md)

---

**Quick Start:**
1. Import collection from OpenAPI spec
2. Create environment with variables
3. Run login request to get token
4. Token auto-saves to environment
5. Test other endpoints!

**Tip:** Use the Collection Runner to test entire workflows automatically.
