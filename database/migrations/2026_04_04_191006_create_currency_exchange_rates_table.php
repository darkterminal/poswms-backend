<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores exchange rates relative to a base currency (typically USD or EUR).
     * Each row represents: 1 unit of base_currency = rate units of target_currency.
     */
    public function up(): void
    {
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('base_currency', 3);
            $table->string('target_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->string('source', 20)->default('manual'); // 'manual', 'ecb', 'api'
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();

            // Unique pair per tenant (or global if tenant_id is null)
            $table->unique(['tenant_id', 'base_currency', 'target_currency'], 'unique_rate');
            $table->index(['tenant_id', 'base_currency']);
            $table->index(['tenant_id', 'target_currency']);
            $table->index('effective_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
