<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ico_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tx_hash', 66)->index();
            $table->string('chain_type', 20);
            $table->string('from_address', 42);
            $table->decimal('token_amount', 36, 18);
            $table->string('eth_value', 78); // wei as string — too large for decimal
            $table->unsignedTinyInteger('payment_index')->default(0);
            $table->string('status', 20)->default('submitted')->index();
            $table->unsignedSmallInteger('confirmation_attempts')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['chain_type', 'tx_hash'], 'ico_purchases_chain_tx_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ico_purchases');
    }
};
