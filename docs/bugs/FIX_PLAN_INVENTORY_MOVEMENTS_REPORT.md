# Fix Plan: Inventory Movements Report Issues

## Overview
This document outlines the systematic approach to fixing the issues identified in the Inventory Movements Report page and API structure mismatches.

## Issues to Address

### 1. Data Structure Alignment
**Problem**: Frontend expects certain fields that don't exist in API response
**Solution**: 
- Modify frontend to remove dependency on non-existent `tenant_name` field
- Correctly handle movement location data (warehouse/store) directly
- Validate all expected fields before accessing them

### 2. API Endpoint Consistency
**Problem**: Different controllers with different API behaviors
**Solution**:
- Consolidate endpoint logic to single controller (preferably `InventoryReportController`)
- Ensure all parameters align with API specification
- Standardize response format across all similar endpoints

### 3. Field Validation and Error Handling
**Problem**: No proper validation of data received from API
**Solution**:
- Add robust field validation in component
- Implement comprehensive type-checking for data structure
- Add proper fallback for missing fields

## Implementation Steps

### Phase 1: Frontend Fixes
- [ ] Update `InventoryMovementsReportPage.tsx` to remove `tenant_name` dependency
- [ ] Correct data structure access for movement items 
- [ ] Improve field validation (null/undefined checks)
- [ ] Standardize display of movement data based on available fields

### Phase 2: Backend API Standardization
- [ ] Merge or standardize `AdminInventoryReportController` and `InventoryReportController`
- [ ] Ensure proper `tenant_id` handling in API routes
- [ ] Standardize movement response structure to match OpenAPI spec exactly
- [ ] Validate `limit` parameter correctly in all methods

### Phase 3: Testing and Validation
- [ ] Unit tests for movement data processing
- [ ] Integration tests for API endpoints
- [ ] Manual testing of report page with various filters
- [ ] Verify export functionality works correctly

## Expected Outcomes
1. Report page displays correctly with all movement data
2. API responses follow documented specification exactly
3. No runtime errors due to missing data fields
4. Proper handling of edge cases (empty results, missing fields)
5. Consistent behavior between different API endpoints

## Timeline
Estimated time: 4-6 hours for complete implementation and testing