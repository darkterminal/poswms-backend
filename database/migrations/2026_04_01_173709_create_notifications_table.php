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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type'); // e.g., 'system', 'tenant', 'order', 'inventory'
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional notification data
            $table->timestamp('read_at')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['tenant_id', 'read_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['priority', 'read_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
