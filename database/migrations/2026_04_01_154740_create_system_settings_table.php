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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index()->comment('Setting key (e.g., application.name, features.rate_limiting)');
            $table->string('group')->index()->comment('Setting group (e.g., application, database, cache, queue, mail, features)');
            $table->json('value')->comment('Setting value (JSON encoded)');
            $table->string('type')->default('string')->comment('Value type: string, boolean, integer, float, json, array');
            $table->text('description')->nullable()->comment('Setting description');
            $table->boolean('is_public')->default(true)->comment('Whether this setting can be exposed to frontend');
            $table->boolean('is_editable')->default(true)->comment('Whether this setting can be edited via UI');
            $table->json('metadata')->nullable()->comment('Additional metadata (validation rules, options, etc.)');
            $table->timestamp('last_modified_at')->nullable()->comment('Last time this setting was modified');
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who last modified this setting');
            
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
