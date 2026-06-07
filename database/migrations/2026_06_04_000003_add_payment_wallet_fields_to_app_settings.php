<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('payment_admin_wallet_address', 42)->nullable()->after('selected_ico_token_id');
            $table->boolean('payment_admin_wallet_connected')->default(false)->after('payment_admin_wallet_address');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_admin_wallet_address',
                'payment_admin_wallet_connected',
            ]);
        });
    }
};
