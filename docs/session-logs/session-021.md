# Session #21 - Database Seeders Implementation

**Date:** 2026-03-20  
**Duration:** ~1 hour  
**Phase:** Phase 8 - Production Readiness  
**Task:** 8.2 Database Seeders

---

## Objectives

- Create comprehensive database seeders for all entities
- Populate demo data for development and testing
- Handle multi-tenant unique constraints properly

---

## Work Completed

### Created/Updated Seeders

1. **TenantSeeder** (NEW)
   - Creates 3 demo tenants: Acme Corporation, TechMart Retail, Global Supplies
   - Each with different currencies, timezones, and settings
   - Creates 2 additional random tenants

2. **CountrySeeder** (UPDATED)
   - 46 countries with codes and phone codes
   - Global reference data (no tenant dependency)

3. **CurrencySeeder** (UPDATED)
   - 20 currencies with symbols and precision
   - Tenant-scoped with default currency per tenant

4. **RolePermissionSeeder** (UPDATED)
   - 18 permissions across 6 groups
   - 5 default roles: Admin, Manager, Warehouse Staff, Store Staff, Viewer
   - Handles multi-tenant unique constraints

5. **PricingTierSeeder** (UPDATED)
   - 4 tiers: Bronze, Silver, Gold, Wholesale
   - Tenant-scoped

6. **PricingRuleSeeder** (UPDATED)
   - Sample bulk discount rules per tier
   - Matches actual PricingRule model schema

7. **StoreSeeder** (UPDATED)
   - Main store per tenant + 2-5 random stores
   - Handles unique code constraint

8. **WarehouseSeeder** (UPDATED)
   - Main warehouse per tenant + 1-3 random warehouses
   - Handles unique code constraint

9. **CategorySeeder** (UPDATED)
   - 6 parent categories: Electronics, Clothing, Home & Garden, Sports & Outdoors, Office Supplies, Food & Beverages
   - 30 subcategories (5 per parent)
   - 5 random categories per tenant
   - Handles unique slug constraint

10. **ProductSeeder** (UPDATED)
    - 48 sample products across 4 categories
    - 20 additional random products per tenant
    - Creates inventory records for each product
    - Handles unique SKU constraint

11. **CustomerSeeder** (UPDATED)
    - 10 sample customers (mix of business and individual)
    - 15 random customers per tenant
    - Assigns pricing tiers
    - Handles unique email constraint

12. **InventorySeeder** (UPDATED)
    - Creates inventory for all products in main warehouse
    - Additional inventory in secondary warehouses
    - Creates low stock items for testing alerts
    - Matches actual Inventory model schema

13. **StockMovementSeeder** (UPDATED)
    - 3-10 movements per inventory item
    - Types: adjustment, in, out, sale, return, transfer
    - Tracks quantity_before and quantity_after
    - Matches actual StockMovement model schema

14. **OrderSeeder** (UPDATED)
    - 20 random orders with various statuses
    - 10 fulfilled historical orders
    - Creates order items for each order
    - Matches actual Order and OrderItem model schemas

### Model Updates

**Tenant.php** - Added relationships:
- `pricingTiers()`
- `categories()`
- `products()`
- `customers()`
- `inventories()`

### DatabaseSeeder.php (UPDATED)

Orchestrates all seeders in proper dependency order:
1. CountrySeeder (reference data)
2. TenantSeeder (core tenants)
3. CurrencySeeder (tenant-scoped reference)
4. RolePermissionSeeder (RBAC)
5. PricingTierSeeder + PricingRuleSeeder (pricing)
6. StoreSeeder + WarehouseSeeder (locations)
7. CategorySeeder (product organization)
8. ProductSeeder + CustomerSeeder (entities)
9. InventorySeeder + StockMovementSeeder (inventory)
10. OrderSeeder (orders)

---

## Issues Resolved

1. **Multi-tenant Unique Constraints**
   - Schema has both global unique (`->unique()`) and composite unique (`->unique(['tenant_id', 'slug'])`) constraints
   - This prevents same slugs/codes across tenants
   - Solution: Check existence, update if exists globally, or create with tenant-prefixed values

2. **Schema Mismatches**
   - Multiple seeders had field names that didn't match actual model schemas
   - Fixed: Inventory (removed min_stock, max_stock), StockMovement (type vs movement_type), Order (removed order_date), OrderItem (unit_price vs price), PricingRule (different schema entirely)

3. **Factory Unique Value Generation**
   - `fake()->unique()->word()` resets between iterations
   - Solution: Manually generate unique values with tenant prefix and timestamp

---

## Testing

```bash
# Fresh migrate and seed
php artisan migrate:fresh --no-interaction && php artisan db:seed --no-interaction

# Result: All seeders completed successfully
```

### Demo Data Summary (per tenant)
- 1 main store + 2-5 additional stores
- 1 main warehouse + 1-3 additional warehouses
- 6 parent categories + 30 subcategories + 5 random categories
- 48 sample products + 20 random products
- 10 sample customers + 15 random customers
- Inventory for all products
- 3-10 stock movements per inventory item
- 20 pending/processing orders + 10 fulfilled orders

### Demo Users Created
- `admin@demo.com` / `password` - Admin role
- `manager@demo.com` / `password` - Manager role
- `staff@demo.com` / `password` - Staff role

---

## Key Decisions

1. **Tenant-prefixed unique values**: For entities with global unique constraints (slug, code, SKU, email), prefixed values with tenant slug to ensure uniqueness across tenants.

2. **Sample data variety**: Created mix of predefined sample data (for consistency) and random data (for variety and stress testing).

3. **Low stock items**: Intentionally created some low stock inventory items to enable testing of low stock alerts.

4. **Historical orders**: Created fulfilled orders with past dates to enable testing of sales reports and analytics.

---

## Next Session Plan

- Task 8.3: Environment Configuration (Dev/Staging/Production configs)
- Task 8.4: CI/CD Pipeline
- Task 8.5: Performance Optimization
- Task 8.6: Security Hardening

---

## Notes

All seeders are idempotent - they can be run multiple times safely using `updateOrCreate` or manual existence checks. The seeders handle the schema's unique constraint design by checking for existing records globally before creating new ones.
