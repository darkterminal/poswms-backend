# Warehouse Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Warehouses

Retrieve all warehouses for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/warehouses
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
    "warehouses": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "Central Warehouse",
        "code": "CW-001",
        "address": "789 Industrial Blvd",
        "city": "Newark",
        "state": "NJ",
        "country": "USA",
        "postal_code": "07101",
        "phone": "+1-555-0200",
        "email": "central@example.com",
        "latitude": 40.7357,
        "longitude": -74.1724,
        "settings": {
          "timezone": "America/New_York",
          "loading_docks": 8
        },
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

## Create Warehouse

Create a new warehouse.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/warehouses
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Central Warehouse",
  "code": "CW-001",
  "address": "789 Industrial Blvd",
  "city": "Newark",
  "state": "NJ",
  "country": "USA",
  "postal_code": "07101",
  "phone": "+1-555-0200",
  "email": "central@example.com",
  "latitude": 40.7357,
  "longitude": -74.1724,
  "settings": {
    "timezone": "America/New_York",
    "loading_docks": 8
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
| `latitude` | number | No | - |
| `longitude` | number | No | - |
| `settings` | array | No | - |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "warehouse": {
      "id": 1,
      "tenant_id": 1,
      "name": "Central Warehouse",
      "code": "CW-001",
      "address": "789 Industrial Blvd",
      "city": "Newark",
      "state": "NJ",
      "country": "USA",
      "postal_code": "07101",
      "phone": "+1-555-0200",
      "email": "central@example.com",
      "latitude": 40.7357,
      "longitude": -74.1724,
      "settings": {
        "timezone": "America/New_York",
        "loading_docks": 8
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Warehouse created successfully"
}
```

---

## Get Warehouse

Retrieve a specific warehouse.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | integer | Yes | Tenant identifier |
| `warehouseId` | integer | Yes | Warehouse identifier |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "warehouse": {
      "id": 1,
      "tenant_id": 1,
      "name": "Central Warehouse",
      "code": "CW-001",
      "address": "789 Industrial Blvd",
      "city": "Newark",
      "state": "NJ",
      "country": "USA",
      "postal_code": "07101",
      "phone": "+1-555-0200",
      "email": "central@example.com",
      "latitude": 40.7357,
      "longitude": -74.1724,
      "settings": {
        "timezone": "America/New_York",
        "loading_docks": 8
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

---

## Update Warehouse

Update an existing warehouse.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Central Distribution Center",
  "phone": "+1-555-0299",
  "settings": {
    "timezone": "America/New_York",
    "loading_docks": 12,
    "cold_storage": true
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "warehouse": {
      "id": 1,
      "tenant_id": 1,
      "name": "Central Distribution Center",
      "code": "CW-001",
      "address": "789 Industrial Blvd",
      "city": "Newark",
      "state": "NJ",
      "country": "USA",
      "postal_code": "07101",
      "phone": "+1-555-0299",
      "email": "central@example.com",
      "latitude": 40.7357,
      "longitude": -74.1724,
      "settings": {
        "timezone": "America/New_York",
        "loading_docks": 12,
        "cold_storage": true
      },
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Warehouse updated successfully"
}
```

---

## Delete Warehouse

Delete a warehouse.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/warehouses/{warehouseId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Warehouse deleted successfully"
}
```
