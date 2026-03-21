# Pricing Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## Pricing Tiers

### List Pricing Tiers

Retrieve all pricing tiers for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/pricing-tiers
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
    "pricing_tiers": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "Retail",
        "slug": "retail",
        "description": "Standard retail pricing",
        "priority": 1,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "name": "Wholesale",
        "slug": "wholesale",
        "description": "Wholesale pricing with 20% discount",
        "priority": 2,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 3,
        "tenant_id": 1,
        "name": "VIP",
        "slug": "vip",
        "description": "VIP customer pricing with 30% discount",
        "priority": 3,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

### Create Pricing Tier

Create a new pricing tier.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/pricing-tiers
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Wholesale",
  "slug": "wholesale",
  "description": "Wholesale pricing with 20% discount",
  "priority": 2,
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `slug` | string | Yes | Max 100 characters |
| `description` | string | No | - |
| `priority` | integer | No | Min: 0 |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "pricing_tier": {
      "id": 2,
      "tenant_id": 1,
      "name": "Wholesale",
      "slug": "wholesale",
      "description": "Wholesale pricing with 20% discount",
      "priority": 2,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Pricing tier created successfully"
}
```

---

### Get Pricing Tier

Retrieve a specific pricing tier.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/pricing-tiers/{tierId}
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
    "pricing_tier": {
      "id": 1,
      "tenant_id": 1,
      "name": "Retail",
      "slug": "retail",
      "description": "Standard retail pricing",
      "priority": 1,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

---

### Update Pricing Tier

Update an existing pricing tier.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/pricing-tiers/{tierId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Wholesale (20% Off)",
  "priority": 3
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "pricing_tier": {
      "id": 2,
      "tenant_id": 1,
      "name": "Wholesale (20% Off)",
      "slug": "wholesale",
      "description": "Wholesale pricing with 20% discount",
      "priority": 3,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Pricing tier updated successfully"
}
```

---

### Delete Pricing Tier

Delete a pricing tier.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/pricing-tiers/{tierId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Pricing tier deleted successfully"
}
```

---

## Pricing Rules

### List Pricing Rules

Retrieve all pricing rules for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/pricing-rules
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
    "pricing_rules": [
      {
        "id": 1,
        "tenant_id": 1,
        "pricing_tier_id": 2,
        "product_id": null,
        "category_id": null,
        "type": "percentage",
        "operation": "subtract",
        "value": 20.00,
        "min_quantity": 1,
        "max_quantity": null,
        "starts_at": null,
        "ends_at": null,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z",
        "pricing_tier": {
          "id": 2,
          "name": "Wholesale",
          "slug": "wholesale"
        },
        "product": null,
        "category": null
      },
      {
        "id": 2,
        "tenant_id": 1,
        "pricing_tier_id": 1,
        "product_id": 1,
        "category_id": null,
        "type": "fixed",
        "operation": "replace",
        "value": 79.99,
        "min_quantity": 10,
        "max_quantity": null,
        "starts_at": "2024-01-01T00:00:00Z",
        "ends_at": "2024-12-31T23:59:59Z",
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z",
        "pricing_tier": {
          "id": 1,
          "name": "Retail",
          "slug": "retail"
        },
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "category": null
      }
    ]
  }
}
```

---

### Create Pricing Rule

Create a new pricing rule.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/pricing-rules
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "pricing_tier_id": 2,
  "product_id": null,
  "category_id": null,
  "type": "percentage",
  "operation": "subtract",
  "value": 20.00,
  "min_quantity": 1,
  "max_quantity": null,
  "starts_at": null,
  "ends_at": null,
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `pricing_tier_id` | integer | No | Must exist in pricing_tiers |
| `product_id` | integer | No | Must exist in products |
| `category_id` | integer | No | Must exist in categories |
| `type` | string | Yes | `percentage` or `fixed` |
| `operation` | string | Yes | `add`, `subtract`, or `replace` |
| `value` | number | Yes | Min: 0 |
| `min_quantity` | integer | No | Min: 0, Default: 0 |
| `max_quantity` | integer | No | Min: 0 |
| `starts_at` | date | No | - |
| `ends_at` | date | No | - |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "pricing_rule": {
      "id": 1,
      "tenant_id": 1,
      "pricing_tier_id": 2,
      "product_id": null,
      "category_id": null,
      "type": "percentage",
      "operation": "subtract",
      "value": 20.00,
      "min_quantity": 0,
      "max_quantity": null,
      "starts_at": null,
      "ends_at": null,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Pricing rule created successfully"
}
```

---

### Get Pricing Rule

Retrieve a specific pricing rule.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/pricing-rules/{ruleId}
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
    "pricing_rule": {
      "id": 1,
      "tenant_id": 1,
      "pricing_tier_id": 2,
      "product_id": null,
      "category_id": null,
      "type": "percentage",
      "operation": "subtract",
      "value": 20.00,
      "min_quantity": 0,
      "max_quantity": null,
      "starts_at": null,
      "ends_at": null,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

---

### Update Pricing Rule

Update an existing pricing rule.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/pricing-rules/{ruleId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "value": 25.00,
  "min_quantity": 5,
  "ends_at": "2024-06-30T23:59:59Z"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "pricing_rule": {
      "id": 1,
      "tenant_id": 1,
      "pricing_tier_id": 2,
      "product_id": null,
      "category_id": null,
      "type": "percentage",
      "operation": "subtract",
      "value": 25.00,
      "min_quantity": 5,
      "max_quantity": null,
      "starts_at": null,
      "ends_at": "2024-06-30T23:59:59Z",
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Pricing rule updated successfully"
}
```

---

### Delete Pricing Rule

Delete a pricing rule.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/pricing-rules/{ruleId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Pricing rule deleted successfully"
}
```

---

## Price Calculation

### Calculate Single Product Price

Calculate the final price for a product considering pricing rules.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/prices/calculate
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
  "quantity": 10,
  "customer_id": 1
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `product_id` | integer | Yes | Must exist in products |
| `quantity` | integer | No | Min: 1, Default: 1 |
| `customer_id` | integer | No | Must exist in customers |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 1,
      "name": "Wireless Headphones",
      "sku": "WH-001"
    },
    "quantity": 10,
    "customer": {
      "id": 1,
      "name": "John Customer",
      "pricing_tier": "Wholesale"
    },
    "pricing": {
      "base_price": 99.99,
      "adjusted_price": 79.99,
      "discount_amount": 20.00,
      "discount_percentage": 20.00,
      "total": 799.90,
      "applied_rules": [
        {
          "rule_id": 1,
          "type": "percentage",
          "operation": "subtract",
          "value": 20.00,
          "description": "Wholesale discount"
        }
      ]
    }
  }
}
```

---

### Calculate Cart Price

Calculate prices for multiple products (shopping cart).

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/prices/calculate-cart
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 2,
      "quantity": 5
    }
  ],
  "customer_id": 1
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `items` | array | Yes | - |
| `items.*.product_id` | integer | Yes | Must exist in products |
| `items.*.quantity` | integer | Yes | Min: 1 |
| `customer_id` | integer | No | Must exist in customers |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "name": "John Customer",
      "pricing_tier": "Wholesale"
    },
    "pricing": {
      "items": [
        {
          "product_id": 1,
          "product_name": "Wireless Headphones",
          "quantity": 2,
          "base_price": 99.99,
          "adjusted_price": 79.99,
          "subtotal": 159.98
        },
        {
          "product_id": 2,
          "product_name": "USB-C Cable",
          "quantity": 5,
          "base_price": 19.99,
          "adjusted_price": 15.99,
          "subtotal": 79.95
        }
      ],
      "subtotal": 239.93,
      "total_discount": 59.98,
      "total": 239.93
    }
  }
}
```
