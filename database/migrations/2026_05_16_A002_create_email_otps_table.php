<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('otp_hash', 255);
            $table->string('otp_salt', 64);
            $table->string('purpose', 30);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('last_resend_at')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('is_consumed')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('email');
            $table->index('expires_at');
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};
