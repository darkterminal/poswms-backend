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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('description')->nullable()->after('event_type');

            // Make tenant_id nullable for global events (e.g., auth failures without user)
            $table->foreignId('tenant_id')->nullable()->change();

            // Make auditable fields nullable for security events that don't target specific models
            $table->string('auditable_type')->nullable()->change();
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('description');

            // Revert nullable changes
            $table->foreignId('tenant_id')->nullable(false)->change();
            $table->string('auditable_type')->nullable(false)->change();
            $table->unsignedBigInteger('auditable_id')->nullable(false)->change();
        });
    }
};
