<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Centralized sorting validation service to prevent SQL injection via ORDER BY clauses.
 *
 * This service provides whitelist-based validation for sort fields and directions,
 * with fallback behavior for invalid inputs and security logging for monitoring.
 */
class SortingValidator
{
    /**
     * Default allowed sort fields when no whitelist is provided.
     *
     * @var array<string>
     */
    protected array $defaultAllowedFields = [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
        'deleted_at',
        'status',
        'is_active',
    ];

    /**
     * Dangerous SQL patterns that should never appear in sort fields.
     *
     * @var array<string>
     */
    protected array $dangerousPatterns = [
        ';',      // SQL statement terminator
        '--',     // SQL comment
        '/*',     // Block comment start
        '*/',     // Block comment end
        '#',      // MySQL comment
        '(',      // Function call
        ')',      // Function call
        ',',      // Multiple columns
        '\\',     // Escape character
        "'",      // String delimiter
        '"',      // String delimiter
        '`',      // Identifier quote
        '[',      // Bracket notation
        ']',      // Bracket notation
        ' ',      // Space (should use underscore)
        '\t',     // Tab
        '\n',     // Newline
        '\r',     // Carriage return
    ];

    /**
     * Validate and sanitize sort field against whitelist.
     *
     * Uses a defense-in-depth approach:
     * 1. Check for dangerous patterns (fail fast)
     * 2. Validate against whitelist
     * 3. Fallback to default on invalid input
     * 4. Log suspicious activity for monitoring
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultField  Default sort field if validation fails
     * @param  array<string>|null  $allowedFields  List of allowed sort fields (null = use defaults)
     * @param  string|null  $whitelistName  Name for logging (e.g., 'User', 'Product')
     * @return string The validated sort field
     */
    public function validateSortField(
        Request $request,
        string $defaultField = 'created_at',
        ?array $allowedFields = null,
        ?string $whitelistName = null
    ): string {
        $sortBy = $request->get('sort_by', $defaultField);

        // Ensure we have a string - handle arrays safely
        if (is_array($sortBy)) {
            // Log suspicious array input
            Log::info('Array input for sort field rejected', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return $defaultField;
        }

        if (! is_string($sortBy)) {
            $sortBy = (string) $sortBy;
        }

        // Trim whitespace
        $sortBy = trim($sortBy);

        // If empty, use default
        if (empty($sortBy)) {
            return $defaultField;
        }

        // Determine allowed fields
        $allowedFields = $allowedFields ?? $this->defaultAllowedFields;

        // Use safe default if whitelist is empty
        if (empty($allowedFields)) {
            Log::warning('Empty sort whitelist provided', [
                'whitelist_name' => $whitelistName,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return $defaultField;
        }

        // Check for dangerous patterns (fail fast security check)
        if ($this->containsDangerousPattern($sortBy)) {
            $this->logSuspiciousActivity($request, $sortBy, 'dangerous_pattern', $whitelistName);

            return $defaultField;
        }

        // Validate against whitelist
        if (! in_array($sortBy, $allowedFields, true)) {
            $this->logInvalidField($request, $sortBy, $allowedFields, $whitelistName);

            return $defaultField;
        }

        return $sortBy;
    }

    /**
     * Validate and sanitize sort order.
     *
     * Only allows 'asc' or 'desc' values, with fallback to default.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultOrder  Default sort order ('asc' or 'desc')
     * @return string The validated sort order ('asc' or 'desc')
     */
    public function validateSortOrder(
        Request $request,
        string $defaultOrder = 'desc'
    ): string {
        $sortOrder = $request->get('sort_order', $defaultOrder);

        // Ensure we have a string
        if (! is_string($sortOrder)) {
            $sortOrder = (string) $sortOrder;
        }

        // Normalize to lowercase and trim
        $sortOrder = strtolower(trim($sortOrder));

        // Only allow 'asc' or 'desc'
        if ($sortOrder === 'asc') {
            return 'asc';
        }

        // Log if invalid order was attempted
        if (! empty($sortOrder) && $sortOrder !== 'desc') {
            Log::info('Invalid sort order attempted', [
                'order' => $sortOrder,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return 'desc';
    }

    /**
     * Get validated sort parameters as an array.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $defaultField  Default sort field
     * @param  array<string>|null  $allowedFields  List of allowed sort fields
     * @param  string  $defaultOrder  Default sort order
     * @param  string|null  $whitelistName  Name for logging
     * @return array{sort_by: string, sort_order: string}
     */
    public function getValidatedSortParams(
        Request $request,
        string $defaultField = 'created_at',
        ?array $allowedFields = null,
        string $defaultOrder = 'desc',
        ?string $whitelistName = null
    ): array {
        return [
            'sort_by' => $this->validateSortField($request, $defaultField, $allowedFields, $whitelistName),
            'sort_order' => $this->validateSortOrder($request, $defaultOrder),
        ];
    }

    /**
     * Apply validated sorting to a query builder instance.
     *
     * This is a convenience method that validates and applies sorting in one call.
     *
     * @param  Request  $request  The HTTP request
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The query builder
     * @param  string  $defaultField  Default sort field
     * @param  array<string>|null  $allowedFields  List of allowed sort fields
     * @param  string  $defaultOrder  Default sort order
     * @param  string|null  $whitelistName  Name for logging
     * @return \Illuminate\Database\Eloquent\Builder The query builder with sorting applied
     */
    public function applySorting(
        Request $request,
        \Illuminate\Database\Eloquent\Builder $query,
        string $defaultField = 'created_at',
        ?array $allowedFields = null,
        string $defaultOrder = 'desc',
        ?string $whitelistName = null
    ): \Illuminate\Database\Eloquent\Builder {
        $sortParams = $this->getValidatedSortParams(
            $request,
            $defaultField,
            $allowedFields,
            $defaultOrder,
            $whitelistName
        );

        return $query->orderBy($sortParams['sort_by'], $sortParams['sort_order']);
    }

    /**
     * Check if the input contains any dangerous SQL patterns.
     *
     * @param  string  $input  The input string to check
     * @return bool True if dangerous pattern found
     */
    protected function containsDangerousPattern(string $input): bool
    {
        // Check for exact dangerous characters
        foreach ($this->dangerousPatterns as $pattern) {
            if (str_contains($input, $pattern)) {
                return true;
            }
        }

        // Check for SQL keywords that shouldn't be in sort fields
        $sqlKeywords = [
            'select', 'insert', 'update', 'delete', 'drop', 'union',
            'exec', 'execute', 'xp_', 'sp_', 'waitfor', 'delay',
            'benchmark', 'sleep', 'database', 'schema', 'table',
            'information_schema', 'pg_', 'mysql_', 'concat', 'char',
        ];

        $lowerInput = strtolower($input);
        foreach ($sqlKeywords as $keyword) {
            if (str_contains($lowerInput, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log suspicious activity for security monitoring.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $attemptedField  The attempted sort field
     * @param  string  $reason  Reason for flagging (e.g., 'dangerous_pattern', 'whitelist_violation')
     * @param  string|null  $whitelistName  Name of the resource being sorted
     */
    protected function logSuspiciousActivity(
        Request $request,
        string $attemptedField,
        string $reason,
        ?string $whitelistName = null
    ): void {
        Log::warning('Suspicious sort field detected - potential SQL injection', [
            'reason' => $reason,
            'attempted_field' => $attemptedField,
            'whitelist_name' => $whitelistName,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
    }

    /**
     * Log invalid field attempt (less severe than dangerous pattern).
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $attemptedField  The attempted sort field
     * @param  array<string>  $allowedFields  The whitelist of allowed fields
     * @param  string|null  $whitelistName  Name of the resource being sorted
     */
    protected function logInvalidField(
        Request $request,
        string $attemptedField,
        array $allowedFields,
        ?string $whitelistName = null
    ): void {
        Log::info('Invalid sort field attempted - fallback to default applied', [
            'attempted_field' => $attemptedField,
            'allowed_fields' => $allowedFields,
            'whitelist_name' => $whitelistName,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);
    }
}
