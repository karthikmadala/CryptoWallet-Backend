<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_provider', 20)->default('local')->after('email');
            $table->boolean('is_online')->default(false)->after('last_login_ip');
            $table->timestamp('last_logout_at')->nullable()->after('is_online');
            $table->string('user_agent', 512)->nullable()->after('last_logout_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_provider', 'is_online', 'last_logout_at', 'user_agent']);
        });
    }
};
