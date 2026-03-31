# Realistic Data Seeder

## Overview

The `RealisticDataSeeder` creates realistic business scenarios for a Multi-Store & Warehouse Management System (SaaS). Unlike fake data generators, this seeder simulates real-world conditions that exist in actual Indonesian retail and distribution businesses.

## What Gets Seeded

### Three Tenant Tiers

#### 1. **Starter Tier** - "Toko Sembako Jaya"
A small neighborhood grocery store in Yogyakarta.

**Characteristics:**
- Single warehouse (back storage)
- Single retail store
- 20 essential products (sembako - basic necessities)
- 30 local customers (neighborhood buyers)
- ~660 orders over 90 days (~8 orders/day)
- 2 users (admin + cashier)
- No VAT/tax (toko kelontong style)
- Trial subscription (15 days remaining)

**Business Flow:**
- Daily retail sales to individual customers
- Simple inventory management
- Cash and QRIS payments
- Local delivery within Yogyakarta area

**Sample Products:**
- Beras Premium 5kg (Rp 65,000)
- Mie Instan Goreng (Dus 40) (Rp 110,000)
- Minyak Goreng 2L (Rp 38,000)
- Gula Pasir 1kg (Rp 14,000)
- Telur Ayam 1kg (Rp 28,000)

---

#### 2. **Professional Tier** - "Elektronik Nusantara"
A growing electronics retail chain in Jakarta.

**Characteristics:**
- 2 warehouses (Jakarta + Tangerang)
- 3 retail stores (Senayan, Kelapa Gading, Pondok Indah)
- 20 electronics products
- 100 customers (mix of B2B and B2C)
- ~1,265 orders over 90 days (~15 orders/day)
- 4 users (admin, manager, cashier, warehouse staff)
- 11% VAT (PPN)
- Paid subscription (8 months remaining)

**Business Flow:**
- Multi-store retail operations
- B2B sales to corporate customers
- B2C sales to individual buyers
- Warehouse-to-store transfers
- Credit terms for business customers
- Bank transfer and credit card payments

**Sample Products:**
- Samsung Galaxy A54 5G (Rp 5,999,000)
- MacBook Air M1 (Rp 14,999,000)
- Sony WH-1000XM5 (Rp 4,999,000)
- PlayStation 5 (Rp 8,999,000)
- Samsung 43" Crystal UHD TV (Rp 4,999,000)

**Customer Types:**
- Individual retail customers (60%)
- Small business buyers (40%)
- Pricing tiers: Bronze, Silver, Gold, Wholesale

---

#### 3. **Enterprise Tier** - "PT Sumber Makmur Jaya"
A national distribution company with complex operations.

**Characteristics:**
- 4 warehouses (Jakarta, Surabaya, Medan, Makassar)
- 16 stores across Indonesia
- 100 diverse products (electronics, fashion, home, automotive)
- 100 customers (wholesalers + retail)
- ~703 orders over 30 days (~20 orders/day)
- 8 users (full organizational hierarchy)
- 11% VAT (PPN)
- Annual enterprise subscription

**Business Flow:**
- Multi-warehouse inventory management
- Inter-warehouse stock transfers
- Wholesale distribution to retailers
- Retail sales across multiple locations
- Complex pricing rules and tiers
- Multiple payment methods (bank transfer, credit card, COD, QRIS)
- Credit limits for wholesale customers
- FIFO inventory tracking

**Warehouse Locations:**
- Gudang Pusat Jakarta (20,000 capacity)
- Gudang Surabaya (15,000 capacity)
- Gudang Medan (10,000 capacity)
- Gudang Makassar (8,000 capacity)

**Store Coverage:**
- Greater Jakarta (5 stores)
- Java (Bandung, Surabaya, Yogyakarta, Semarang)
- Sumatra (Medan, Palembang)
- Kalimantan (Balikpapan, Pontianak)
- Sulawesi (Makassar, Manado)
- Bali (Denpasar)

**Customer Segments:**
- Wholesale distributors (20%)
- B2B business customers (30%)
- Retail individual customers (50%)

---

## Reference Data

The seeder also creates:

### Currencies
- Indonesian Rupiah (IDR) as default

### Pricing Tiers
- Bronze (standard pricing)
- Silver (discounted pricing)
- Gold (best pricing)
- Wholesale (special B2B pricing)

### Roles & Permissions
- Admin (full access)
- Manager (operational management)
- Warehouse Staff (inventory management)
- Store Staff (sales and orders)
- Viewer (read-only)

### Categories
Tier-specific category structures:
- **Starter**: Sembako categories (Beras, Minyak, Minuman, etc.)
- **Professional**: Electronics categories (Smartphone, Laptop, Audio, etc.)
- **Enterprise**: Comprehensive categories (Electronics, Fashion, Home, Automotive, etc.)

---

## Realistic Data Features

### 1. **Indonesian Context**
- Indonesian company names (PT, CV, UD)
- Indonesian addresses and cities
- Indonesian phone formats (+62)
- Indonesian tax IDs (NPWP format)
- Local product names and pricing in IDR

### 2. **Business Logic**
- Proper cost vs. price margins (20-40%)
- Realistic stock levels per business size
- Order status distribution (pending, confirmed, processing, fulfilled, cancelled)
- Variable order volumes per day
- Seasonal patterns (some days closed)

### 3. **Multi-tenant Isolation**
- All data properly scoped by tenant_id
- Unique codes per tenant (warehouse codes, store codes)
- Tenant-specific categories and products
- Isolated customer bases

### 4. **Data Relationships**
- Orders linked to customers, stores, warehouses
- Order items linked to products and orders
- Inventory linked to products and warehouses
- Users assigned to tenants with roles

---

## Usage

### Fresh Seed
```bash
php artisan migrate:fresh --seed
```

### Individual Seeder
```bash
php artisan db:seed --class=RealisticDataSeeder
```

---

## Login Credentials

### Super Admin
- Email: `superadmin@poswms.com`
- Password: `password`

### Starter Tenant (Toko Sembako Jaya)
- Admin: `admin@tokosembako.com` / `password`
- Cashier: `kasir1@tokosembako.com` / `password`

### Professional Tenant (Elektronik Nusantara)
- Admin: `admin@elektroniknusantara.co.id` / `password`
- Manager: `manager@elektroniknusantara.co.id` / `password`
- Cashier: `kasir.senayan@elektroniknusantara.co.id` / `password`
- Warehouse: `gudang@elektroniknusantara.co.id` / `password`

### Enterprise Tenant (PT Sumber Makmur Jaya)
- Admin: `admin@makmurjaya.co.id` / `password`
- CEO: `ceo@makmurjaya.co.id` / `password`
- Operations: `operations@makmurjaya.co.id` / `password`
- Warehouse Jakarta: `warehouse.jkt@makmurjaya.co.id` / `password`
- Warehouse Surabaya: `warehouse.sby@makmurjaya.co.id` / `password`
- Store Manager: `store.jkt@makmurjaya.co.id` / `password`
- HR: `hr@makmurjaya.co.id` / `password`
- Finance: `finance@makmurjaya.co.id` / `password`

---

## Customization

To modify the seeded data:

1. **Adjust order volumes**: Change `$avgOrdersPerDay` and `$daysBack` parameters
2. **Modify product counts**: Change the count parameter in product creation methods
3. **Change customer counts**: Adjust the count parameter in customer creation methods
4. **Add more tenants**: Create new `seedXxxTenant()` methods following the pattern

---

## Performance Notes

- Total seeding time: ~50 seconds
- Total orders created: ~2,600+
- Total products: 50
- Total customers: 230
- Total users: 14 + super admin

For faster seeding during development, reduce:
- Order history days
- Average orders per day
- Product counts
- Customer counts

---

## Troubleshooting

### Unique Constraint Violations
If you see errors about unique codes/slugs:
- Ensure warehouse codes are globally unique
- Ensure store codes are globally unique
- Ensure category slugs are globally unique
- The seeder uses tenant-prefixed slugs for categories

### Memory Issues
If running out of memory:
- Reduce order counts in each tier
- Run with increased memory: `php -d memory_limit=512M artisan migrate:fresh --seed`

---

## File Location
`database/seeders/RealisticDataSeeder.php`
