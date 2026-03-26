## **POS WMS Backend API - Endpoint Categorization**

### **📊 Summary Statistics**
- **Total Endpoints:** 90 unique paths
- **Total Operations:** ~147 HTTP operations
- **API Version:** v1
- **Base Path:** `/api/v1/`

---

## **1. 🔐 Authentication (4 endpoints)**
Standard user authentication within tenant context.

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/v1/auth/login` | Login user | No |
| POST | `/api/v1/tenants/{tenant_id}/auth/logout` | Logout user | Yes |
| POST | `/api/v1/tenants/{tenant_id}/auth/refresh` | Refresh token | Yes |
| GET | `/api/v1/tenants/{tenant_id}/auth/me` | Get current user | Yes |

---

## **2. 🏪 Stores (5 endpoints)**
Store/location management for retail outlets.

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/stores` | List stores | Yes |
| POST | `/api/v1/tenants/{tenant_id}/stores` | Create store | Yes |
| GET | `/api/v1/tenants/{tenant_id}/stores/{storeId}` | Get store | Yes |
| PUT | `/api/v1/tenants/{tenant_id}/stores/{storeId}` | Update store | Yes |
| DELETE | `/api/v1/tenants/{tenant_id}/stores/{storeId}` | Delete store | Yes |

---

## **3. 🏭 Warehouses (5 endpoints)**
Warehouse management for storage facilities.

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/warehouses` | List warehouses | Yes |
| POST | `/api/v1/tenants/{tenant_id}/warehouses` | Create warehouse | Yes |
| GET | `/api/v1/tenants/{tenant_id}/warehouses/{warehouseId}` | Get warehouse | Yes |
| PUT | `/api/v1/tenants/{tenant_id}/warehouses/{warehouseId}` | Update warehouse | Yes |
| DELETE | `/api/v1/tenants/{tenant_id}/warehouses/{warehouseId}` | Delete warehouse | Yes |

---

## **4. 📦 Products (6 endpoints)**
Product catalog management.

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/v1/tenants/{tenant_id}/products` | List products | `products.view` |
| POST | `/api/v1/tenants/{tenant_id}/products` | Create product | `products.create` |
| GET | `/api/v1/tenants/{tenant_id}/products/{productId}` | Get product | `products.view` |
| PUT | `/api/v1/tenants/{tenant_id}/products/{productId}` | Update product | `products.edit` |
| DELETE | `/api/v1/tenants/{tenant_id}/products/{productId}` | Delete product | `products.delete` |

**Query Parameters:** `category_id`, `search`, `active`, `per_page`, `page`

---

## **5. 🏷️ Categories (6 endpoints)**
Product categorization system.

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/v1/tenants/{tenant_id}/categories` | List categories | `categories.view` |
| POST | `/api/v1/tenants/{tenant_id}/categories` | Create category | `categories.create` |
| GET | `/api/v1/tenants/{tenant_id}/categories/{categoryId}` | Get category | `categories.view` |
| PUT | `/api/v1/tenants/{tenant_id}/categories/{categoryId}` | Update category | `categories.edit` |
| DELETE | `/api/v1/tenants/{tenant_id}/categories/{categoryId}` | Delete category | `categories.delete` |

**Query Parameters:** `parent_id`, `search`, `active`, `per_page`, `page`

---

## **6. 📊 Inventory (8 endpoints)**
Stock tracking and transfers.

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/v1/tenants/{tenant_id}/inventory` | List inventory | `inventory.view` |
| POST | `/api/v1/tenants/{tenant_id}/inventory` | Create inventory | `inventory.create` |
| GET | `/api/v1/tenants/{tenant_id}/inventory/{inventoryId}` | Get inventory | `inventory.view` |
| PUT | `/api/v1/tenants/{tenant_id}/inventory/{inventoryId}` | Update inventory | `inventory.edit` |
| DELETE | `/api/v1/tenants/{tenant_id}/inventory/{inventoryId}` | Delete inventory | `inventory.delete` |
| POST | `/api/v1/tenants/{tenant_id}/inventory/transfer` | Transfer stock | `inventory.edit` |
| GET | `/api/v1/tenants/{tenant_id}/inventory/product/{productId}/transferable` | Get transferable qty | `inventory.view` |

**Query Parameters:** `warehouse_id`, `store_id`, `product_id`, `per_page`, `page`

---

## **7. 🛒 Orders (10 endpoints)**
Order processing and fulfillment.

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/v1/tenants/{tenant_id}/orders` | List orders | `orders.view` |
| POST | `/api/v1/tenants/{tenant_id}/orders` | Create order | `orders.create` |
| GET | `/api/v1/tenants/{tenant_id}/orders/{orderId}` | Get order | `orders.view` |
| PUT | `/api/v1/tenants/{tenant_id}/orders/{orderId}` | Update order | `orders.edit` |
| DELETE | `/api/v1/tenants/{tenant_id}/orders/{orderId}` | Delete order | `orders.delete` |
| POST | `/api/v1/tenants/{tenant_id}/orders/{orderId}/confirm` | Confirm order | `orders.edit` |
| POST | `/api/v1/tenants/{tenant_id}/orders/{orderId}/fulfill` | Fulfill order | `orders.edit` |
| POST | `/api/v1/tenants/{tenant_id}/orders/{orderId}/cancel` | Cancel order | `orders.edit` |

**Query Parameters:** `status`, `store_id`, `customer_id`, `per_page`, `page`

---

## **8. 👥 Customers (5 endpoints)**
Customer relationship management.

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/v1/tenants/{tenant_id}/customers` | List customers | `customers.view` |
| POST | `/api/v1/tenants/{tenant_id}/customers` | Create customer | `customers.create` |
| GET | `/api/v1/tenants/{tenant_id}/customers/{customerId}` | Get customer | `customers.view` |
| PUT | `/api/v1/tenants/{tenant_id}/customers/{customerId}` | Update customer | `customers.edit` |
| DELETE | `/api/v1/tenants/{tenant_id}/customers/{customerId}` | Delete customer | `customers.delete` |

**Query Parameters:** `search`, `active`, `per_page`, `page`

---

## **9. 💰 Pricing (8 endpoints)**
Tiered pricing and price calculation.

| Method | Endpoint | Description | Role/Permission |
|--------|----------|-------------|-----------------|
| GET | `/api/v1/tenants/{tenant_id}/pricing-tiers` | List pricing tiers | `admin` role |
| POST | `/api/v1/tenants/{tenant_id}/pricing-tiers` | Create pricing tier | `admin` role |
| GET | `/api/v1/tenants/{tenant_id}/pricing-tiers/{pricingTier}` | Get pricing tier | `admin` role |
| PUT | `/api/v1/tenants/{tenant_id}/pricing-tiers/{pricingTier}` | Update pricing tier | `admin` role |
| DELETE | `/api/v1/tenants/{tenant_id}/pricing-tiers/{pricingTier}` | Delete pricing tier | `admin` role |
| GET | `/api/v1/tenants/{tenant_id}/pricing-rules` | List pricing rules | `admin` role |
| POST | `/api/v1/tenants/{tenant_id}/pricing-rules` | Create pricing rule | `admin` role |
| GET/PUT/DELETE | `/api/v1/tenants/{tenant_id}/pricing-rules/{pricingRule}` | Pricing rule CRUD | `admin` role |
| POST | `/api/v1/tenants/{tenant_id}/prices/calculate` | Calculate product price | `prices.view` |
| POST | `/api/v1/tenants/{tenant_id}/prices/calculate-cart` | Calculate cart total | `prices.view` |

---

## **10. 🔐 Roles & Permissions (11 endpoints)**
RBAC system management.

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/roles` | List roles | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/roles` | Create role | `admin` |
| GET/PUT/DELETE | `/api/v1/tenants/{tenant_id}/roles/{role}` | Role CRUD | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/permissions` | List permissions | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/permissions` | Create permission | `admin` |
| GET/PUT/DELETE | `/api/v1/tenants/{tenant_id}/permissions/{permission}` | Permission CRUD | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/users/{userId}/assign-role` | Assign role to user | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/users/{userId}/remove-role/{roleId}` | Remove role from user | `admin` |

---

## **11. 📈 Reports & Dashboard (20 endpoints)**
Analytics, reporting, and exports.

### Dashboard Metrics
| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/dashboard` | Unified dashboard | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/dashboard` | Sales dashboard | `admin` |

### Sales Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/revenue` | Revenue report |
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/orders-by-period` | Orders by period |
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/top-products` | Top products |

### Inventory Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/low-stock` | Low stock alerts |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory` | Inventory report |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/stock-levels` | Stock levels |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/movements` | Stock movements |

### Export Endpoints (CSV)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/export/revenue` | Export revenue |
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/export/orders-by-period` | Export orders |
| GET | `/api/v1/tenants/{tenant_id}/reports/sales/export/top-products` | Export products |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/export/stock-levels` | Export stock |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/export/movements` | Export movements |
| GET | `/api/v1/tenants/{tenant_id}/reports/inventory/export/low-stock` | Export low stock |

---

## **12. 🔗 Webhooks & Audit (11 endpoints)**
Webhook management and audit logging.

### Webhooks
| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/webhooks` | List webhooks | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/webhooks` | Create webhook | `admin` |
| GET/PUT/DELETE | `/api/v1/tenants/{tenant_id}/webhooks/{webhook}` | Webhook CRUD | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/webhooks/{webhook}/test` | Test webhook | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/webhooks/{webhook}/attempts` | Delivery attempts | `admin` |
| POST | `/api/v1/tenants/{tenant_id}/webhooks/{webhook}/retry` | Retry webhook | `admin` |

### Audit Logs
| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/api/v1/tenants/{tenant_id}/audit-logs` | List audit logs | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/audit-logs/{auditLog}` | Get audit log | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/audit-logs/summary` | Audit summary | `admin` |
| GET | `/api/v1/tenants/{tenant_id}/audit-logs/by-user/{userId}` | Logs by user | `admin` |

---

## **13. 👑 Super Admin (32 endpoints)**
System-wide administration (multi-tenant SaaS control).

### Super Admin Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/admin/auth/login` | Super admin login |
| POST | `/api/v1/admin/auth/logout` | Super admin logout |
| GET | `/api/v1/admin/auth/me` | Get current super admin |

### Tenant Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/tenants` | List all tenants |
| POST | `/api/v1/admin/tenants` | Create tenant |
| GET/PUT/DELETE | `/api/v1/admin/tenants/{tenantId}` | Tenant CRUD |
| POST | `/api/v1/admin/tenants/{tenantId}/activate` | Activate tenant |
| POST | `/api/v1/admin/tenants/{tenantId}/suspend` | Suspend tenant |
| GET | `/api/v1/admin/tenants/{tenantId}/stats` | Tenant statistics |
| POST | `/api/v1/admin/tenants/{tenantId}/trial` | Update trial |
| POST | `/api/v1/admin/tenants/{tenantId}/trial/extend` | Extend trial |
| POST | `/api/v1/admin/tenants/{tenantId}/subscription` | Update subscription |
| POST | `/api/v1/admin/tenants/{tenantId}/subscription/extend` | Extend subscription |
| POST | `/api/v1/admin/tenants/{tenantId}/subscription/cancel` | Cancel subscription |
| POST | `/api/v1/admin/tenants/{tenantId}/convert-to-paid` | Convert to paid |

### System Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/dashboard` | System dashboard |
| GET | `/api/v1/admin/dashboard/revenue` | Revenue metrics |
| GET | `/api/v1/admin/dashboard/usage` | Usage analytics |
| GET | `/api/v1/admin/dashboard/alerts` | System alerts |

### Global Audit Logs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/audit-logs` | Global audit logs |
| GET | `/api/v1/admin/audit-logs/summary` | Global summary |
| GET | `/api/v1/admin/audit-logs/by-user/{userId}` | Global logs by user |

### User Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/users` | Search users |
| GET | `/api/v1/admin/users/{userId}` | View user |
| POST | `/api/v1/admin/users/{userId}/impersonate` | Impersonate user |
| POST | `/api/v1/admin/users/stop-impersonating` | Stop impersonation |
| GET | `/api/v1/admin/users/{userId}/impersonation-sessions` | Get sessions |
| POST | `/api/v1/admin/users/{userId}/revoke-impersonation` | Revoke tokens |

### System Settings
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/PUT | `/api/v1/admin/settings` | System config |
| POST | `/api/v1/admin/settings/clear-cache` | Clear cache |
| GET | `/api/v1/admin/settings/health` | Health check |
| POST | `/api/v1/admin/settings/run-command` | Run artisan command |

---

## **🔒 Security Model Summary**

### Authentication Types:
1. **Bearer Token** (Laravel Sanctum) - Most endpoints
2. **No Auth** - Public login endpoints

### Authorization Levels:
1. **Role-based (`x-role`)**: `admin`, `super_admin`, `manager`
2. **Permission-based (`x-permission`)**: Granular permissions like `products.view`, `orders.create`

### Multi-Tenancy:
- Most endpoints scoped by `tenant_id` path parameter
- Super admin endpoints operate globally across all tenants

---

## **📋 Authorization & Access Control Guide**

### Understanding `x-role` (Role-Based Access)

The `x-role` extension defines which **user roles** can access an endpoint. Roles are assigned to users and provide broad access levels.

#### Syntax & Behavior:

```yaml
x-role: [admin]
# User must have the 'admin' role

x-role: [admin, manager]
# User must have EITHER 'admin' OR 'manager' role (OR logic)
```

#### How It Works:
- **Single role**: `x-role: [admin]` - Only users with the 'admin' role can access
- **Multiple roles**: `x-role: [admin, manager]` - Users with **any** of the listed roles can access
- **Evaluation**: The system checks if the authenticated user has **at least one** of the specified roles

#### When to Use `x-role`:

| Scenario | Example | Recommended Approach |
|----------|---------|---------------------|
| **Administrative functions** | User management, system settings | `x-role: [admin]` |
| **Management-level access** | Reports, dashboard analytics | `x-role: [admin, manager]` |
| **Cross-functional access** | Operations that multiple roles need | `x-role: [admin, manager, supervisor]` |
| **Super admin only** | System-wide operations | `x-role: [super_admin]` |

#### Endpoints Using `x-role`:

| Endpoint | Required Role | Use Case |
|----------|---------------|----------|
| `/api/v1/tenants/{tenant_id}/pricing-tiers/*` | `admin` | Pricing configuration |
| `/api/v1/tenants/{tenant_id}/pricing-rules/*` | `admin` | Pricing rules management |
| `/api/v1/tenants/{tenant_id}/roles/*` | `admin` | Role management |
| `/api/v1/tenants/{tenant_id}/permissions/*` | `admin` | Permission management |
| `/api/v1/tenants/{tenant_id}/users/{userId}/assign-role` | `admin` | User role assignment |
| `/api/v1/tenants/{tenant_id}/dashboard` | `admin` | Admin dashboard |
| `/api/v1/tenants/{tenant_id}/reports/*` | `admin` | All reports |
| `/api/v1/tenants/{tenant_id}/webhooks/*` | `admin` | Webhook management |
| `/api/v1/tenants/{tenant_id}/audit-logs/*` | `admin` | Audit log access |
| `/api/v1/admin/*` | `super_admin` | All super admin endpoints |

---

### Understanding `x-permission` (Permission-Based Access)

The `x-permission` extension defines which **specific permissions** are required to access an endpoint. This provides fine-grained access control within a feature area.

#### Syntax & Behavior:

```yaml
x-permission: [products.create]
# User must have the 'products.create' permission

x-permission: [products.create, products.edit]
# User must have AT LEAST ONE of the specified permissions (OR logic)
```

#### How It Works:
- **Single permission**: `x-permission: [products.view]` - User needs exactly this permission
- **Multiple permissions**: `x-permission: [products.create, products.edit]` - User needs **at least one** of the listed permissions
- **Evaluation**: The system checks if the user has **any** of the specified permissions (typically inherited from their assigned roles)

#### When to Use `x-permission`:

| Scenario | Example | Recommended Approach |
|----------|---------|---------------------|
| **CRUD operations** | Product management | Separate permissions: `products.view`, `products.create`, `products.edit`, `products.delete` |
| **Feature-specific access** | Order fulfillment | `orders.edit` for status changes |
| **Granular control** | Different users can do different actions | Use specific permissions per operation |
| **Flexible role composition** | Roles built from permission sets | Permissions are building blocks for roles |

#### Permission Groups:

Permissions are organized by feature area:

| Group | Permissions | Description |
|-------|-------------|-------------|
| **Products** | `products.view`, `products.create`, `products.edit`, `products.delete` | Product catalog operations |
| **Categories** | `categories.view`, `categories.create`, `categories.edit`, `categories.delete` | Category management |
| **Inventory** | `inventory.view`, `inventory.create`, `inventory.edit`, `inventory.delete` | Stock management |
| **Orders** | `orders.view`, `orders.create`, `orders.edit`, `orders.delete` | Order processing |
| **Customers** | `customers.view`, `customers.create`, `customers.edit`, `customers.delete` | Customer management |
| **Prices** | `prices.view` | Price calculation access |

#### Endpoints Using `x-permission`:

| Endpoint | Required Permission | Use Case |
|----------|---------------------|----------|
| `/api/v1/tenants/{tenant_id}/products` (GET) | `products.view` | View product list |
| `/api/v1/tenants/{tenant_id}/products` (POST) | `products.create` | Create new product |
| `/api/v1/tenants/{tenant_id}/products/{id}` (PUT) | `products.edit` | Update product |
| `/api/v1/tenants/{tenant_id}/products/{id}` (DELETE) | `products.delete` | Delete product |
| `/api/v1/tenants/{tenant_id}/categories/*` | `categories.*` | Category operations |
| `/api/v1/tenants/{tenant_id}/inventory/*` | `inventory.*` | Inventory operations |
| `/api/v1/tenants/{tenant_id}/orders/*` | `orders.*` | Order operations |
| `/api/v1/tenants/{tenant_id}/customers/*` | `customers.*` | Customer operations |
| `/api/v1/tenants/{tenant_id}/prices/calculate` | `prices.view` | Calculate prices |

---

### Role vs Permission: When to Use Which

| Factor | Use `x-role` | Use `x-permission` |
|--------|--------------|-------------------|
| **Access Scope** | Broad, cross-feature access | Specific, feature-level access |
| **Flexibility** | Fixed role assignments | Flexible, composable permissions |
| **Use Case** | Admin panels, reports, settings | CRUD operations, feature actions |
| **User Type** | Clear role distinctions (admin, manager) | Fine-grained access within teams |
| **Maintenance** | Easier for simple hierarchies | Better for complex permission matrices |

---

### Authorization Flow

```
┌─────────────────────────────────────────────────────────────┐
│  1. User makes authenticated request with Bearer token      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  2. Laravel Sanctum validates token & loads user            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  3. Middleware checks endpoint for x-role or x-permission   │
└─────────────────────────────────────────────────────────────┘
                              ↓
         ┌────────────────────┴────────────────────┐
         ↓                                         ↓
┌─────────────────────┐                   ┌─────────────────────┐
│  x-role defined     │                   │  x-permission       │
│  Check user roles   │                   │  Check permissions  │
└─────────────────────┘                   └─────────────────────┘
         ↓                                         ↓
┌─────────────────────┐                   ┌─────────────────────┐
│  Has ANY role in    │                   │  Has ANY permission │
│  the x-role array?  │                   │  in the array?      │
└─────────────────────┘                   └─────────────────────┘
         ↓                                         ↓
         └────────────────────┬────────────────────┘
                              ↓
         ┌────────────────────┴────────────────────┐
         ↓                                         ↓
    ┌─────────┐                               ┌─────────┐
    │  YES    │                               │   NO    │
    │  Allow  │                               │  403    │
    │  Access │                               │  Forbidden │
    └─────────┘                               └─────────┘
```

---

### Implementation Examples

#### Example 1: Creating a Product (Permission-Based)

```http
POST /api/v1/tenants/1/products
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "New Product",
  "sku": "PRD-001"
}
```

**Authorization Check:**
- Endpoint requires: `x-permission: [products.create]`
- System checks: Does user have `products.create` permission?
- Result: Access granted if permission exists (typically via role assignment)

#### Example 2: Accessing Reports (Role-Based)

```http
GET /api/v1/tenants/1/reports/sales/revenue
Authorization: Bearer {token}
```

**Authorization Check:**
- Endpoint requires: `x-role: [admin]`
- System checks: Does user have `admin` role?
- Result: Access granted only to admin users

#### Example 3: Multiple Permissions (OR Logic)

```http
PUT /api/v1/tenants/1/products/123
Authorization: Bearer {token}
```

If endpoint has: `x-permission: [products.create, products.edit]`

**Authorization Check:**
- User with `products.create` only → ✅ Access granted
- User with `products.edit` only → ✅ Access granted
- User with both → ✅ Access granted
- User with neither → ❌ 403 Forbidden

---

### Best Practices

#### For API Consumers:
1. **Check response codes**: `403 Forbidden` means authorization failed
2. **Request appropriate scopes**: Ensure your user has the right roles/permissions
3. **Use service accounts**: For automated systems, create users with specific permissions
4. **Test with different roles**: Verify access levels during development

#### For API Designers:
1. **Use `x-role` for**: Admin functions, cross-cutting concerns, management features
2. **Use `x-permission` for**: CRUD operations, feature-specific actions, granular control
3. **Document requirements**: Clearly mark `x-role` and `x-permission` in OpenAPI specs
4. **Consider OR logic**: Multiple values mean "any of these" not "all of these"

---

### Quick Reference Table

| Access Pattern | OpenAPI Extension | Example | Who Can Access |
|----------------|-------------------|---------|----------------|
| Admin only | `x-role: [admin]` | Settings, user management | Admin users |
| Admin or Manager | `x-role: [admin, manager]` | Reports, analytics | Admin + Manager |
| View products | `x-permission: [products.view]` | List products | Anyone with view permission |
| Create or edit | `x-permission: [products.create, products.edit]` | Product forms | Creators + Editors |
| Super admin | `x-role: [super_admin]` | System-wide ops | Super admin only |
| Tenant admin | `x-role: [admin]` + tenant scope | Tenant settings | Tenant admins |

---

This categorization provides a complete overview of your POS WMS Backend API structure!
