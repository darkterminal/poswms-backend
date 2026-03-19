<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('tenant_id');
            $table->unsignedBigInteger('store_id')->nullable()->after('role');
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('store_id');

            $table->index(['tenant_id', 'role']);
            $table->index('store_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropIndex('store_id');
            $table->dropIndex('warehouse_id');
            $table->dropColumn(['role', 'store_id', 'warehouse_id']);
        });
    }
};
