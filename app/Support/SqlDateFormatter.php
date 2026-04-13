<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL Date Formatter Helper.
 *
 * Provides database-agnostic date formatting for SQLite, MySQL, and PostgreSQL.
 * Used to avoid duplicated driver detection logic across controllers.
 */
class SqlDateFormatter
{
    /**
     * Get the SQL expression to format a date column for the current database driver.
     *
     * @param  string  $column  The column name to format (default: 'created_at')
     * @param  string  $format  The date format: 'date' (Y-m-d), 'month' (Y-m), 'week' (Y-W), 'year' (Y)
     * @return string The SQL expression to use in selectRaw/groupByRaw
     */
    public static function dateColumn(string $column = 'created_at', string $format = 'date'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => self::sqliteDateExpression($column, $format),
            'mysql' => self::mysqlDateExpression($column, $format),
            'pgsql' => self::pgsqlDateExpression($column, $format),
            default => self::genericDateExpression($column, $format),
        };
    }

    /**
     * Get SQLite date expression.
     */
    protected static function sqliteDateExpression(string $column, string $format): string
    {
        return match ($format) {
            'date' => "strftime('%Y-%m-%d', {$column})",
            'month' => "strftime('%Y-%m', {$column})",
            'week' => "strftime('%Y-%W', {$column})",
            'year' => "strftime('%Y', {$column})",
            'day_of_week' => "CAST(strftime('%w', {$column}) AS INTEGER) + 1",
            'hour' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "strftime('%Y-%m-%d', {$column})",
        };
    }

    /**
     * Get MySQL date expression.
     */
    protected static function mysqlDateExpression(string $column, string $format): string
    {
        return match ($format) {
            'date' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            'week' => "DATE_FORMAT({$column}, '%Y-%u')",
            'year' => "DATE_FORMAT({$column}, '%Y')",
            'day_of_week' => "DAYOFWEEK({$column})",
            'hour' => "HOUR({$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /**
     * Get PostgreSQL date expression.
     */
    protected static function pgsqlDateExpression(string $column, string $format): string
    {
        return match ($format) {
            'date' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
            'month' => "TO_CHAR({$column}, 'YYYY-MM')",
            'week' => "TO_CHAR({$column}, 'YYYY-WW')",
            'year' => "TO_CHAR({$column}, 'YYYY')",
            'day_of_week' => "EXTRACT(DOW FROM {$column}) + 1",
            'hour' => "EXTRACT(HOUR FROM {$column})",
            default => "TO_CHAR({$column}, 'YYYY-MM-DD')",
        };
    }

    /**
     * Get generic date expression (fallback).
     */
    protected static function genericDateExpression(string $column, string $format): string
    {
        return match ($format) {
            'date' => "DATE({$column})",
            'month' => "STRFTIME('%Y-%m', {$column})",
            'week' => "STRFTIME('%Y-%W', {$column})",
            'year' => "STRFTIME('%Y', {$column})",
            default => "DATE({$column})",
        };
    }
}
