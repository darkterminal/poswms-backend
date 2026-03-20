# Inventory Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Inventory

Retrieve all inventory records for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/inventory
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `warehouse_id` | integer | No | Filter by warehouse |
| `store_id` | integer | No | Filter by store |
| `product_id` | integer | No | Filter by product |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "inventories": [
      {
        "id": 1,
        "tenant_id": 1,
        "product_id": 1,
        "warehouse_id": 1,
        "store_id": null,
        "quantity": 150,
        "reserved": 10,
        "available": 140,
        "cost": 50.00,
        "location": "A-12-3",
        "notes": "Main stock",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-15T00:00:00Z",
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "warehouse": {
          "id": 1,
          "name": "Central Warehouse",
          "code": "CW-001"
        },
        "store": null
      },
      {
        "id": 2,
        "tenant_id": 1,
        "product_id": 1,
        "warehouse_id": null,
        "store_id": 1,
        "quantity": 25,
        "reserved": 2,
        "available": 23,
        "cost": 50.00,
        "location": "Shelf A",
        "notes": null,
        "created_at": "2024-01-02T00:00:00Z",
        "updated_at": "2024-01-15T00:00:00Z",
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "warehouse": null,
        "store": {
          "id": 1,
          "name": "Downtown Store",
          "code": "DT-001"
        }
      }
    ]
  }
}
```

---

## Create Inventory

Create a new inventory record.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/inventory
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "product_id": 1,
  "warehouse_id": 1,
  "store_id": null,
  "quantity": 100,
  "reserved": 5,
  "available": 95,
  "cost": 50.00,
  "location": "A-12-3",
  "notes": "Initial stock"
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `product_id` | integer | Yes | Must exist in products |
| `warehouse_id` | integer | No | Must exist in warehouses |
| `store_id` | integer | No | Must exist in stores |
| `quantity` | integer | Yes | Min: 0 |
| `reserved` | integer | No | Min: 0, Default: 0 |
| `available` | integer | No | Min: 0, Default: quantity |
| `cost` | number | No | Min: 0 |
| `location` | string | No | Max 255 characters |
| `notes` | string | No | - |

**Note:** Either `warehouse_id` or `store_id` should be provided.

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "inventory": {
      "id": 1,
      "tenant_id": 1,
      "product_id": 1,
      "warehouse_id": 1,
      "store_id": null,
      "quantity": 100,
      "reserved": 5,
      "available": 95,
      "cost": 50.00,
      "location": "A-12-3",
      "notes": "Initial stock",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Inventory created successfully"
}
```

---

## Get Inventory

Retrieve a specific inventory record.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/inventory/{inventory_id}
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
    "inventory": {
      "id": 1,
      "tenant_id": 1,
      "product_id": 1,
      "warehouse_id": 1,
      "store_id": null,
      "quantity": 150,
      "reserved": 10,
      "available": 140,
      "cost": 50.00,
      "location": "A-12-3",
      "notes": "Main stock",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  }
}
```

---

## Update Inventory

Update an existing inventory record.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/inventory/{inventory_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "quantity": 200,
  "reserved": 15,
  "location": "B-15-2"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "inventory": {
      "id": 1,
      "tenant_id": 1,
      "product_id": 1,
      "warehouse_id": 1,
      "store_id": null,
      "quantity": 200,
      "reserved": 15,
      "available": 185,
      "cost": 50.00,
      "location": "B-15-2",
      "notes": "Main stock",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Inventory updated successfully"
}
```

---

## Delete Inventory

Delete an inventory record.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/inventory/{inventory_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Inventory deleted successfully"
}
```

---

## Inventory Transfer

Transfer stock between locations (warehouses/stores).

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/inventory/transfer
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "product_id": 1,
  "quantity": 50,
  "from_warehouse_id": 1,
  "from_store_id": null,
  "to_warehouse_id": null,
  "to_store_id": 1,
  "reason": "Stock replenishment for store"
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `product_id` | integer | Yes | Must exist in products |
| `quantity` | integer | Yes | Min: 1 |
| `from_warehouse_id` | integer | No | Must exist in warehouses |
| `from_store_id` | integer | No | Must exist in stores |
| `to_warehouse_id` | integer | No | Must exist in warehouses |
| `to_store_id` | integer | No | Must exist in stores |
| `reason` | string | No | Max 255 characters |

**Note:** At least one source (`from_warehouse_id` or `from_store_id`) and one destination (`to_warehouse_id` or `to_store_id`) must be provided.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Stock transferred successfully",
  "data": {
    "source_inventory": {
      "id": 1,
      "product_id": 1,
      "warehouse_id": 1,
      "store_id": null,
      "quantity": 100,
      "reserved": 10,
      "available": 90
    },
    "destination_inventory": {
      "id": 2,
      "product_id": 1,
      "warehouse_id": null,
      "store_id": 1,
      "quantity": 75,
      "reserved": 2,
      "available": 73
    }
  }
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Insufficient stock available for transfer"
}
```

---

## Get Transferable Inventory

Get available inventory for transfer from a location.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/inventory/product/{product_id}/transferable
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `location_id` | integer | No | Filter by location |
| `location_type` | string | No | `warehouse` or `store` (default: warehouse) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "inventory": [
      {
        "id": 1,
        "product_id": 1,
        "warehouse_id": 1,
        "store_id": null,
        "quantity": 150,
        "reserved": 10,
        "available": 140,
        "location_name": "Central Warehouse",
        "location_type": "warehouse"
      }
    ]
  }
}
```
