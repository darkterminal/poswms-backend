# API Overview

## Base URL

```
/api/v1
```

All API endpoints are prefixed with `/api/v1/` to support versioning.

## Authentication

The API uses **Laravel Sanctum** for token-based authentication.

### Authentication Flow

1. **Login**: Send credentials to receive a Bearer token
2. **Authenticated Requests**: Include token in `Authorization` header
3. **Logout**: Revoke the current token
4. **Refresh**: Revoke current token and issue a new one

### Authorization Header

Include the Bearer token in all authenticated requests:

```
Authorization: Bearer {your-token}
```

## Multi-Tenant Architecture

All endpoints (except authentication) require a `tenant_id` path parameter:

```
/api/v1/tenants/{tenant_id}/resources
```

The system automatically scopes all data by tenant to ensure data isolation.

## Request Format

### Content-Type

All requests that send data must use:

```
Content-Type: application/json
```

### Request Body

```json
{
  "field": "value",
  "another_field": "another_value"
}
```

## Response Format

### Success Response

```json
{
  "success": true,
  "data": {
    "resource": {
      "id": 1,
      "name": "Example"
    }
  },
  "message": "Operation successful"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "field": ["The field is required."]
    }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

## HTTP Status Codes

| Code | Status | Description |
|------|--------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid request |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Resource conflict (e.g., duplicate) |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

## Pagination

Some endpoints support pagination via query parameters:

```
GET /api/v1/tenants/{tenant_id}/audit-logs?per_page=20&page=1
```

### Pagination Response

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

## Filtering & Query Parameters

Many endpoints support filtering via query parameters:

```
GET /api/v1/tenants/{tenant_id}/orders?status=pending&store_id=1
```

Common query parameters:
- `per_page`: Items per page (default: 20)
- `page`: Page number
- `sort`: Sort field
- `order`: Sort order (asc/desc)

## Rate Limiting

API requests are rate limited to prevent abuse. Default limits:
- **100 requests per minute** per user

When exceeded, you'll receive a `429 Too Many Requests` response.

## Date Format

All dates use **ISO 8601** format:

```
2024-01-01T00:00:00Z
```

## Common Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Auto-increment primary key |
| `tenant_id` | integer | Tenant identifier |
| `created_at` | datetime | Record creation timestamp |
| `updated_at` | datetime | Record last update timestamp |
| `active` | boolean | Record active status |
