# Roles & Permissions Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## Roles

### List Roles

Retrieve all roles for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/roles
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
    "roles": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "Admin",
        "slug": "admin",
        "description": "Full system access",
        "permissions": [
          "users.view",
          "users.create",
          "users.edit",
          "users.delete",
          "products.view",
          "products.create",
          "products.edit",
          "products.delete",
          "orders.view",
          "orders.create",
          "orders.edit",
          "orders.delete"
        ],
        "is_system": true,
        "users_count": 2,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "name": "Store Manager",
        "slug": "store-manager",
        "description": "Manage store operations",
        "permissions": [
          "products.view",
          "products.edit",
          "orders.view",
          "orders.create",
          "orders.edit",
          "inventory.view"
        ],
        "is_system": false,
        "users_count": 5,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  },
  "message": "Roles retrieved successfully"
}
```

---

### Create Role

Create a new role.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/roles
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Warehouse Manager",
  "slug": "warehouse-manager",
  "description": "Manage warehouse operations",
  "permissions": [
    "products.view",
    "inventory.view",
    "inventory.create",
    "inventory.edit",
    "orders.view",
    "orders.edit"
  ],
  "is_system": false
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `slug` | string | Yes | Max 255 characters, unique per tenant |
| `description` | string | No | - |
| `permissions` | array | No | Array of permission slugs |
| `is_system` | boolean | No | Default: false |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "role": {
      "id": 3,
      "tenant_id": 1,
      "name": "Warehouse Manager",
      "slug": "warehouse-manager",
      "description": "Manage warehouse operations",
      "permissions": [
        "products.view",
        "inventory.view",
        "inventory.create",
        "inventory.edit",
        "orders.view",
        "orders.edit"
      ],
      "is_system": false,
      "created_at": "2024-01-15T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Role created successfully"
}
```

---

### Get Role

Retrieve a specific role.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/roles/{role_id}
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
    "role": {
      "id": 1,
      "tenant_id": 1,
      "name": "Admin",
      "slug": "admin",
      "description": "Full system access",
      "permissions": [
        "users.view",
        "users.create",
        "users.edit",
        "users.delete",
        "products.view",
        "products.create",
        "products.edit",
        "products.delete",
        "orders.view",
        "orders.create",
        "orders.edit",
        "orders.delete"
      ],
      "is_system": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Role retrieved successfully"
}
```

---

### Update Role

Update an existing role.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/roles/{role_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Senior Store Manager",
  "description": "Manage multiple stores",
  "permissions": [
    "products.view",
    "products.create",
    "products.edit",
    "orders.view",
    "orders.create",
    "orders.edit",
    "inventory.view",
    "inventory.edit",
    "users.view"
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "role": {
      "id": 2,
      "tenant_id": 1,
      "name": "Senior Store Manager",
      "slug": "store-manager",
      "description": "Manage multiple stores",
      "permissions": [
        "products.view",
        "products.create",
        "products.edit",
        "orders.view",
        "orders.create",
        "orders.edit",
        "inventory.view",
        "inventory.edit",
        "users.view"
      ],
      "is_system": false,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Role updated successfully"
}
```

---

### Delete Role

Delete a role.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/roles/{role_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Role deleted successfully"
}
```

**Response (403 Forbidden):**
```json
{
  "success": false,
  "message": "Cannot delete system role."
}
```

---

### Assign Role to User

Assign a role to a user.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/users/{user_id}/assign-role
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "role_id": 2
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `role_id` | integer | Yes | Must exist in roles |

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Role assigned successfully."
}
```

---

### Remove Role from User

Remove a role from a user.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/users/{user_id}/remove-role/{role_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Role removed successfully."
}
```

---

## Permissions

### List Permissions

Retrieve all permissions for a tenant, optionally filtered by group.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/permissions
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `group` | string | No | Filter by permission group |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "permissions": [
      {
        "id": 1,
        "tenant_id": 1,
        "name": "View Users",
        "slug": "users.view",
        "group": "users",
        "description": "View user list and details",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "name": "Create Users",
        "slug": "users.create",
        "group": "users",
        "description": "Create new users",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 5,
        "tenant_id": 1,
        "name": "View Products",
        "slug": "products.view",
        "group": "products",
        "description": "View product list and details",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 6,
        "tenant_id": 1,
        "name": "Create Products",
        "slug": "products.create",
        "group": "products",
        "description": "Create new products",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  },
  "message": "Permissions retrieved successfully"
}
```

---

### Create Permission

Create a new permission.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/permissions
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Export Reports",
  "slug": "reports.export",
  "group": "reports",
  "description": "Export reports to CSV/PDF"
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `slug` | string | Yes | Max 255 characters, unique per tenant |
| `group` | string | Yes | Max 100 characters |
| `description` | string | No | - |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "permission": {
      "id": 20,
      "tenant_id": 1,
      "name": "Export Reports",
      "slug": "reports.export",
      "group": "reports",
      "description": "Export reports to CSV/PDF",
      "created_at": "2024-01-15T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Permission created successfully"
}
```

---

### Get Permission

Retrieve a specific permission.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/permissions/{permission_id}
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
    "permission": {
      "id": 1,
      "tenant_id": 1,
      "name": "View Users",
      "slug": "users.view",
      "group": "users",
      "description": "View user list and details",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Permission retrieved successfully"
}
```

---

### Update Permission

Update an existing permission.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/permissions/{permission_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "View and Export Users",
  "description": "View user list, details, and export"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "permission": {
      "id": 1,
      "tenant_id": 1,
      "name": "View and Export Users",
      "slug": "users.view",
      "group": "users",
      "description": "View user list, details, and export",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Permission updated successfully"
}
```

---

### Delete Permission

Delete a permission.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/permissions/{permission_id}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Permission deleted successfully."
}
```

---

## Permission Groups

Permissions are organized into groups for easier management:

| Group | Description |
|-------|-------------|
| `users` | User management permissions |
| `roles` | Role management permissions |
| `products` | Product management permissions |
| `categories` | Category management permissions |
| `inventory` | Inventory management permissions |
| `orders` | Order management permissions |
| `customers` | Customer management permissions |
| `stores` | Store management permissions |
| `warehouses` | Warehouse management permissions |
| `pricing` | Pricing management permissions |
| `reports` | Report access permissions |
| `settings` | System settings permissions |
