<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ico_payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ico_sale_id')->constrained('ico_sales')->cascadeOnDelete();
            $table->unsignedTinyInteger('payment_index');
            $table->string('symbol', 20);
            $table->string('contract_address', 42)->nullable();
            $table->string('chain_type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ico_sale_id', 'payment_index']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ico_payment_methods');
    }
};
