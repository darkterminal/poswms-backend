# Bug Report: Inventory Movements Report Page Mismatch

## Summary
The Inventory Movements Report page in the frontend application has inconsistencies between expected data structure and actual API responses, leading to potential display issues and data problems.

## Environment
- Frontend: poswms-super-app
- Backend: poswms-backend
- Component: InventoryMovementsReportPage

## Issues Identified

### 1. Data Structure Mismatch
**Problem**: Frontend component references `movement.tenant_name` but it's not in the API schema definition
- Expected fields: `tenant_name`, `location` (with `warehouse` and `store`)
- Actual API fields (from spec): `product`, `warehouse`, `store`, `user`, `movement_type`, etc.

### 2. Inconsistent API Usage
**Problem**: 
- Frontend uses `/admin/reports/inventory/movements` API endpoint
- Backend implements endpoint with different route structure
- The OpenAPI spec expects tenant_id at route level `/tenants/{tenant_id}/reports/inventory/movements`

### 3. Field Name Mismatches
**Problem**: 
- Component expects `movement.location` field but API schema only has `warehouse` and `store` at top level
- Some fields like `notes` should be optional but component assumes their presence

## Root Causes

1. **Backend API Version Mismatch**: Different behavior between two backend controllers
2. **Frontend API Client Inconsistencies**: API call patterns don't align with backend routes or expected fields
3. **Schema Version Mismatch**: The frontend code assumes different data model than provided by the backend

## Files Affected
- `/poswms-super-app/src/features/inventory-reports/pages/InventoryMovementsReportPage.tsx`
- `/poswms-super-app/src/features/inventory-reports/services/inventoryReportService.ts`
- `/poswms-backend/app/Http/Controllers/Admin/AdminInventoryReportController.php`
- `/poswms-backend/app/Http/Controllers/InventoryReportController.php`

## Impact
- Report data may not display correctly
- Component errors when accessing missing/incorrectly named fields
- Potential data loss or invalid display of inventory movement details
- Inconsistent user experience in reports

## Fix Approach
1. Align frontend component with actual API response structure
2. Correct API calls to match backend routes properly
3. Update data structures to match the OpenAPI specification
4. Add appropriate error handling for nullable fields
5. Standardize field access patterns across the component