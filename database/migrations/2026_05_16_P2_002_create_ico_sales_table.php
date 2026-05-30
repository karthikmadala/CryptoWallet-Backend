<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ico_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ico_token_id')->constrained('ico_tokens')->cascadeOnDelete();
            $table->string('sale_type', 20);
            $table->string('status', 20)->default('draft')->index();
            $table->string('contract_address', 42);
            $table->string('chain_type', 20)->index();
            $table->decimal('token_price_usd', 18, 8);
            $table->decimal('total_allocation', 36, 18);
            $table->decimal('sold_amount', 36, 18)->default('0');
            $table->decimal('min_purchase_usd', 18, 8)->nullable();
            $table->decimal('max_purchase_usd', 18, 8)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ico_token_id', 'status']);
            $table->index(['chain_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ico_sales');
    }
};
