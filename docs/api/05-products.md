# Product Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Products

Retrieve all products for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/products
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
    "products": [
      {
        "id": 1,
        "tenant_id": 1,
        "category_id": 1,
        "name": "Wireless Headphones",
        "sku": "WH-001",
        "barcode": "1234567890123",
        "description": "High-quality wireless headphones with noise cancellation",
        "price": 99.99,
        "cost": 50.00,
        "tax_rate": 0.08,
        "unit": "piece",
        "min_stock": 10,
        "max_stock": 500,
        "image": "https://example.com/images/wh-001.jpg",
        "images": [
          "https://example.com/images/wh-001-1.jpg",
          "https://example.com/images/wh-001-2.jpg"
        ],
        "attributes": {
          "color": "Black",
          "weight": "250g",
          "battery_life": "30 hours"
        },
        "track_inventory": true,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

## Create Product

Create a new product.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/products
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Wireless Headphones",
  "sku": "WH-001",
  "barcode": "1234567890123",
  "description": "High-quality wireless headphones with noise cancellation",
  "price": 99.99,
  "cost": 50.00,
  "tax_rate": 0.08,
  "unit": "piece",
  "min_stock": 10,
  "max_stock": 500,
  "image": "https://example.com/images/wh-001.jpg",
  "images": [
    "https://example.com/images/wh-001-1.jpg",
    "https://example.com/images/wh-001-2.jpg"
  ],
  "attributes": {
    "color": "Black",
    "weight": "250g",
    "battery_life": "30 hours"
  },
  "track_inventory": true,
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `category_id` | integer | No | Must exist in categories |
| `name` | string | Yes | Max 255 characters |
| `sku` | string | Yes | Max 100 characters |
| `barcode` | string | No | Max 100 characters |
| `description` | string | No | - |
| `price` | number | Yes | Min: 0 |
| `cost` | number | No | Min: 0 |
| `tax_rate` | number | No | Min: 0 |
| `unit` | string | No | Max 50 characters |
| `min_stock` | integer | No | Min: 0 |
| `max_stock` | integer | No | Min: 0 |
| `image` | string | No | - |
| `images` | array | No | - |
| `attributes` | array | No | - |
| `track_inventory` | boolean | No | Default: true |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 1,
      "tenant_id": 1,
      "category_id": 1,
      "name": "Wireless Headphones",
      "sku": "WH-001",
      "barcode": "1234567890123",
      "description": "High-quality wireless headphones with noise cancellation",
      "price": 99.99,
      "cost": 50.00,
      "tax_rate": 0.08,
      "unit": "piece",
      "min_stock": 10,
      "max_stock": 500,
      "image": "https://example.com/images/wh-001.jpg",
      "images": [
        "https://example.com/images/wh-001-1.jpg",
        "https://example.com/images/wh-001-2.jpg"
      ],
      "attributes": {
        "color": "Black",
        "weight": "250g",
        "battery_life": "30 hours"
      },
      "track_inventory": true,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Product created successfully"
}
```

---

## Get Product

Retrieve a specific product.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/products/{product_id}
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
    "product": {
      "id": 1,
      "tenant_id": 1,
      "category_id": 1,
      "name": "Wireless Headphones",
      "sku": "WH-001",
      "barcode": "1234567890123",
      "description": "High-quality wireless headphones with noise cancellation",
      "price": 99.99,
      "cost": 50.00,
      "tax_rate": 0.08,
      "unit": "piece",
      "min_stock": 10,
      "max_stock": 500,
      "image": "https://example.com/images/wh-001.jpg",
      "images": [
        "https://example.com/images/wh-001-1.jpg",
        "https://example.com/images/wh-001-2.jpg"
      ],
      "attributes": {
        "color": "Black",
        "weight": "250g",
        "battery_life": "30 hours"
      },
      "track_inventory": true,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

---

## Update Product

Update an existing product.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/products/{product_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "price": 89.99,
  "min_stock": 15,
  "attributes": {
    "color": "Black",
    "weight": "250g",
    "battery_life": "35 hours"
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 1,
      "tenant_id": 1,
      "category_id": 1,
      "name": "Wireless Headphones",
      "sku": "WH-001",
      "barcode": "1234567890123",
      "description": "High-quality wireless headphones with noise cancellation",
      "price": 89.99,
      "cost": 50.00,
      "tax_rate": 0.08,
      "unit": "piece",
      "min_stock": 15,
      "max_stock": 500,
      "image": "https://example.com/images/wh-001.jpg",
      "images": [
        "https://example.com/images/wh-001-1.jpg",
        "https://example.com/images/wh-001-2.jpg"
      ],
      "attributes": {
        "color": "Black",
        "weight": "250g",
        "battery_life": "35 hours"
      },
      "track_inventory": true,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Product updated successfully"
}
```

---

## Delete Product

Delete a product.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/products/{product_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```
