<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds additional performance indexes to improve query performance
     * for frequently accessed columns and common query patterns in the MSWMS application.
     */
    public function up(): void
    {
        // Products - additional indexes not in original migration
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('price');
            $table->index(['tenant_id', 'active', 'created_at']);
        });

        // Customers - additional indexes
        Schema::table('customers', function (Blueprint $table) {
            $table->index('email');
            $table->index('phone');
            $table->index(['tenant_id', 'active']);
        });

        // Orders - additional indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('type');
            $table->index('payment_status');
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Inventories - additional indexes
        Schema::table('inventories', function (Blueprint $table) {
            $table->index('quantity');
            $table->index('warehouse_id');
            $table->index('store_id');
            $table->index(['tenant_id', 'quantity']);
        });

        // Stock Movements - additional indexes
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('warehouse_id');
            $table->index('store_id');
            $table->index('created_at');
            $table->index(['tenant_id', 'product_id', 'created_at']);
        });

        // Pricing Rules - active index (others already exist)
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->index('active');
        });

        // Pricing Tiers - composite index
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->index(['tenant_id', 'priority']);
        });

        // Audit Logs - additional indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['tenant_id', 'created_at']);
        });

        // Webhooks - composite index
        Schema::table('webhooks', function (Blueprint $table) {
            $table->index(['tenant_id', 'active', 'created_at']);
        });

        // Webhook Delivery Attempts - additional indexes
        Schema::table('webhook_delivery_attempts', function (Blueprint $table) {
            $table->index('webhook_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Users - additional indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
            $table->index('role_id');
            $table->index(['tenant_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['price']);
            $table->dropIndex(['tenant_id', 'active', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['tenant_id', 'active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['tenant_id', 'status', 'created_at']);
            $table->dropIndex(['tenant_id', 'customer_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['quantity']);
            $table->dropIndex(['warehouse_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['tenant_id', 'quantity']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['warehouse_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['tenant_id', 'product_id', 'created_at']);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropIndex(['active']);
        });

        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'priority']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'active', 'created_at']);
        });

        Schema::table('webhook_delivery_attempts', function (Blueprint $table) {
            $table->dropIndex(['webhook_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['role_id']);
            $table->dropIndex(['tenant_id', 'active']);
        });
    }
};
