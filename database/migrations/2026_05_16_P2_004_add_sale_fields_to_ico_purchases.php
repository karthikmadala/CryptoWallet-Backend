<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ico_purchases', function (Blueprint $table): void {
            $table->foreignUuid('sale_id')->nullable()->after('user_id')
                ->constrained('ico_sales')->nullOnDelete();
            $table->foreignUuid('payment_method_id')->nullable()->after('sale_id')
                ->constrained('ico_payment_methods')->nullOnDelete();

            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('ico_purchases', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['sale_id', 'payment_method_id']);
        });
    }
};
