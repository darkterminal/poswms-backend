# Category Management Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## List Categories

Retrieve all categories for a tenant.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/categories
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
    "categories": [
      {
        "id": 1,
        "tenant_id": 1,
        "parent_id": null,
        "name": "Electronics",
        "slug": "electronics",
        "description": "Electronic devices and accessories",
        "image": "https://example.com/images/electronics.jpg",
        "sort_order": 1,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "parent_id": 1,
        "name": "Audio",
        "slug": "audio",
        "description": "Audio equipment and accessories",
        "image": null,
        "sort_order": 1,
        "active": true,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

## Create Category

Create a new category.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/categories
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "parent_id": 1,
  "name": "Audio",
  "slug": "audio",
  "description": "Audio equipment and accessories",
  "image": "https://example.com/images/audio.jpg",
  "sort_order": 1,
  "active": true
}
```

**Validation Rules:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `parent_id` | integer | No | Must exist in categories |
| `name` | string | Yes | Max 255 characters |
| `slug` | string | No | Auto-generated if not provided |
| `description` | string | No | - |
| `image` | string | No | - |
| `sort_order` | integer | No | Min: 0, Default: 0 |
| `active` | boolean | No | Default: true |

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 2,
      "tenant_id": 1,
      "parent_id": 1,
      "name": "Audio",
      "slug": "audio",
      "description": "Audio equipment and accessories",
      "image": "https://example.com/images/audio.jpg",
      "sort_order": 1,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "Category created successfully"
}
```

---

## Get Category

Retrieve a specific category with its relationships.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/categories/{categoryId}
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
    "category": {
      "id": 1,
      "tenant_id": 1,
      "parent_id": null,
      "name": "Electronics",
      "slug": "electronics",
      "description": "Electronic devices and accessories",
      "image": "https://example.com/images/electronics.jpg",
      "sort_order": 1,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "parent": null,
      "children": [
        {
          "id": 2,
          "name": "Audio",
          "slug": "audio"
        }
      ],
      "products": [
        {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        }
      ]
    }
  }
}
```

---

## Update Category

Update an existing category.

**Endpoint:**
```
PUT /api/v1/tenants/{tenant_id}/categories/{categoryId}
```

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Audio Equipment",
  "sort_order": 2,
  "image": "https://example.com/images/audio-updated.jpg"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 2,
      "tenant_id": 1,
      "parent_id": 1,
      "name": "Audio Equipment",
      "slug": "audio-equipment",
      "description": "Audio equipment and accessories",
      "image": "https://example.com/images/audio-updated.jpg",
      "sort_order": 2,
      "active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T00:00:00Z"
    }
  },
  "message": "Category updated successfully"
}
```

---

## Delete Category

Delete a category.

**Endpoint:**
```
DELETE /api/v1/tenants/{tenant_id}/categories/{categoryId}
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Category deleted successfully"
}
```
