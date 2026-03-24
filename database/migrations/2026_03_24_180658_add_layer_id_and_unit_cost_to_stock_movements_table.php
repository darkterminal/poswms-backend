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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('layer_id')->nullable()->after('inventory_id')->constrained('inventory_layers')->onDelete('set null');
            $table->decimal('unit_cost', 15, 4)->nullable()->after('quantity');
            $table->decimal('total_cost', 15, 4)->nullable()->after('unit_cost');

            // Add indexes for performance
            $table->index('layer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['layer_id']);
            $table->dropForeign(['layer_id']);
            $table->dropColumn(['layer_id', 'unit_cost', 'total_cost']);
        });
    }
};
