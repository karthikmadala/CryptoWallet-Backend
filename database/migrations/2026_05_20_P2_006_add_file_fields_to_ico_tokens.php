<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ico_tokens', function (Blueprint $table): void {
            $table->string('logo_path', 255)->nullable()->after('logo_url');
            $table->string('logo_original_name', 255)->nullable()->after('logo_path');
            $table->string('logo_mime_type', 100)->nullable()->after('logo_original_name');
            $table->unsignedBigInteger('logo_size')->nullable()->after('logo_mime_type');

            $table->string('whitepaper_path', 255)->nullable()->after('logo_size');
            $table->string('whitepaper_original_name', 255)->nullable()->after('whitepaper_path');
            $table->string('whitepaper_mime_type', 100)->nullable()->after('whitepaper_original_name');
            $table->unsignedBigInteger('whitepaper_size')->nullable()->after('whitepaper_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('ico_tokens', function (Blueprint $table): void {
            $table->dropColumn([
                'logo_path', 'logo_original_name', 'logo_mime_type', 'logo_size',
                'whitepaper_path', 'whitepaper_original_name', 'whitepaper_mime_type', 'whitepaper_size',
            ]);
        });
    }
};
