# Bug Report: Inventory Movements API Data Structure Inconsistencies

## Summary
There are inconsistencies in the data structure provided by the backend inventory movements API, causing desynchronization between API specifications and actual implementation.

## Environment
- Backend: poswms-backend
- Controllers: AdminInventoryReportController.php, InventoryReportController.php
- API Specification: openapi.yaml

## Issues Identified

### 1. Inconsistent API Endpoints
**Problem**: Two similar controllers with inconsistent implementations:
- `AdminInventoryReportController::movements` - accepts `limit` parameter
- `InventoryReportController::movements` - does not accept `limit` parameter 

### 2. Incomplete/Incorrect Response Structure
**Problem**: The `movements` endpoint in both controllers does not return the full set of fields expected by the front-end:
- Missing `tenant_name` field from response (expected by frontend)
- Location information provided inconsistently
- Missing fields like `notes` in some implementations

### 3. Field Mismatch with OpenAPI Documentation
**Problem**: The API response structure differs from the documented API specification in openapi.yaml:
- The `StockMovementsResponse` schema in docs shows:
  - `product` object with `id`, `name`, `sku`
  - `warehouse` object with `id`, `name`
  - `store` object with `id`, `name`
  - `user` object with `id`, `name`
  - `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reference`, `notes`, `created_at`

- However, the actual backend returns only fields that are used in the API call (may have different field mappings)

### 4. Parameter Validation Inconsistencies
**Problem**: 
- Admin controller allows `limit` parameter
- InventoryReportController does not validate or use limit correctly
- No standardization for parameter validation across controllers

## Root Causes

1. **Overlapping Functionality**: Two separate controllers handling similar functionality
2. **Lack of Unified Data Layer**: Different controllers return differently structured data
3. **Inconsistent API Design**: No proper data transformation in API responses
4. **No Field Validation**: Fields are not properly sanitized or validated

## Files Affected
- `/poswms-backend/app/Http/Controllers/Admin/AdminInventoryReportController.php`
- `/poswms-backend/app/Http/Controllers/InventoryReportController.php`
- `/poswms-backend/routes/api.php`
- `/poswms-super-app/docs/openapi.yaml`

## Impact
- Frontend components cannot properly render inventory movement data
- Inconsistent error handling between different API endpoints
- Inflexible reporting functionality due to inconsistent data structures
- Potential data integrity issues

## Fix Approach
1. Consolidate inventory report controllers to single source of truth
2. Ensure all movement API endpoints return consistent data structure
3. Implement comprehensive field validation
4. Align API response structure completely with OpenAPI specification
5. Add proper error handling for malformed parameter cases