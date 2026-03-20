# Authentication Endpoints

Base URL: `/api/v1`

---

## Login

Authenticate user and receive access token.

**Endpoint:**
```
POST /api/v1/auth/login
```

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "store_manager",
      "store_id": 1,
      "warehouse_id": null,
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    },
    "token": "1|abc123xyz...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

**Response (401 Unauthorized):**
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

---

## Logout

Revoke the current authentication token.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/auth/logout
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
  "message": "Logout successful"
}
```

---

## Refresh Token

Revoke current token and issue a new one.

**Endpoint:**
```
POST /api/v1/tenants/{tenant_id}/auth/refresh
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
    "token": "2|def456uvw...",
    "token_type": "Bearer"
  },
  "message": "Token refreshed successfully"
}
```

---

## Get Current User

Retrieve authenticated user details.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/auth/me
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
    "user": {
      "id": 1,
      "tenant_id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "store_manager",
      "store_id": 1,
      "warehouse_id": null,
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  },
  "message": "User retrieved successfully"
}
```

---

## Usage Example (JavaScript)

```javascript
// Login
const loginResponse = await fetch('/api/v1/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123',
  }),
});

const { data } = await loginResponse.json();
const token = data.token;

// Use token in subsequent requests
const storesResponse = await fetch('/api/v1/tenants/1/stores', {
  headers: {
    'Authorization': `Bearer ${token}`,
  },
});
```

## Usage Example (cURL)

```bash
# Login
curl -X POST https://api.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Get stores
curl -X GET https://api.example.com/api/v1/tenants/1/stores \
  -H "Authorization: Bearer 1|abc123xyz..."
```
