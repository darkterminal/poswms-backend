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
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('report_templates')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // sales, inventory, customer, custom
            $table->json('filters'); // Applied filters snapshot
            $table->json('data')->nullable(); // Optional: cached report data
            $table->string('file_path')->nullable(); // Path to exported file
            $table->string('file_format')->nullable(); // csv, pdf, xlsx
            $table->integer('file_size')->nullable(); // File size in bytes
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable(); // Auto-cleanup after this date

            $table->timestamps();
            $table->softDeletes(); // Soft deletes

            // Indexes for common queries
            $table->index(['tenant_id', 'type']);
            $table->index(['created_by', 'generated_at']);
            $table->index('expires_at');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
