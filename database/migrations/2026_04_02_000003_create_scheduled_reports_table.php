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
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('report_templates')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // sales, inventory, customer, custom
            $table->json('filters'); // Report filters
            $table->string('schedule_frequency'); // daily, weekly, monthly
            $table->string('schedule_day')->nullable(); // Day of week (1-7) for weekly, day of month (1-31) for monthly
            $table->time('schedule_time')->default('09:00:00'); // Time to run the report
            $table->json('recipients'); // Array of email addresses
            $table->string('export_format')->default('csv'); // csv, pdf, xlsx
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['tenant_id', 'is_active']);
            $table->index(['schedule_frequency', 'next_run_at']);
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
