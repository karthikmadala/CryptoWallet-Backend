<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ico_payment_methods', function (Blueprint $table): void {
            $table->string('name', 100)->nullable()->after('symbol');
            $table->unsignedTinyInteger('decimals')->default(18)->after('chain_type');
            $table->decimal('price_usd', 24, 8)->nullable()->after('decimals');
        });
    }

    public function down(): void
    {
        Schema::table('ico_payment_methods', function (Blueprint $table): void {
            $table->dropColumn(['name', 'decimals', 'price_usd']);
        });
    }
};
