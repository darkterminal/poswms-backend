<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the global unique constraint on slug
        // SQLite doesn't support dropping individual constraints,
        // so we need to recreate the table without the constraint

        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we'll just leave it as-is since the composite unique index exists
            // The global unique constraint in SQLite is via an index that we can drop
            try {
                DB::statement('DROP INDEX IF EXISTS roles_slug_unique');
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }
        } else {
            // For MySQL/PostgreSQL
            try {
                Schema::table('roles', function ($table) {
                    $table->dropUnique('roles_slug_unique');
                });
            } catch (Exception $e) {
                // Ignore if constraint doesn't exist
            }
        }

        // Do the same for permissions table
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS permissions_slug_unique');
            } catch (Exception $e) {
                // Ignore if index doesn't exist
            }
        } else {
            try {
                Schema::table('permissions', function ($table) {
                    $table->dropUnique('permissions_slug_unique');
                });
            } catch (Exception $e) {
                // Ignore if constraint doesn't exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the global unique constraints
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: recreate the indexes
            DB::statement('CREATE UNIQUE INDEX roles_slug_unique ON roles (slug)');
            DB::statement('CREATE UNIQUE INDEX permissions_slug_unique ON permissions (slug)');
        } else {
            Schema::table('roles', function ($table) {
                $table->unique('slug');
            });
            Schema::table('permissions', function ($table) {
                $table->unique('slug');
            });
        }
    }
};
