<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->enum('application_logo_type', ['ico', 'custom'])->default('custom');
            $table->string('application_logo_path', 255)->nullable();
            $table->string('fallback_logo_path', 255)->nullable();
            $table->char('selected_ico_token_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('selected_ico_token_id')
                ->references('id')
                ->on('ico_tokens')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
