# Customer Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Customers

Retrieve all customers for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/customers
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "customers": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "John Customer",
        "email": "john@example.com",
        "phone": "+1-555-0300",
        "company": "Acme Corp",
        "tax_id": "12-3456789",
        "address": "123 Customer St",
        "city": "New York",
        "state": "NY",
        "country": "USA",
        "postal_code": "10001",
        "pricing_tier_id": 1,
        "credit_limit": 5000.00,
        "balance": 225.98,
        "settings": {
          "preferred_payment": "credit_card",
          "newsletter": true
        },
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-15T00:00:00Z"
      }
    ]
  }
}
```

---

## Create Customer

Create a new customer.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/customers
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Customer",
  "email": "john@example.com",
  "phone": "+1-555-0300",
  "company": "Acme Corp",
  "tax_id": "12-3456789",
  "address": "123 Customer St",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "pricing_tier_id": 1,
  "credit_limit": 5000.00,
  "settings": {
    "preferred_payment": "credit_card",
    "newsletter": true
  },
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `email` | string | No | Valid email, max 255 characters |
| `phone` | string | No | Max 50 characters |
| `company` | string | No | Max 255 characters |
| `tax_id` | string | No | Max 100 characters |
| `address` | string | No | - |
| `city` | string | No | Max 255 characters |
| `state` | string | No | Max 255 characters |
| `country` | string | No | Max 255 characters |
| `postal_code` | string | No | Max 50 characters |
| `pricing_tier_id` | integer | No | Must exist in pricing_tiers |
| `credit_limit` | number | No | Min: 0, Default: 0 |
| `balance` | number | No | Min: 0, Default: 0 |
| `settings` | array | No | - |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Customer",
      "email": "john@example.com",
      "phone": "+1-555-0300",
      "company": "Acme Corp",
      "tax_id": "12-3456789",
      "address": "123 Customer St",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "pricing_tier_id": 1,
      "credit_limit": 5000.00,
      "balance": 0.00,
      "settings": {
        "preferred_payment": "credit_card",
        "newsletter": true
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Customer created successfully"
}
```

---

## Get Customer

Retrieve a specific customer.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/customers/{customerId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Customer",
      "email": "john@example.com",
      "phone": "+1-555-0300",
      "company": "Acme Corp",
      "tax_id": "12-3456789",
      "address": "123 Customer St",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "pricing_tier_id": 1,
      "credit_limit": 5000.00,
      "balance": 225.98,
      "settings": {
        "preferred_payment": "credit_card",
        "newsletter": true
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  }
}
```

---

## Update Customer

Update an existing customer.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/customers/{customerId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "phone": "+1-555-0399",
  "credit_limit": 10000.00,
  "settings": {
    "preferred_payment": "bank_transfer",
    "newsletter": false
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Customer",
      "email": "john@example.com",
      "phone": "+1-555-0399",
      "company": "Acme Corp",
      "tax_id": "12-3456789",
      "address": "123 Customer St",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "pricing_tier_id": 1,
      "credit_limit": 10000.00,
      "balance": 225.98,
      "settings": {
        "preferred_payment": "bank_transfer",
        "newsletter": false
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Customer updated successfully"
}
```

---

## Delete Customer

Delete a customer.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/customers/{customerId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Customer deleted successfully"
}
```
