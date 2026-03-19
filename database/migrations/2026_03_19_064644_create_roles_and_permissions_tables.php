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
        // Roles table - stores available roles per tenant
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // admin, manager, user, etc.
            $table->string('slug')->unique(); // admin, store_manager, warehouse_manager, etc.
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // Array of permission slugs
            $table->boolean('is_system')->default(false); // System roles can't be deleted
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });

        // Permissions table - stores available permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // View Products, Manage Orders, etc.
            $table->string('slug'); // products.view, orders.manage, etc.
            $table->string('group')->default('default'); // products, orders, inventory, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'group']);
        });

        // Role-User pivot table
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['tenant_id', 'role_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
