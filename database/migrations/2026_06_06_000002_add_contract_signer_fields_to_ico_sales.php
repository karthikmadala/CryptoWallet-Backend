<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ico_sales', function (Blueprint $table): void {
            $table->string('owner_address', 42)->nullable()->after('contract_address');
            $table->string('signer_address', 42)->nullable()->after('owner_address');
            $table->text('signer_private_key_enc')->nullable()->after('signer_address');
        });
    }

    public function down(): void
    {
        Schema::table('ico_sales', function (Blueprint $table): void {
            $table->dropColumn(['owner_address', 'signer_address', 'signer_private_key_enc']);
        });
    }
};
