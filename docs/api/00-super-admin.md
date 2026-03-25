# Super Admin API Documentation

## Overview

The Super Admin API provides system-wide management capabilities for SaaS platform owners. These endpoints operate **outside tenant scope** and use a **separate authentication guard**.

### Key Features

- **Separate Authentication**: Super admins use a dedicated auth guard (`auth:sanctum.super_admin`)
- **System-Level Operations**: Manage tenants, users, and system configuration
- **Cross-Tenant Visibility**: View and manage data across all tenants
- **Audit Logging**: All actions are logged for compliance and security
- **Stricter Rate Limiting**: 200 requests/minute (vs 60 for tenant API)

---

## Authentication

### Super Admin Login

**Endpoint:** `POST /api/v1/admin/auth/login`

**Description:** Authenticate as a super admin and receive an access token.

**Request:**
```http
POST /api/v1/admin/auth/login
Content-Type: application/json

{
  "email": "superadmin@poswms.com",
  "password": "secure-password"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "System Administrator",
      "email": "superadmin@poswms.com",
      "role": "super_admin",
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z"
    },
    "token": "1|superAdminToken123...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

**Error Response (401):**
```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "The provided credentials are incorrect.",
    "details": {
      "email": ["The provided credentials are incorrect."]
    }
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "User does not have super admin privileges."
  }
}
```

### Super Admin Logout

**Endpoint:** `POST /api/v1/admin/auth/logout`

**Description:** Invalidate the current super admin token.

**Request:**
```http
POST /api/v1/admin/auth/logout
Authorization: Bearer {super-admin-token}
Content-Type: application/json
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### Get Current Super Admin

**Endpoint:** `GET /api/v1/admin/auth/me`

**Description:** Retrieve the authenticated super admin's information.

**Request:**
```http
GET /api/v1/admin/auth/me
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "System Administrator",
      "email": "superadmin@poswms.com",
      "role": "super_admin",
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "User retrieved successfully"
}
```

---

## Tenant Management

### List All Tenants

**Endpoint:** `GET /api/v1/admin/tenants`

**Description:** Retrieve a paginated list of all tenants in the system.

**Request:**
```http
GET /api/v1/admin/tenants?status=active&search=acme&per_page=15&page=1
Authorization: Bearer {super-admin-token}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | - | Filter by status: `active`, `suspended`, `trial` |
| `search` | string | - | Search by name, email, or company name |
| `per_page` | integer | 15 | Items per page (max: 100) |
| `page` | integer | 1 | Page number |
| `sort` | string | `created_at` | Sort field |
| `order` | string | `desc` | Sort order: `asc`, `desc` |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenants": [
      {
        "id": 1,
        "name": "Acme Corporation",
        "slug": "acme-corp",
        "company_name": "Acme Corp Inc.",
        "email": "contact@acme.com",
        "status": "active",
        "subscription_plan": "premium",
        "trial_ends_at": null,
        "subscription_ends_at": "2025-01-01T00:00:00Z",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "name": "TechStart Inc",
        "slug": "techstart",
        "company_name": "TechStart Incorporated",
        "email": "hello@techstart.com",
        "status": "trial",
        "subscription_plan": "basic",
        "trial_ends_at": "2026-04-20T00:00:00Z",
        "subscription_ends_at": null,
        "created_at": "2026-03-20T00:00:00Z",
        "updated_at": "2026-03-20T00:00:00Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

### Create Tenant

**Endpoint:** `POST /api/v1/admin/tenants`

**Description:** Create a new tenant business in the system.

**Request:**
```http
POST /api/v1/admin/tenants
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "name": "Acme Corporation",
  "slug": "acme-corp",
  "company_name": "Acme Corp Inc.",
  "email": "contact@acme.com",
  "subscription_plan": "premium",
  "trial_ends_at": "2026-04-20T00:00:00Z",
  "subscription_ends_at": "2025-01-01T00:00:00Z"
}
```

**Request Body Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Business display name |
| `slug` | string | Yes | Unique URL-friendly identifier |
| `company_name` | string | Yes | Legal company name |
| `email` | string | Yes | Primary contact email |
| `subscription_plan` | string | Yes | Plan: `basic`, `premium`, `enterprise` |
| `trial_ends_at` | datetime | No | Trial expiration date |
| `subscription_ends_at` | datetime | No | Subscription expiration date |

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 3,
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "company_name": "Acme Corp Inc.",
      "email": "contact@acme.com",
      "status": "trial",
      "subscription_plan": "premium",
      "trial_ends_at": "2026-04-20T00:00:00Z",
      "subscription_ends_at": null,
      "created_at": "2026-03-25T10:00:00Z",
      "updated_at": "2026-03-25T10:00:00Z"
    }
  },
  "message": "Tenant created successfully"
}
```

**Error Response (409):**
```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_RESOURCE",
    "message": "A tenant with this slug already exists.",
    "details": {
      "slug": ["A tenant with this slug already exists."]
    }
  }
}
```

### Get Tenant Details

**Endpoint:** `GET /api/v1/admin/tenants/{tenantId}`

**Description:** Retrieve detailed information about a specific tenant.

**Request:**
```http
GET /api/v1/admin/tenants/1
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "name": "Acme Corporation",
      "slug": "acme-corp",
      "company_name": "Acme Corp Inc.",
      "email": "contact@acme.com",
      "status": "active",
      "subscription_plan": "premium",
      "trial_ends_at": null,
      "subscription_ends_at": "2025-01-01T00:00:00Z",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "stats": {
        "total_users": 25,
        "total_stores": 8,
        "total_warehouses": 2,
        "total_products": 450,
        "total_orders": 1250
      }
    }
  }
}
```

### Update Tenant

**Endpoint:** `PUT /api/v1/admin/tenants/{tenantId}`

**Description:** Update tenant information.

**Request:**
```http
PUT /api/v1/admin/tenants/1
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "name": "Acme Corporation Updated",
  "company_name": "Acme Corp Inc. LLC",
  "email": "newcontact@acme.com",
  "subscription_plan": "enterprise"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "name": "Acme Corporation Updated",
      "slug": "acme-corp",
      "company_name": "Acme Corp Inc. LLC",
      "email": "newcontact@acme.com",
      "status": "active",
      "subscription_plan": "enterprise",
      "updated_at": "2026-03-25T10:30:00Z"
    }
  },
  "message": "Tenant updated successfully"
}
```

### Delete Tenant

**Endpoint:** `DELETE /api/v1/admin/tenants/{tenantId}`

**Description:** Soft delete a tenant (preserves data for compliance).

**Request:**
```http
DELETE /api/v1/admin/tenants/1
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Tenant deleted successfully"
}
```

### Activate Tenant

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/activate`

**Description:** Activate a suspended tenant.

**Request:**
```http
POST /api/v1/admin/tenants/1/activate
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "status": "active",
      "activated_at": "2026-03-25T10:00:00Z"
    }
  },
  "message": "Tenant activated successfully"
}
```

### Suspend Tenant

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/suspend`

**Description:** Suspend an active tenant (e.g., for non-payment).

**Request:**
```http
POST /api/v1/admin/tenants/1/suspend
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "reason": "Non-payment for 30 days"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "status": "suspended",
      "suspended_at": "2026-03-25T10:00:00Z",
      "suspension_reason": "Non-payment for 30 days"
    }
  },
  "message": "Tenant suspended successfully"
}
```

### Get Tenant Statistics

**Endpoint:** `GET /api/v1/admin/tenants/{tenantId}/stats`

**Description:** Retrieve detailed statistics for a tenant.

**Request:**
```http
GET /api/v1/admin/tenants/1/stats
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant_id": 1,
    "tenant_name": "Acme Corporation",
    "users": {
      "total": 25,
      "active": 23,
      "inactive": 2,
      "by_role": {
        "admin": 2,
        "manager": 5,
        "store_manager": 8,
        "warehouse_manager": 2,
        "sales_associate": 8
      }
    },
    "stores": {
      "total": 8,
      "active": 7,
      "inactive": 1
    },
    "warehouses": {
      "total": 2,
      "active": 2,
      "inactive": 0
    },
    "products": {
      "total": 450,
      "active": 425,
      "inactive": 25
    },
    "orders": {
      "total": 1250,
      "today": 45,
      "this_week": 312,
      "this_month": 1180,
      "by_status": {
        "pending": 12,
        "confirmed": 8,
        "fulfilling": 5,
        "completed": 1220,
        "cancelled": 5
      }
    },
    "inventory": {
      "total_value": 125000.00,
      "low_stock_count": 15,
      "out_of_stock_count": 3
    },
    "revenue": {
      "today": 2500.00,
      "this_week": 15800.00,
      "this_month": 58000.00,
      "total": 250000.00,
      "currency": "USD"
    }
  }
}
```

---

## Subscription Management

### Update Trial Period

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/trial`

**Description:** Update a tenant's trial period.

**Request:**
```http
POST /api/v1/admin/tenants/1/trial
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "trial_ends_at": "2026-05-20T00:00:00Z"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "trial_ends_at": "2026-05-20T00:00:00Z",
      "status": "trial"
    }
  },
  "message": "Trial period updated successfully"
}
```

### Extend Trial

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/trial/extend`

**Description:** Extend a tenant's trial by a specified number of days.

**Request:**
```http
POST /api/v1/admin/tenants/1/trial/extend
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "days": 14
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "trial_ends_at": "2026-06-03T00:00:00Z",
      "extended_by_days": 14
    }
  },
  "message": "Trial extended by 14 days"
}
```

### Update Subscription

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/subscription`

**Description:** Update a tenant's subscription details.

**Request:**
```http
POST /api/v1/admin/tenants/1/subscription
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "subscription_plan": "enterprise",
  "subscription_ends_at": "2027-01-01T00:00:00Z"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "subscription_plan": "enterprise",
      "subscription_ends_at": "2027-01-01T00:00:00Z",
      "status": "active"
    }
  },
  "message": "Subscription updated successfully"
}
```

### Extend Subscription

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/subscription/extend`

**Description:** Extend a tenant's subscription by a specified number of days.

**Request:**
```http
POST /api/v1/admin/tenants/1/subscription/extend
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "days": 30
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "subscription_ends_at": "2027-01-31T00:00:00Z",
      "extended_by_days": 30
    }
  },
  "message": "Subscription extended by 30 days"
}
```

### Cancel Subscription

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/subscription/cancel`

**Description:** Cancel a tenant's subscription (sets end of period cancellation).

**Request:**
```http
POST /api/v1/admin/tenants/1/subscription/cancel
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "reason": "Customer requested cancellation",
  "cancel_immediately": false
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "subscription_cancelled_at": "2026-03-25T10:00:00Z",
      "subscription_ends_at": "2026-12-31T23:59:59Z",
      "cancellation_reason": "Customer requested cancellation"
    }
  },
  "message": "Subscription cancelled. Access continues until end of billing period."
}
```

### Convert Trial to Paid

**Endpoint:** `POST /api/v1/admin/tenants/{tenantId}/convert-to-paid`

**Description:** Convert a trial tenant to a paid subscription.

**Request:**
```http
POST /api/v1/admin/tenants/1/convert-to-paid
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "subscription_plan": "premium",
  "subscription_ends_at": "2027-03-25T00:00:00Z"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": 1,
      "status": "active",
      "subscription_plan": "premium",
      "subscription_ends_at": "2027-03-25T00:00:00Z",
      "trial_ends_at": null,
      "converted_at": "2026-03-25T10:00:00Z"
    }
  },
  "message": "Tenant converted to paid subscription successfully"
}
```

---

## Rate Limiting

Super Admin endpoints have the following rate limits:

| Endpoint Group | Rate Limit | Notes |
|----------------|------------|-------|
| `/api/v1/admin/auth/*` | 200 requests/minute | Authentication endpoints |
| `/api/v1/admin/*` (general) | 200 requests/minute | Most admin endpoints |
| `/api/v1/admin/settings/run-command` | 60 requests/minute | Command execution |

Rate limits are higher than tenant API (60 requests/minute) to accommodate system-wide management operations.

---

## System Dashboard

### System Overview

**Endpoint:** `GET /api/v1/admin/dashboard`

**Description:** Retrieve system-wide overview metrics.

**Request:**
```http
GET /api/v1/admin/dashboard
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenants": {
      "total": 150,
      "active": 142,
      "on_trial": 8,
      "expiring_soon": 3,
      "suspended": 5
    },
    "users": {
      "total": 1250,
      "active": 1180,
      "inactive": 70
    },
    "resources": {
      "total_stores": 320,
      "total_warehouses": 85,
      "total_products": 5600,
      "total_customers": 8900
    },
    "activity": {
      "orders_today": 423,
      "orders_this_week": 2850,
      "orders_this_month": 11500
    },
    "revenue": {
      "mrr": 14058.00,
      "arr": 168696.00,
      "currency": "USD",
      "growth_rate": 12.5
    },
    "alerts": {
      "critical": 2,
      "warning": 5,
      "info": 12
    }
  }
}
```

### Revenue Metrics

**Endpoint:** `GET /api/v1/admin/dashboard/revenue`

**Description:** Retrieve detailed revenue metrics.

**Request:**
```http
GET /api/v1/admin/dashboard/revenue?period=12
Authorization: Bearer {super-admin-token}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `period` | integer | 12 | Number of months to include |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_mrr": 14058.00,
    "previous_mrr": 12500.00,
    "growth_rate": 12.5,
    "arr": 168696.00,
    "currency": "USD",
    "monthly_breakdown": [
      {
        "month": "2025-04",
        "mrr": 12500.00,
        "new_business": 1500.00,
        "expansion": 500.00,
        "churn": -200.00
      },
      {
        "month": "2025-05",
        "mrr": 13200.00,
        "new_business": 1200.00,
        "expansion": 300.00,
        "churn": -100.00
      }
    ],
    "by_plan": [
      {
        "plan": "basic",
        "tenants": 50,
        "mrr": 2500.00
      },
      {
        "plan": "premium",
        "tenants": 75,
        "mrr": 7500.00
      },
      {
        "plan": "enterprise",
        "tenants": 25,
        "mrr": 4058.00
      }
    ]
  }
}
```

### Usage Statistics

**Endpoint:** `GET /api/v1/admin/dashboard/usage`

**Description:** Retrieve platform usage statistics.

**Request:**
```http
GET /api/v1/admin/dashboard/usage?period=30
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "api_requests": {
      "total": 1250000,
      "today": 45000,
      "average_per_day": 41667
    },
    "active_users": {
      "today": 850,
      "this_week": 1100,
      "this_month": 1180
    },
    "resource_usage": {
      "total_orders": 125000,
      "orders_today": 423,
      "total_products": 5600,
      "products_created_today": 15
    },
    "storage": {
      "database_size_mb": 2048,
      "file_storage_mb": 512,
      "log_storage_mb": 128
    },
    "top_tenants_by_activity": [
      {
        "tenant_id": 1,
        "tenant_name": "Acme Corporation",
        "api_requests": 15000,
        "orders": 450
      },
      {
        "tenant_id": 2,
        "tenant_name": "TechStart Inc",
        "api_requests": 12000,
        "orders": 380
      }
    ]
  }
}
```

### System Alerts

**Endpoint:** `GET /api/v1/admin/dashboard/alerts`

**Description:** Retrieve system alerts and notifications.

**Request:**
```http
GET /api/v1/admin/dashboard/alerts
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "alerts": [
      {
        "id": 1,
        "level": "critical",
        "title": "Database Connection Pool Near Limit",
        "message": "Connection pool usage at 95%",
        "created_at": "2026-03-25T09:30:00Z"
      },
      {
        "id": 2,
        "level": "warning",
        "title": "High API Error Rate",
        "message": "Error rate increased to 5% in last hour",
        "created_at": "2026-03-25T09:00:00Z"
      },
      {
        "id": 3,
        "level": "info",
        "title": "Scheduled Maintenance",
        "message": "Database maintenance scheduled for weekend",
        "created_at": "2026-03-24T10:00:00Z"
      }
    ],
    "summary": {
      "critical": 2,
      "warning": 5,
      "info": 12
    }
  }
}
```

---

## User Management

### List All Users

**Endpoint:** `GET /api/v1/admin/users`

**Description:** Search and list users across all tenants.

**Request:**
```http
GET /api/v1/admin/users?tenant_id=1&role=admin&search=john&is_active=true&per_page=20
Authorization: Bearer {super-admin-token}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tenant_id` | integer | - | Filter by tenant |
| `role` | string | - | Filter by role |
| `search` | string | - | Search by name or email |
| `is_active` | boolean | - | Filter by active status |
| `per_page` | integer | 20 | Items per page |
| `page` | integer | 1 | Page number |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "tenant_id": 1,
        "tenant_name": "Acme Corporation",
        "name": "John Doe",
        "email": "john@acme.com",
        "role": "admin",
        "is_active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "last_login_at": "2026-03-25T08:00:00Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 52
  }
}
```

### Get User Details

**Endpoint:** `GET /api/v1/admin/users/{userId}`

**Description:** View detailed information about a specific user.

**Request:**
```http
GET /api/v1/admin/users/1
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "tenant_id": 1,
      "tenant_name": "Acme Corporation",
      "name": "John Doe",
      "email": "john@acme.com",
      "role": "admin",
      "is_active": true,
      "store_id": null,
      "warehouse_id": null,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "last_login_at": "2026-03-25T08:00:00Z",
      "permissions": [
        "users.manage",
        "products.create",
        "products.edit",
        "orders.manage"
      ]
    }
  }
}
```

### Generate Impersonation Token

**Endpoint:** `POST /api/v1/admin/users/{userId}/impersonate`

**Description:** Generate a short-lived token to impersonate a user (for support).

**Request:**
```http
POST /api/v1/admin/users/1/impersonate
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "impersonation_token": "imp_abc123xyz...",
    "expires_at": "2026-03-25T10:15:00Z",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@acme.com",
      "tenant_id": 1,
      "tenant_name": "Acme Corporation"
    }
  },
  "message": "Impersonation token generated. Valid for 15 minutes."
}
```

**Usage:**
```bash
curl -X GET http://localhost:8000/api/v1/tenants/1/stores \
  -H "Authorization: Bearer imp_abc123xyz..."
```

### Stop Impersonating

**Endpoint:** `POST /api/v1/admin/users/stop-impersonating`

**Description:** Stop current impersonation session.

**Request:**
```http
POST /api/v1/admin/users/stop-impersonating
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Impersonation session ended"
}
```

### Get Impersonation Sessions

**Endpoint:** `GET /api/v1/admin/users/{userId}/impersonation-sessions`

**Description:** View impersonation session history for a user.

**Request:**
```http
GET /api/v1/admin/users/1/impersonation-sessions
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "sessions": [
      {
        "id": 1,
        "super_admin_id": 1,
        "super_admin_name": "System Administrator",
        "user_id": 1,
        "started_at": "2026-03-25T09:00:00Z",
        "ended_at": "2026-03-25T09:15:00Z",
        "ip_address": "192.168.1.100"
      }
    ]
  }
}
```

### Revoke Impersonation Tokens

**Endpoint:** `POST /api/v1/admin/users/{userId}/revoke-impersonation`

**Description:** Revoke all active impersonation tokens for a user.

**Request:**
```http
POST /api/v1/admin/users/1/revoke-impersonation
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "All impersonation tokens revoked for user"
}
```

---

## System Configuration

### Get System Settings

**Endpoint:** `GET /api/v1/admin/settings`

**Description:** Retrieve current system-wide settings.

**Request:**
```http
GET /api/v1/admin/settings
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "settings": {
      "app_name": "POS WMS",
      "app_url": "https://poswms.com",
      "maintenance_mode": false,
      "registration_enabled": true,
      "default_trial_days": 30,
      "max_tenants": 1000,
      "rate_limits": {
        "auth": 10,
        "api": 60,
        "api_admin": 120
      }
    }
  }
}
```

### Update System Settings

**Endpoint:** `PUT /api/v1/admin/settings`

**Description:** Update system-wide settings.

**Request:**
```http
PUT /api/v1/admin/settings
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "maintenance_mode": true,
  "registration_enabled": false
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "settings": {
      "maintenance_mode": true,
      "registration_enabled": false
    }
  },
  "message": "Settings updated successfully"
}
```

### Clear Cache

**Endpoint:** `POST /api/v1/admin/settings/clear-cache`

**Description:** Clear application cache.

**Request:**
```http
POST /api/v1/admin/settings/clear-cache
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Cache cleared successfully"
}
```

### System Health Check

**Endpoint:** `GET /api/v1/admin/settings/health`

**Description:** Perform system health check.

**Request:**
```http
GET /api/v1/admin/settings/health
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "checks": {
      "database": {
        "status": "healthy",
        "response_time_ms": 5
      },
      "cache": {
        "status": "healthy",
        "response_time_ms": 2
      },
      "queue": {
        "status": "healthy",
        "pending_jobs": 15
      },
      "storage": {
        "status": "healthy",
        "disk_usage_percent": 45
      }
    }
  }
}
```

### Run Artisan Command

**Endpoint:** `POST /api/v1/admin/settings/run-command`

**Description:** Execute an Artisan command (restricted to safe commands).

**Request:**
```http
POST /api/v1/admin/settings/run-command
Authorization: Bearer {super-admin-token}
Content-Type: application/json

{
  "command": "cache:clear"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "command": "cache:clear",
    "output": "INFO  Cache cleared successfully.\n"
  },
  "message": "Command executed successfully"
}
```

---

## Global Audit Logs

### List Global Audit Logs

**Endpoint:** `GET /api/v1/admin/audit-logs`

**Description:** View audit logs across all tenants.

**Request:**
```http
GET /api/v1/admin/audit-logs?event_type=created&user_id=1&start_date=2026-01-01&end_date=2026-03-31&per_page=20
Authorization: Bearer {super-admin-token}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `event_type` | string | - | Filter by event type |
| `user_id` | integer | - | Filter by user |
| `tenant_id` | integer | - | Filter by tenant |
| `start_date` | date | - | Filter by start date |
| `end_date` | date | - | Filter by end date |
| `per_page` | integer | 20 | Items per page |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "audit_logs": [
      {
        "id": 1,
        "tenant_id": 1,
        "tenant_name": "Acme Corporation",
        "user_id": 1,
        "user_name": "John Doe",
        "event_type": "created",
        "auditable_type": "App\\Models\\Product",
        "auditable_id": 123,
        "changes": {
          "name": ["New Product"],
          "sku": ["PROD-001"],
          "base_price": [29.99]
        },
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "created_at": "2026-03-25T10:00:00Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 50,
    "per_page": 20,
    "total": 1000
  }
}
```

### Get Audit Summary

**Endpoint:** `GET /api/v1/admin/audit-logs/summary`

**Description:** Get summary statistics of audit logs.

**Request:**
```http
GET /api/v1/admin/audit-logs/summary?start_date=2026-01-01&end_date=2026-03-31
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2026-01-01",
      "end_date": "2026-03-31"
    },
    "total_events": 15000,
    "by_event_type": {
      "created": 5000,
      "updated": 8000,
      "deleted": 2000
    },
    "by_resource": {
      "products": 4000,
      "orders": 6000,
      "customers": 2000,
      "inventory": 3000
    },
    "top_users": [
      {
        "user_id": 1,
        "user_name": "John Doe",
        "events_count": 500
      }
    ]
  }
}
```

### Get Logs by User

**Endpoint:** `GET /api/v1/admin/audit-logs/by-user/{userId}`

**Description:** Get all audit logs for a specific user.

**Request:**
```http
GET /api/v1/admin/audit-logs/by-user/1?per_page=20
Authorization: Bearer {super-admin-token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@acme.com"
    },
    "audit_logs": [...],
    "meta": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    }
  }
}
```

---

## Error Handling

### Common Error Responses

**401 Unauthorized:**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "Unauthenticated."
  }
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "User does not have super admin privileges."
  }
}
```

**404 Not Found:**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Tenant not found."
  }
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "subscription_plan": ["The selected plan is invalid."]
    }
  }
}
```

**429 Rate Limited:**
```json
{
  "success": false,
  "error": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Too many requests. Please try again in 60 seconds.",
    "retry_after": 60
  }
}
```

---

## Security Considerations

### Audit Logging

All Super Admin actions are automatically logged with:
- IP address
- User agent
- Request URL
- Changes made (old/new values)
- Timestamp

### Impersonation Security

- Impersonation tokens are short-lived (15 minutes max)
- All impersonation sessions are logged
- Super admins can revoke impersonation tokens
- Users can view their impersonation history

### Data Protection

- Tenants are soft-deleted (data preserved for compliance)
- No cascade deletes (referential integrity maintained)
- Sensitive operations require re-authentication
- All API access is rate-limited

### Best Practices

1. **Use HTTPS**: Always use HTTPS in production
2. **Token Security**: Store tokens securely, never expose in logs
3. **Least Privilege**: Only grant super admin access when necessary
4. **Audit Regularly**: Review audit logs for suspicious activity
5. **Rotate Tokens**: Regularly rotate API tokens

---

## Code Examples

### JavaScript (Axios) - Tenant Management

```javascript
const axios = require('axios');

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
});

// Super Admin Login
async function superAdminLogin(email, password) {
  const response = await api.post('/admin/auth/login', { email, password });
  return response.data.data.token;
}

// Create Tenant
async function createTenant(token, tenantData) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  const response = await api.post('/admin/tenants', tenantData);
  return response.data;
}

// Get Dashboard Metrics
async function getDashboard(token) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  const response = await api.get('/admin/dashboard');
  return response.data;
}

// Impersonate User
async function impersonateUser(token, userId) {
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  const response = await api.post(`/admin/users/${userId}/impersonate`);
  return response.data.data.impersonation_token;
}

// Usage
(async () => {
  const token = await superAdminLogin('superadmin@poswms.com', 'password');
  
  const tenant = await createTenant(token, {
    name: 'New Business',
    slug: 'new-business',
    company_name: 'New Business Inc.',
    email: 'contact@newbusiness.com',
    subscription_plan: 'premium'
  });
  
  console.log('Created tenant:', tenant.data.tenant);
  
  const dashboard = await getDashboard(token);
  console.log('System metrics:', dashboard.data);
})();
```

### PHP - Subscription Management

```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000/api/v1/',
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

// Login
$response = $client->post('admin/auth/login', [
    'json' => [
        'email' => 'superadmin@poswms.com',
        'password' => 'password'
    ]
]);

$token = json_decode($response->getBody(), true)['data']['token'];

// Extend Trial
$response = $client->post('admin/tenants/1/trial/extend', [
    'headers' => ['Authorization' => 'Bearer ' . $token],
    'json' => ['days' => 14]
]);

$result = json_decode($response->getBody(), true);
echo "Trial extended: " . $result['message'] . "\n";

// Get Tenant Stats
$response = $client->get('admin/tenants/1/stats', [
    'headers' => ['Authorization' => 'Bearer ' . $token]
]);

$stats = json_decode($response->getBody(), true);
echo "Tenant has {$stats['data']['tenant']['stats']['total_users']} users\n";
```

### Python - System Monitoring

```python
import requests

BASE_URL = 'http://localhost:8000/api/v1'

def super_admin_login(email, password):
    response = requests.post(f'{BASE_URL}/admin/auth/login', json={
        'email': email,
        'password': password
    })
    return response.json()['data']['token']

def get_system_health(token):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(f'{BASE_URL}/admin/settings/health', headers=headers)
    return response.json()

def get_alerts(token):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(f'{BASE_URL}/admin/dashboard/alerts', headers=headers)
    return response.json()

def suspend_tenant(token, tenant_id, reason):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.post(
        f'{BASE_URL}/admin/tenants/{tenant_id}/suspend',
        headers=headers,
        json={'reason': reason}
    )
    return response.json()

# Usage
token = super_admin_login('superadmin@poswms.com', 'password')

health = get_system_health(token)
print(f"System status: {health['data']['status']}")

alerts = get_alerts(token)
print(f"Critical alerts: {alerts['data']['summary']['critical']}")

# Suspend non-paying tenant
result = suspend_tenant(token, 1, 'Non-payment for 60 days')
print(result['message'])
```

---

## Support

For Super Admin API support:
- **Email:** superadmin-support@poswms.com
- **Priority:** High (Super Admin issues are prioritized)
- **SLA:** 4-hour response time
