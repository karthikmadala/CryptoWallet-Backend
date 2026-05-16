<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureVerifiedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_cannot_access_ico_routes(): void
    {
        $user = User::factory()->create([
            'account_status'    => 'pending_verification',
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
             ->getJson('/api/v1/ico/tokens')
             ->assertStatus(403);
    }

    public function test_active_verified_user_can_access_ico_routes(): void
    {
        $user = User::factory()->create([
            'account_status'    => 'active',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
             ->getJson('/api/v1/ico/tokens')
             ->assertOk();
    }
}
