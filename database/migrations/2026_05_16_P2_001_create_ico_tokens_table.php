<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ico_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('symbol', 20);
            $table->unsignedTinyInteger('decimals')->default(18);
            $table->string('contract_address', 42)->nullable();
            $table->string('chain_type', 20);
            $table->decimal('total_supply', 36, 18)->default(0);
            $table->text('description')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('chain_type');
            $table->index('is_active');
            $table->unique(['symbol', 'chain_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ico_tokens');
    }
};
