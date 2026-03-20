# Order Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Orders

Retrieve all orders for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/orders
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status |
| `store_id` | integer | No | Filter by store |
| `customer_id` | integer | No | Filter by customer |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 1,
        "tenant_id": 1,
        "order_number": "ORD-2024-0001",
        "customer_id": 1,
        "store_id": 1,
        "warehouse_id": 1,
        "user_id": 1,
        "status": "pending",
        "type": "sale",
        "subtotal": 199.98,
        "tax": 16.00,
        "discount": 0.00,
        "shipping": 10.00,
        "total": 225.98,
        "payment_status": "pending",
        "payment_method": "credit_card",
        "notes": "Please deliver before 5 PM",
        "shipping_address": "123 Customer St",
        "shipping_city": "New York",
        "shipping_state": "NY",
        "shipping_country": "USA",
        "shipping_postal_code": "10001",
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z",
        "customer": {
          "id": 1,
          "name": "John Customer",
          "email": "john@example.com"
        },
        "store": {
          "id": 1,
          "name": "Downtown Store",
          "code": "DT-001"
        },
        "warehouse": {
          "id": 1,
          "name": "Central Warehouse",
          "code": "CW-001"
        },
        "items": [
          {
            "id": 1,
            "order_id": 1,
            "product_id": 1,
            "quantity": 2,
            "unit_price": 99.99,
            "total": 199.98,
            "discount": 0.00
          }
        ]
      }
    ]
  }
}
```

---

## Create Order

Create a new order.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/orders
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "order_number": "ORD-2024-0002",
  "customer_id": 1,
  "store_id": 1,
  "warehouse_id": 1,
  "status": "pending",
  "type": "sale",
  "subtotal": 199.98,
  "tax": 16.00,
  "discount": 0.00,
  "shipping": 10.00,
  "payment_status": "pending",
  "payment_method": "credit_card",
  "notes": "Please deliver before 5 PM",
  "shipping_address": "123 Customer St",
  "shipping_city": "New York",
  "shipping_state": "NY",
  "shipping_country": "USA",
  "shipping_postal_code": "10001",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 99.99
    },
    {
      "product_id": 2,
      "quantity": 1,
      "unit_price": 49.99
    }
  ]
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `order_number` | string | No | Auto-generated if not provided |
| `customer_id` | integer | No | Must exist in customers |
| `store_id` | integer | No | Must exist in stores |
| `warehouse_id` | integer | No | Must exist in warehouses |
| `status` | string | No | `pending`, `confirmed`, `fulfilled`, `cancelled` (default: pending) |
| `type` | string | No | Default: sale |
| `subtotal` | number | No | Min: 0, Auto-calculated if items provided |
| `tax` | number | No | Min: 0 |
| `discount` | number | No | Min: 0 |
| `shipping` | number | No | Min: 0 |
| `payment_status` | string | No | Default: pending |
| `payment_method` | string | No | Max 100 characters |
| `notes` | string | No | - |
| `shipping_address` | string | No | - |
| `shipping_city` | string | No | Max 255 characters |
| `shipping_state` | string | No | Max 255 characters |
| `shipping_country` | string | No | Max 255 characters |
| `shipping_postal_code` | string | No | Max 50 characters |
| `items` | array | No | - |
| `items.*.product_id` | integer | Yes* | Required if items provided |
| `items.*.quantity` | integer | Yes* | Min: 1 |
| `items.*.unit_price` | number | Yes* | Min: 0 |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 2,
      "tenant_id": 1,
      "order_number": "ORD-2024-0002",
      "customer_id": 1,
      "store_id": 1,
      "warehouse_id": 1,
      "user_id": 1,
      "status": "pending",
      "type": "sale",
      "subtotal": 249.97,
      "tax": 16.00,
      "discount": 0.00,
      "shipping": 10.00,
      "total": 275.97,
      "payment_status": "pending",
      "payment_method": "credit_card",
      "notes": "Please deliver before 5 PM",
      "created_at": "2024-01-15T11:00:00Z",
      "updated_at": "2024-01-15T11:00:00Z"
    }
  },
  "message": "Order created successfully"
}
```

---

## Get Order

Retrieve a specific order.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/orders/{order_id}
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
    "order": {
      "id": 1,
      "tenant_id": 1,
      "order_number": "ORD-2024-0001",
      "customer_id": 1,
      "store_id": 1,
      "warehouse_id": 1,
      "user_id": 1,
      "status": "pending",
      "type": "sale",
      "subtotal": 199.98,
      "tax": 16.00,
      "discount": 0.00,
      "shipping": 10.00,
      "total": 225.98,
      "payment_status": "pending",
      "payment_method": "credit_card",
      "notes": "Please deliver before 5 PM",
      "shipping_address": "123 Customer St",
      "shipping_city": "New York",
      "shipping_state": "NY",
      "shipping_country": "USA",
      "shipping_postal_code": "10001",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z",
      "customer": {
        "id": 1,
        "name": "John Customer",
        "email": "john@example.com"
      },
      "items": [
        {
          "id": 1,
          "order_id": 1,
          "product_id": 1,
          "quantity": 2,
          "unit_price": 99.99,
          "total": 199.98,
          "discount": 0.00,
          "product": {
            "id": 1,
            "name": "Wireless Headphones",
            "sku": "WH-001"
          }
        }
      ]
    }
  }
}
```

---

## Update Order

Update an existing order.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/orders/{order_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "status": "confirmed",
  "payment_status": "paid",
  "notes": "Updated delivery instructions"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "tenant_id": 1,
      "order_number": "ORD-2024-0001",
      "customer_id": 1,
      "store_id": 1,
      "warehouse_id": 1,
      "user_id": 1,
      "status": "confirmed",
      "type": "sale",
      "subtotal": 199.98,
      "tax": 16.00,
      "discount": 0.00,
      "shipping": 10.00,
      "total": 225.98,
      "payment_status": "paid",
      "payment_method": "credit_card",
      "notes": "Updated delivery instructions",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T11:00:00Z"
    }
  },
  "message": "Order updated successfully"
}
```

---

## Delete Order

Delete an order.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/orders/{order_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Order deleted successfully"
}
```

---

## Confirm Order

Confirm an order (change status to confirmed).

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/orders/{order_id}/confirm
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "tenant_id": 1,
      "order_number": "ORD-2024-0001",
      "status": "confirmed",
      "total": 225.98,
      "updated_at": "2024-01-15T11:30:00Z"
    }
  },
  "message": "Order confirmed successfully"
}
```

---

## Fulfill Order

Fulfill an order (change status to fulfilled and update inventory).

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/orders/{order_id}/fulfill
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "tenant_id": 1,
      "order_number": "ORD-2024-0001",
      "status": "fulfilled",
      "total": 225.98,
      "fulfilled_at": "2024-01-15T14:00:00Z",
      "updated_at": "2024-01-15T14:00:00Z"
    }
  },
  "message": "Order fulfilled successfully"
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Insufficient stock to fulfill order"
}
```

---

## Cancel Order

Cancel an order (change status to cancelled and restore inventory).

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/orders/{order_id}/cancel
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "tenant_id": 1,
      "order_number": "ORD-2024-0001",
      "status": "cancelled",
      "total": 225.98,
      "cancelled_at": "2024-01-15T12:00:00Z",
      "updated_at": "2024-01-15T12:00:00Z"
    }
  },
  "message": "Order cancelled successfully"
}
```

---

## Order Status Flow

```
pending → confirmed → fulfilled
   ↓
cancelled
```

- **pending**: Order created, awaiting confirmation
- **confirmed**: Order confirmed, ready for fulfillment
- **fulfilled**: Order shipped/delivered, inventory deducted
- **cancelled**: Order cancelled, inventory restored (if applicable)
