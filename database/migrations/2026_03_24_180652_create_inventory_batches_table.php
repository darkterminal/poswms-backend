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
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('batch_number')->index();
            $table->string('lot_number')->nullable()->index();
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->integer('initial_quantity')->default(0);
            $table->integer('remaining_quantity')->default(0);
            $table->string('status')->default('active'); // active, consumed, expired, returned
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Additional batch attributes
            $table->timestamps();

            // Unique constraint per tenant
            $table->unique(['tenant_id', 'batch_number'], 'unique_tenant_batch');

            // Indexes for performance
            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['expiry_date', 'status']);
            $table->index(['received_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
