<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_is_online_and_user_agent(): void
    {
        $user = User::factory()->create([
            'password'       => Hash::make('Password1!'),
            'account_status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1!',
        ], ['User-Agent' => 'TestBrowser/1.0']);

        $user->refresh();
        $this->assertTrue($user->is_online);
        $this->assertNotNull($user->last_login_at);
        $this->assertEquals('TestBrowser/1.0', $user->user_agent);
    }

    public function test_logout_sets_is_online_false_and_last_logout_at(): void
    {
        $user = User::factory()->create([
            'account_status' => 'active',
            'is_online'      => true,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout');

        $user->refresh();
        $this->assertFalse($user->is_online);
        $this->assertNotNull($user->last_logout_at);
    }
}
