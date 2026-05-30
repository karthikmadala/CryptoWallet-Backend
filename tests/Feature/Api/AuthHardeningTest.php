<?php
namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_auth_hardening_columns(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumns('users', [
                'account_status', 'last_login_at', 'last_login_ip',
            ])
        );
    }

    public function test_account_status_defaults_to_active_for_new_users(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->assertEquals(\App\Enums\Auth\AccountStatus::Active, $user->fresh()->account_status);
    }

    public function test_is_active_helper(): void
    {
        $user = \App\Models\User::factory()->create(['account_status' => 'active']);
        $this->assertTrue($user->isActive());
    }

    public function test_is_pending_verification_helper(): void
    {
        $user = \App\Models\User::factory()->create(['account_status' => 'pending_verification']);
        $this->assertTrue($user->isPendingVerification());
        $this->assertFalse($user->isActive());
    }

    public function test_password_is_hashed_with_argon2id(): void
    {
        $hash = \Illuminate\Support\Facades\Hash::make('secret');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = \App\Models\User::factory()->create([
            'password'       => \Illuminate\Support\Facades\Hash::make('password'),
            'account_status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_locked_user_cannot_login(): void
    {
        $user = \App\Models\User::factory()->create([
            'password'       => \Illuminate\Support\Facades\Hash::make('password'),
            'account_status' => 'locked',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_login_updates_last_login_fields(): void
    {
        $user = \App\Models\User::factory()->create([
            'password'       => \Illuminate\Support\Facades\Hash::make('password'),
            'account_status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_social_account_can_be_created_and_linked(): void
    {
        $user = \App\Models\User::factory()->create();

        $social = \App\Models\SocialAccount::create([
            'user_id'          => $user->id,
            'provider'         => 'google',
            'provider_user_id' => '123456789',
            'provider_email'   => $user->email,
        ]);

        $this->assertNotNull($social->id);
        $this->assertEquals('google', $social->provider);
        $this->assertCount(1, $user->socialAccounts);
    }

    public function test_email_otp_model_can_be_created(): void
    {
        $user = \App\Models\User::factory()->create();
        $otp = \App\Models\EmailOtp::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'otp_hash'   => 'testhash',
            'otp_salt'   => 'testsalt',
            'purpose'    => \App\Enums\Auth\OtpPurpose::Registration->value,
            'expires_at' => now()->addMinutes(5),
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertNotNull($otp->id);
        $this->assertFalse($otp->is_consumed);
        $this->assertFalse($otp->isExpired());
        $this->assertFalse($otp->isExhausted());
        $this->assertTrue($otp->isValid());
        $this->assertEquals(\App\Enums\Auth\OtpPurpose::Registration, $otp->purpose);
    }
}
