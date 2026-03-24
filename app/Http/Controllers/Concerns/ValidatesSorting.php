<?php

namespace App\Http\Controllers\Concerns;

use App\Services\SortingValidator;
use Illuminate\Http\Request;

/**
 * Trait for validating sort parameters to prevent SQL injection.
 *
 * This trait provides a backward-compatible wrapper around the SortingValidator service.
 * It maintains the existing method signatures while delegating to the centralized validator.
 */
trait ValidatesSorting
{
    /**
     * Get the sorting validator service instance.
     */
    protected function sortingValidator(): SortingValidator
    {
        return app(SortingValidator::class);
    }

    /**
     * Validate and sanitize sort field against whitelist.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultField  Default sort field
     * @param  array<string>  $allowedFields  List of allowed sort fields
     * @return string The validated sort field
     */
    protected function getValidatedSortField(
        Request $request,
        string $defaultField = 'created_at',
        array $allowedFields = []
    ): string {
        // Use default allowed fields if none provided for backward compatibility
        $whitelist = empty($allowedFields) ? null : $allowedFields;

        return $this->sortingValidator()->validateSortField(
            $request,
            $defaultField,
            $whitelist,
            $this->getWhitelistName()
        );
    }

    /**
     * Validate and sanitize sort order.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultOrder  Default sort order ('asc' or 'desc')
     * @return string The validated sort order ('asc' or 'desc')
     */
    protected function getValidatedSortOrder(
        Request $request,
        string $defaultOrder = 'desc'
    ): string {
        return $this->sortingValidator()->validateSortOrder($request, $defaultOrder);
    }

    /**
     * Get validated sort parameters as an array.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultField  Default sort field
     * @param  array<string>  $allowedFields  List of allowed sort fields
     * @param  string  $defaultOrder  Default sort order
     * @return array{sort_by: string, sort_order: string}
     */
    protected function getValidatedSortParams(
        Request $request,
        string $defaultField = 'created_at',
        array $allowedFields = [],
        string $defaultOrder = 'desc'
    ): array {
        // Use default allowed fields if none provided for backward compatibility
        $whitelist = empty($allowedFields) ? null : $allowedFields;

        return $this->sortingValidator()->getValidatedSortParams(
            $request,
            $defaultField,
            $whitelist,
            $defaultOrder,
            $this->getWhitelistName()
        );
    }

    /**
     * Get the whitelist name for logging purposes.
     * Override this method in your controller to provide context.
     */
    protected function getWhitelistName(): ?string
    {
        // Extract class name without "Controller" suffix
        $className = class_basename($this);

        return str_replace('Controller', '', $className);
    }
}
