<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_returns_url(): void
    {
        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('getRedirectUrl')
             ->once()
             ->andReturn('https://accounts.google.com/o/oauth2/auth?test=1');

        $this->app->instance(GoogleAuthService::class, $mock);

        $this->getJson('/api/v1/auth/google/redirect')
             ->assertOk()
             ->assertJsonPath('data.redirect_url', 'https://accounts.google.com/o/oauth2/auth?test=1');
    }

    public function test_google_callback_issues_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'account_status'    => 'active',
        ]);

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('handleCallback')
             ->once()
             ->andReturn(['user' => $user, 'is_new' => false]);

        $this->app->instance(GoogleAuthService::class, $mock);

        $this->getJson('/api/v1/auth/google/callback')
             ->assertOk()
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.token', fn($t) => ! empty($t));
    }

    public function test_google_callback_returns_401_on_failure(): void
    {
        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('handleCallback')
             ->once()
             ->andThrow(new \Exception('OAuth failed'));

        $this->app->instance(GoogleAuthService::class, $mock);

        $this->getJson('/api/v1/auth/google/callback')
             ->assertStatus(401);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
