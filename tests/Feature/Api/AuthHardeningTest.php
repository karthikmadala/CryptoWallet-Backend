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
}
