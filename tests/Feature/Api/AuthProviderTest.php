<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_sets_auth_provider_local(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('local', $user->auth_provider);
    }

    public function test_user_resource_exposes_auth_provider(): void
    {
        $user = User::factory()->create([
            'auth_provider'  => 'local',
            'account_status' => 'active',
            'password'       => Hash::make('Password1!'),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.auth_provider', 'local');
    }
}
