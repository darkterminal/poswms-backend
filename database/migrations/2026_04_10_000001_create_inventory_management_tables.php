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
        // Physical inventory counts
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, in_progress, completed, approved, cancelled
            $table->foreignId('started_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Inventory count items
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('inventory_counts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->onDelete('set null');
            $table->decimal('expected_quantity', 15, 2)->default(0);
            $table->decimal('counted_quantity', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['count_id', 'product_id']);
            $table->unique(['count_id', 'product_id']);
        });

        // Inventory alert configurations
        Schema::create('inventory_alert_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('set null');
            $table->integer('min_threshold')->default(0);
            $table->integer('max_threshold')->nullable();
            $table->boolean('alert_enabled')->default(true);
            $table->text('email_recipients')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id', 'store_id']);
        });

        // Warehouse zones
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->string('name');
            $table->string('code');
            $table->integer('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_zones');
        Schema::dropIfExists('inventory_alert_configs');
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
    }
};
