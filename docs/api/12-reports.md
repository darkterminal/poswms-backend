# Reports & Dashboard Endpoints

Base URL: `/api/v1/tenants/{tenant_id}`

---

## Dashboard

### Get Dashboard Metrics

Get unified dashboard metrics for tenant admin.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/dashboard
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `period` | string | No | `today`, `week`, `month`, `year`, `all` (default: today) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "period": "week",
    "sales": {
      "revenue": {
        "current": 15420.50,
        "previous": 12350.00,
        "growth_percentage": 24.86
      },
      "orders_count": {
        "current": 145,
        "previous": 120,
        "growth_percentage": 20.83
      },
      "average_order_value": 106.35
    },
    "inventory": {
      "total_products": 250,
      "total_quantity": 5420,
      "total_available": 4890,
      "total_reserved": 530,
      "total_value": 125450.00,
      "low_stock_count": 12,
      "out_of_stock_count": 3,
      "health_percentage": 95.20
    },
    "orders": {
      "status_counts": {
        "total": 1250,
        "pending": 25,
        "confirmed": 45,
        "fulfilled": 1150,
        "cancelled": 30
      },
      "todays_orders": 18,
      "pending_fulfillment": 70
    }
  }
}
```

---

## Sales Reports

### Revenue Report

Get sales revenue analytics.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/revenue
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
| `period` | string | No | `daily`, `weekly`, `monthly`, `yearly` (default: daily) |
| `store_id` | integer | No | Filter by store |
| `warehouse_id` | integer | No | Filter by warehouse |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "period": "daily",
    "revenue_by_period": [
      {
        "period": "2024-01-15",
        "order_count": 45,
        "total_revenue": 4520.50,
        "total_tax": 361.64,
        "total_discount": 120.00,
        "total_shipping": 225.00,
        "avg_order_value": 100.46
      },
      {
        "period": "2024-01-16",
        "order_count": 52,
        "total_revenue": 5230.75,
        "total_tax": 418.46,
        "total_discount": 85.00,
        "total_shipping": 260.00,
        "avg_order_value": 100.59
      }
    ],
    "summary": {
      "total_revenue": 15420.50,
      "total_orders": 145,
      "average_order_value": 106.35,
      "total_tax": 1233.64,
      "total_discount": 350.00,
      "total_shipping": 725.00
    }
  }
}
```

---

### Orders by Period

Get orders grouped by period.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/orders-by-period
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
| `period` | string | No | `daily`, `weekly`, `monthly`, `yearly` (default: daily) |
| `status` | string | No | Filter by status |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "period": "daily",
    "orders_by_period": [
      {
        "period": "2024-01-15",
        "order_count": 45,
        "pending_count": 5,
        "confirmed_count": 10,
        "fulfilled_count": 28,
        "cancelled_count": 2,
        "total_revenue": 4520.50
      },
      {
        "period": "2024-01-16",
        "order_count": 52,
        "pending_count": 8,
        "confirmed_count": 12,
        "fulfilled_count": 30,
        "cancelled_count": 2,
        "total_revenue": 5230.75
      }
    ],
    "summary": {
      "total_orders": 145,
      "pending": 18,
      "confirmed": 35,
      "fulfilled": 85,
      "cancelled": 7
    }
  }
}
```

---

### Top Products

Get top selling products report.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/top-products
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
| `limit` | integer | No | Number of products (default: 10) |
| `sort_by` | string | No | `quantity` or `revenue` (default: quantity) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "sort_by": "quantity",
    "limit": 10,
    "top_products": [
      {
        "product_id": 1,
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "total_quantity": 250,
        "total_revenue": 24997.50,
        "order_count": 125,
        "avg_price": 99.99
      },
      {
        "product_id": 2,
        "product": {
          "id": 2,
          "name": "USB-C Cable",
          "sku": "UC-002"
        },
        "total_quantity": 180,
        "total_revenue": 3598.20,
        "order_count": 95,
        "avg_price": 19.99
      }
    ]
  }
}
```

---

### Sales Dashboard Metrics

Get sales dashboard metrics and analytics.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/dashboard
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
    "summary": {
      "total_revenue": 15420.50,
      "total_orders": 145,
      "average_order_value": 106.35,
      "total_tax": 1233.64,
      "total_discount": 350.00,
      "total_shipping": 725.00
    },
    "trends": {
      "revenue_growth": 24.86,
      "orders_growth": 20.83
    }
  }
}
```

---

### Export Revenue Report (CSV)

Export revenue report to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/export/revenue
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:** Same as Revenue Report

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="sales_revenue_daily_2024-01-16.csv"

Period,Orders,Revenue,Tax,Discount,Shipping,Avg Order Value
2024-01-15,45,4520.50,361.64,120.00,225.00,100.46
2024-01-16,52,5230.75,418.46,85.00,260.00,100.59
```

---

### Export Orders by Period (CSV)

Export orders by period report to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/export/orders-by-period
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="orders_by_period_2024-01-16.csv"

Period,Total Orders,Pending,Confirmed,Fulfilled,Cancelled,Revenue
2024-01-15,45,5,10,28,2,4520.50
2024-01-16,52,8,12,30,2,5230.75
```

---

### Export Top Products (CSV)

Export top products report to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/sales/export/top-products
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="top_products_2024-01-16.csv"

Product ID,Product Name,SKU,Qty Sold,Revenue,Orders,Avg Price
1,Wireless Headphones,WH-001,250,24997.50,125,99.99
2,USB-C Cable,UC-002,180,3598.20,95,19.99
```

---

## Inventory Reports

### Stock Levels Report

Get current stock levels across all locations.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/stock-levels
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `warehouse_id` | integer | No | Filter by warehouse |
| `store_id` | integer | No | Filter by store |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "inventories": [
      {
        "id": 1,
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "location": {
          "warehouse": "Central Warehouse",
          "store": null
        },
        "quantity": 150,
        "reserved": 10,
        "available": 140,
        "cost": 50.00,
        "total_value": 7500.00
      },
      {
        "id": 2,
        "product": {
          "id": 2,
          "name": "USB-C Cable",
          "sku": "UC-002"
        },
        "location": {
          "warehouse": "Central Warehouse",
          "store": null
        },
        "quantity": 500,
        "reserved": 25,
        "available": 475,
        "cost": 8.00,
        "total_value": 4000.00
      }
    ],
    "summary": {
      "total_items": 250,
      "total_quantity": 5420,
      "total_available": 4890,
      "total_reserved": 530,
      "total_value": 125450.00
    }
  }
}
```

---

### Inventory Movements

Get stock movement history.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/movements
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `product_id` | integer | No | Filter by product |
| `warehouse_id` | integer | No | Filter by warehouse |
| `limit` | integer | No | Number of records (default: 50) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "movements": [
      {
        "id": 1,
        "tenant_id": 1,
        "product_id": 1,
        "warehouse_id": 1,
        "store_id": null,
        "user_id": 1,
        "movement_type": "in",
        "quantity": 100,
        "quantity_before": 50,
        "quantity_after": 150,
        "reference": "PO-2024-001",
        "notes": "Purchase order received",
        "created_at": "2024-01-15T10:00:00Z",
        "product": {
          "id": 1,
          "name": "Wireless Headphones",
          "sku": "WH-001"
        },
        "warehouse": {
          "id": 1,
          "name": "Central Warehouse"
        },
        "store": null,
        "user": {
          "id": 1,
          "name": "John Admin"
        }
      }
    ]
  }
}
```

---

### Low Stock Alerts

Get products with low stock levels.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/low-stock
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
      "product_id": 3,
      "product_name": "Bluetooth Speaker",
      "product_sku": "BS-003",
      "warehouse_name": "Central Warehouse",
      "store_name": null,
      "current_quantity": 5,
      "minimum_quantity": 10,
      "shortage": 5,
      "severity": "medium"
    },
    {
      "product_id": 4,
      "product_name": "Phone Case",
      "product_sku": "PC-004",
      "warehouse_name": null,
      "store_name": "Downtown Store",
      "current_quantity": 2,
      "minimum_quantity": 15,
      "shortage": 13,
      "severity": "high"
    }
  ]
}
```

---

### Export Stock Levels (CSV)

Export stock levels report to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/export/stock-levels
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="stock_levels_2024-01-16.csv"

ID,Product ID,Product Name,SKU,Warehouse,Store,Quantity,Reserved,Available,Cost,Total Value
1,1,Wireless Headphones,WH-001,Central Warehouse,N/A,150,10,140,50.00,7500.00
2,2,USB-C Cable,UC-002,Central Warehouse,N/A,500,25,475,8.00,4000.00
```

---

### Export Inventory Movements (CSV)

Export inventory movements to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/export/movements
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="inventory_movements_2024-01-16.csv"

ID,Product,SKU,Warehouse,Store,Type,Quantity,Before,After,Reference,User,Date
1,Wireless Headphones,WH-001,Central Warehouse,N/A,in,100,50,150,PO-2024-001,John Admin,2024-01-15T10:00:00Z
```

---

### Export Low Stock Alerts (CSV)

Export low stock alerts to CSV.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory/export/low-stock
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="low_stock_alerts_2024-01-16.csv"

Product ID,Product Name,SKU,Warehouse,Store,Current Qty,Min Qty,Shortage,Severity
3,Bluetooth Speaker,BS-003,Central Warehouse,N/A,5,10,5,medium
4,Phone Case,PC-004,N/A,Downtown Store,2,15,13,high
```

---

## Inventory Report Summary

Get comprehensive inventory report.

**Endpoint:**
```
GET /api/v1/tenants/{tenant_id}/reports/inventory
```

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `warehouse_id` | integer | No | Filter by warehouse |
| `store_id` | integer | No | Filter by store |

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_products": 250,
      "total_quantity": 5420,
      "total_value": 125450.00,
      "low_stock_count": 12,
      "out_of_stock_count": 3
    },
    "low_stock_alerts": [...],
    "recent_movements": [...]
  }
}
```
