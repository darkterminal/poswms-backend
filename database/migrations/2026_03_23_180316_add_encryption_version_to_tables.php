<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds encryption_version columns to track which records
     * have been encrypted with the new encryption system. This allows for
     * gradual rollout and rollback of encryption at rest.
     */
    public function up(): void
    {
        // Add encryption_version to webhooks table
        Schema::table('webhooks', function (Blueprint $table) {
            $table->smallInteger('encryption_version')->unsigned()->default(0)->after('secret')
                ->comment('0=plaintext, 1=encrypted');
        });

        // Add encryption_version to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->smallInteger('encryption_version')->unsigned()->default(0)->after('email')
                ->comment('0=plaintext, 1=encrypted');
        });

        // Add encryption_version to tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->smallInteger('encryption_version')->unsigned()->default(0)->after('email')
                ->comment('0=plaintext, 1=encrypted');
        });

        // Add encryption_version to stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->smallInteger('encryption_version')->unsigned()->default(0)->after('email')
                ->comment('0=plaintext, 1=encrypted');
        });

        // Add encryption_version to warehouses table
        Schema::table('warehouses', function (Blueprint $table) {
            $table->smallInteger('encryption_version')->unsigned()->default(0)->after('email')
                ->comment('0=plaintext, 1=encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });
    }
};
