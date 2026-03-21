# Webhooks & Audit Logs Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## Webhooks

Webhooks allow you to receive real-time notifications when events occur in the system.

### List Webhooks

Retrieve all webhooks for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/webhooks
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "name": "Order Notifications",
      "url": "https://example.com/webhooks/orders",
      "secret": "whsec_abc123...",
      "events": [
        "order.created",
        "order.confirmed",
        "order.fulfilled",
        "order.cancelled"
      ],
      "active": true,
      "content_type": "json",
      "headers": {
        "X-Custom-Header": "value"
      },
      "retry_count": 3,
      "timeout": 30,
      "delivery_attempts_count": 125,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

---

### Create Webhook

Create a new webhook.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/webhooks
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Order Notifications",
  "url": "https://example.com/webhooks/orders",
  "secret": "whsec_your_webhook_secret",
  "events": [
    "order.created",
    "order.confirmed",
    "order.fulfilled",
    "order.cancelled"
  ],
  "active": true,
  "content_type": "json",
  "headers": {
    "X-Custom-Header": "value",
    "Authorization": "Bearer token"
  },
  "retry_count": 3,
  "timeout": 30
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `url` | string | Yes | Valid URL, max 2048 characters |
| `secret` | string | No | Max 255 characters |
| `events` | array | Yes | Min: 1 item |
| `events.*` | string | Yes | Valid event name |
| `active` | boolean | No | Default: true |
| `content_type` | string | No | `json` or `form-data` (default: json) |
| `headers` | array | No | Custom headers |
| `retry_count` | integer | No | Min: 0, Max: 10, Default: 3 |
| `timeout` | integer | No | Min: 1, Max: 300, Default: 30 |

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Webhook created successfully",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "name": "Order Notifications",
    "url": "https://example.com/webhooks/orders",
    "secret": "whsec_abc123...",
    "events": [
      "order.created",
      "order.confirmed",
      "order.fulfilled",
      "order.cancelled"
    ],
    "active": true,
    "content_type": "json",
    "headers": {
      "X-Custom-Header": "value"
    },
    "retry_count": 3,
    "timeout": 30,
    "created_at": "2024-01-15T00:00:00Z",
    "updated_at": "2024-01-15T00:00:00Z"
  }
}
```

---

### Get Webhook

Retrieve a specific webhook with delivery attempts.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/webhooks/{webhook}
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
    "id": 1,
    "tenant_id": 1,
    "name": "Order Notifications",
    "url": "https://example.com/webhooks/orders",
    "secret": "whsec_abc123...",
    "events": [
      "order.created",
      "order.confirmed",
      "order.fulfilled",
      "order.cancelled"
    ],
    "active": true,
    "content_type": "json",
    "headers": {
      "X-Custom-Header": "value"
    },
    "retry_count": 3,
    "timeout": 30,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "delivery_attempts": [
      {
        "id": 1,
        "webhook_id": 1,
        "event_type": "order.created",
        "attempt_number": 1,
        "response_status": 200,
        "success": true,
        "created_at": "2024-01-15T10:00:00Z"
      }
    ]
  }
}
```

---

### Update Webhook

Update an existing webhook.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/webhooks/{webhook}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "All Order Events",
  "url": "https://new-endpoint.example.com/webhooks",
  "events": [
    "order.created",
    "order.updated",
    "order.confirmed",
    "order.fulfilled",
    "order.cancelled"
  ],
  "active": false
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Webhook updated successfully",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "name": "All Order Events",
    "url": "https://new-endpoint.example.com/webhooks",
    "secret": "whsec_abc123...",
    "events": [
      "order.created",
      "order.updated",
      "order.confirmed",
      "order.fulfilled",
      "order.cancelled"
    ],
    "active": false,
    "content_type": "json",
    "headers": {
      "X-Custom-Header": "value"
    },
    "retry_count": 3,
    "timeout": 30,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-15T00:00:00Z"
  }
}
```

---

### Delete Webhook

Delete a webhook.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/webhooks/{webhook}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Webhook deleted successfully"
}
```

---

### Test Webhook

Send a test event to the webhook.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/webhooks/{webhook}/test
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
  "message": "Test webhook delivered successfully",
  "data": {
    "success": true,
    "response_status": 200,
    "response_body": "{\"received\": true}",
    "attempt_number": 1,
    "delivered_at": "2024-01-15T11:00:00Z"
  }
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Test webhook failed",
  "data": {
    "success": false,
    "response_status": 500,
    "response_body": "{\"error\": \"Internal server error\"}",
    "attempt_number": 1,
    "delivered_at": "2024-01-15T11:00:00Z"
  }
}
```

---

### Get Delivery Attempts

Get delivery attempts for a webhook.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/webhooks/{webhook}/attempts
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "webhook_id": 1,
      "event_type": "order.created",
      "payload": {
        "order_id": 123,
        "order_number": "ORD-2024-0001"
      },
      "attempt_number": 1,
      "response_status": 200,
      "response_body": "{\"received\": true}",
      "success": true,
      "created_at": "2024-01-15T10:00:00Z"
    },
    {
      "id": 2,
      "webhook_id": 1,
      "event_type": "order.fulfilled",
      "payload": {
        "order_id": 120,
        "order_number": "ORD-2024-0000"
      },
      "attempt_number": 1,
      "response_status": 500,
      "response_body": "{\"error\": \"Server error\"}",
      "success": false,
      "created_at": "2024-01-15T09:00:00Z"
    }
  ]
}
```

---

### Retry Failed Deliveries

Retry failed delivery attempts for all webhooks.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/webhooks/{webhook}/retry
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
  "message": "Retried 3 failed delivery attempt(s)"
}
```

**Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Cannot retry webhooks that are not active"
}
```

---

## Available Webhook Events

| Event | Description |
|-------|-------------|
| `order.created` | Triggered when a new order is created |
| `order.updated` | Triggered when an order is updated |
| `order.confirmed` | Triggered when an order is confirmed |
| `order.fulfilled` | Triggered when an order is fulfilled |
| `order.cancelled` | Triggered when an order is cancelled |
| `product.created` | Triggered when a new product is created |
| `product.updated` | Triggered when a product is updated |
| `product.deleted` | Triggered when a product is deleted |
| `inventory.low_stock` | Triggered when stock falls below minimum |
| `inventory.out_of_stock` | Triggered when stock reaches zero |
| `customer.created` | Triggered when a new customer is created |
| `webhook.test` | Test event for webhook verification |

---

## Webhook Payload Format

### JSON Content Type

```json
{
  "event": "order.created",
  "timestamp": "2024-01-15T10:00:00Z",
  "tenant_id": 1,
  "data": {
    "order_id": 123,
    "order_number": "ORD-2024-0001",
    "status": "pending",
    "total": 225.98
  }
}
```

### Signature Verification

When a `secret` is configured, webhooks include an `X-Webhook-Signature` header containing an HMAC-SHA256 signature of the payload.

Verify the signature:

```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature, 'hex'),
    Buffer.from(expected, 'hex')
  );
}
```

---

## Audit Logs

### List Audit Logs

Retrieve audit logs with filtering and pagination.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/audit-logs
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `event_type` | string | No | Filter by event type |
| `user_id` | integer | No | Filter by user |
| `auditable_type` | string | No | Filter by model type |
| `auditable_id` | integer | No | Filter by model ID |
| `start_date` | date | No | Filter from date |
| `end_date` | date | No | Filter to date |
| `per_page` | integer | No | Items per page (default: 20) |
| `page` | integer | No | Page number |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "user_id": 1,
      "event_type": "product.created",
      "auditable_type": "App\\Models\\Product",
      "auditable_id": 1,
      "old_values": null,
      "new_values": {
        "name": "Wireless Headphones",
        "sku": "WH-001",
        "price": 99.99
      },
      "created_at": "2024-01-15T10:00:00Z",
      "user": {
        "id": 1,
        "name": "John Admin",
        "email": "admin@example.com"
      },
      "tenant": {
        "id": 1,
        "name": "Acme Corp"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

---

### Get Audit Log

Retrieve a specific audit log entry.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/audit-logs/{auditLog}
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
    "id": 1,
    "tenant_id": 1,
    "user_id": 1,
    "event_type": "product.updated",
    "auditable_type": "App\\Models\\Product",
    "auditable_id": 1,
    "old_values": {
      "price": 99.99
    },
    "new_values": {
      "price": 89.99
    },
    "created_at": "2024-01-15T11:00:00Z",
    "user": {
      "id": 1,
      "name": "John Admin",
      "email": "admin@example.com"
    },
    "tenant": {
      "id": 1,
      "name": "Acme Corp"
    }
  }
}
```

---

### Get Audit Logs by User

Get audit logs for a specific user.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/audit-logs/by-user/{userId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "user_id": 1,
      "event_type": "product.created",
      "auditable_type": "App\\Models\\Product",
      "auditable_id": 1,
      "old_values": null,
      "new_values": {
        "name": "Wireless Headphones",
        "sku": "WH-001"
      },
      "created_at": "2024-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 52,
    "user": {
      "id": 1,
      "name": "John Admin",
      "email": "admin@example.com"
    }
  }
}
```

---

### Get Audit Summary

Get audit log statistics and summary.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/audit-logs/summary
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `start_date` | date | No | Filter from date |
| `end_date` | date | No | Filter to date |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "total_events": 523,
    "by_event_type": {
      "product.created": 45,
      "product.updated": 120,
      "product.deleted": 5,
      "order.created": 145,
      "order.updated": 85,
      "order.fulfilled": 95,
      "order.cancelled": 8,
      "inventory.adjusted": 20
    },
    "by_user": [
      {
        "user": "John Admin",
        "count": 250
      },
      {
        "user": "Jane Manager",
        "count": 180
      },
      {
        "user": "Bob Staff",
        "count": 93
      }
    ]
  }
}
```

---

## Audit Event Types

| Event Type | Description |
|------------|-------------|
| `*.created` | Resource created |
| `*.updated` | Resource updated |
| `*.deleted` | Resource deleted |
| `user.login` | User logged in |
| `user.logout` | User logged out |
| `role.assigned` | Role assigned to user |
| `role.removed` | Role removed from user |
| `inventory.adjusted` | Inventory quantity adjusted |
| `inventory.transfer` | Stock transferred between locations |
| `order.confirmed` | Order confirmed |
| `order.fulfilled` | Order fulfilled |
| `order.cancelled` | Order cancelled |
