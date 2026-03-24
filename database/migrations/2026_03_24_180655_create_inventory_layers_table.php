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
        Schema::create('inventory_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->onDelete('set null');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('quantity')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0); // quantity * unit_cost
            $table->integer('layer_order')->default(0); // For FIFO ordering
            $table->boolean('is_fifo_layer')->default(true);
            $table->timestamps();

            // Indexes for FIFO queries
            $table->index(['tenant_id', 'product_id', 'warehouse_id', 'layer_order']);
            $table->index(['inventory_id', 'layer_order']);
            $table->index(['tenant_id', 'is_fifo_layer', 'layer_order']);

            // Ensure unique layer ordering per inventory
            $table->unique(['inventory_id', 'layer_order'], 'unique_inventory_layer_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_layers');
    }
};
