# Store Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Stores

Retrieve all stores for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/stores
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | integer | Yes | Tenant identifier |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "stores": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "Downtown Store",
        "code": "DT-001",
        "address": "123 Main Street",
        "city": "New York",
        "state": "NY",
        "country": "USA",
        "postal_code": "10001",
        "phone": "+1-555-0100",
        "email": "downtown@example.com",
        "settings": {
          "timezone": "America/New_York",
          "currency": "USD"
        },
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "name": "Uptown Store",
        "code": "UT-002",
        "address": "456 Park Avenue",
        "city": "New York",
        "state": "NY",
        "country": "USA",
        "postal_code": "10002",
        "phone": "+1-555-0101",
        "email": "uptown@example.com",
        "settings": null,
        "active": true,
        "created_at": "2024-01-02T00:00:00Z",
        "updated_at": "2024-01-02T00:00:00Z"
      }
    ]
  }
}
```

---

## Create Store

Create a new store.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/stores
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Downtown Store",
  "code": "DT-001",
  "address": "123 Main Street",
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "postal_code": "10001",
  "phone": "+1-555-0100",
  "email": "downtown@example.com",
  "settings": {
    "timezone": "America/New_York",
    "currency": "USD"
  },
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `code` | string | Yes | Max 100 characters |
| `address` | string | No | - |
| `city` | string | No | Max 255 characters |
| `state` | string | No | Max 255 characters |
| `country` | string | No | Max 255 characters |
| `postal_code` | string | No | Max 50 characters |
| `phone` | string | No | Max 50 characters |
| `email` | string | No | Valid email, max 255 characters |
| `settings` | array | No | - |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "store": {
      "id": 1,
      "tenant_id": 1,
      "name": "Downtown Store",
      "code": "DT-001",
      "address": "123 Main Street",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "phone": "+1-555-0100",
      "email": "downtown@example.com",
      "settings": {
        "timezone": "America/New_York",
        "currency": "USD"
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Store created successfully"
}
```

---

## Get Store

Retrieve a specific store.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/stores/{storeId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | integer | Yes | Tenant identifier |
| `storeId` | integer | Yes | Store identifier |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "store": {
      "id": 1,
      "tenant_id": 1,
      "name": "Downtown Store",
      "code": "DT-001",
      "address": "123 Main Street",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "phone": "+1-555-0100",
      "email": "downtown@example.com",
      "settings": {
        "timezone": "America/New_York",
        "currency": "USD"
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

---

## Update Store

Update an existing store.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/stores/{storeId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (partial update):**
```json
{
  "name": "Downtown Flagship Store",
  "phone": "+1-555-0199",
  "settings": {
    "timezone": "America/New_York",
    "currency": "USD",
    "tax_rate": 0.08
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "store": {
      "id": 1,
      "tenant_id": 1,
      "name": "Downtown Flagship Store",
      "code": "DT-001",
      "address": "123 Main Street",
      "city": "New York",
      "state": "NY",
      "country": "USA",
      "postal_code": "10001",
      "phone": "+1-555-0199",
      "email": "downtown@example.com",
      "settings": {
        "timezone": "America/New_York",
        "currency": "USD",
        "tax_rate": 0.08
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Store updated successfully"
}
```

---

## Delete Store

Delete a store.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/stores/{storeId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Store deleted successfully"
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "name": ["The name field is required."],
      "code": ["The code field is required."]
    }
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Store not found."
  }
}
```
