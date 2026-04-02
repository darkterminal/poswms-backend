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
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // sales, inventory, customer, custom
            $table->json('config'); // { filters, columns, grouping, sorting }
            
            $table->boolean('is_global')->default(false); // Available to all tenants
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes(); // Soft deletes

            // Indexes for common queries
            $table->index(['tenant_id', 'type']);
            $table->index(['is_global', 'is_active']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
