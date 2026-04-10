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
        // Inventory indexes
        Schema::table('inventories', function (Blueprint $table) {
            $table->index(['product_id', 'warehouse_id'], 'idx_inv_product_warehouse');
            $table->index(['product_id', 'store_id'], 'idx_inv_product_store');
            $table->index(['tenant_id', 'available'], 'idx_inv_tenant_available');
            $table->index(['tenant_id', 'quantity'], 'idx_inv_tenant_quantity');
            $table->index(['warehouse_id', 'available'], 'idx_inv_warehouse_available');
            $table->index(['store_id', 'available'], 'idx_inv_store_available');
        });

        // Stock movements indexes
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'created_at'], 'idx_mov_tenant_type_date');
            $table->index(['tenant_id', 'product_id', 'created_at'], 'idx_mov_tenant_product_date');
            $table->index(['inventory_id', 'created_at'], 'idx_mov_inventory_date');
            $table->index(['warehouse_id', 'created_at'], 'idx_mov_warehouse_date');
            $table->index(['store_id', 'created_at'], 'idx_mov_store_date');
            $table->index(['layer_id'], 'idx_mov_layer');
            $table->index(['order_id'], 'idx_mov_order');
        });

        // Inventory batches indexes
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index(['expiry_date', 'status'], 'idx_batch_expiry_status');
            $table->index(['tenant_id', 'status'], 'idx_batch_tenant_status');
            $table->index(['tenant_id', 'product_id'], 'idx_batch_tenant_product');
            $table->index(['warehouse_id', 'status'], 'idx_batch_warehouse_status');
            $table->index(['batch_number'], 'idx_batch_number');
        });

        // Inventory layers indexes
        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->index(['inventory_id', 'is_fifo_layer', 'layer_order'], 'idx_layer_inv_fifo_order');
            $table->index(['tenant_id', 'product_id'], 'idx_layer_tenant_product');
            $table->index(['warehouse_id', 'available'], 'idx_layer_warehouse_available');
            $table->index(['batch_id'], 'idx_layer_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex('idx_inv_product_warehouse');
            $table->dropIndex('idx_inv_product_store');
            $table->dropIndex('idx_inv_tenant_available');
            $table->dropIndex('idx_inv_tenant_quantity');
            $table->dropIndex('idx_inv_warehouse_available');
            $table->dropIndex('idx_inv_store_available');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_mov_tenant_type_date');
            $table->dropIndex('idx_mov_tenant_product_date');
            $table->dropIndex('idx_mov_inventory_date');
            $table->dropIndex('idx_mov_warehouse_date');
            $table->dropIndex('idx_mov_store_date');
            $table->dropIndex('idx_mov_layer');
            $table->dropIndex('idx_mov_order');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex('idx_batch_expiry_status');
            $table->dropIndex('idx_batch_tenant_status');
            $table->dropIndex('idx_batch_tenant_product');
            $table->dropIndex('idx_batch_warehouse_status');
            $table->dropIndex('idx_batch_number');
        });

        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->dropIndex('idx_layer_inv_fifo_order');
            $table->dropIndex('idx_layer_tenant_product');
            $table->dropIndex('idx_layer_warehouse_available');
            $table->dropIndex('idx_layer_batch');
        });
    }
};
