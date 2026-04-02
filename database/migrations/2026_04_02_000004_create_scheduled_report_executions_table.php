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
        Schema::create('scheduled_report_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamp('executed_at');
            $table->boolean('success');
            $table->integer('records_count')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_format')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->json('recipients_notified')->nullable(); // Emails that received the report
            $table->timestamps();

            // Indexes
            $table->index(['scheduled_report_id', 'executed_at']);
            $table->index(['tenant_id', 'executed_at']);
            $table->index('success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_executions');
    }
};
