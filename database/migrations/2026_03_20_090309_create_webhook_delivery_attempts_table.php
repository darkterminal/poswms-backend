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
        Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // The event that triggered the webhook
            $table->string('url');
            $table->integer('attempt_number')->default(1);
            $table->integer('response_status')->nullable(); // HTTP status code
            $table->text('request_body')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->float('response_time_ms')->nullable(); // Response time in milliseconds
            $table->boolean('success')->default(false);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'success']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
    }
};
