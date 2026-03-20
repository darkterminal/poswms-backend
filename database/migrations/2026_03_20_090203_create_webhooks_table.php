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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events'); // Array of event types to listen for
            $table->boolean('active')->default(true);
            $table->string('content_type')->default('application/json'); // json or form-data
            $table->json('headers')->nullable(); // Custom headers
            $table->integer('retry_count')->default(3); // Number of retry attempts
            $table->integer('timeout')->default(30); // Timeout in seconds
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
