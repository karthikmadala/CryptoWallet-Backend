<?php

namespace Tests\Feature\Api;

use App\Enums\Auth\OtpPurpose;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = app(OtpService::class);
    }

    public function test_generate_creates_otp_record(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');

        $this->assertDatabaseHas('email_otps', [
            'user_id'     => $user->id,
            'purpose'     => OtpPurpose::Registration->value,
            'is_consumed' => false,
        ]);
    }

    public function test_generate_returns_6_digit_string(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $otp = $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    }

    public function test_verify_returns_true_for_correct_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $plainOtp = $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');
        $result   = $this->otpService->verify($user, $plainOtp, OtpPurpose::Registration);

        $this->assertTrue($result);
    }

    public function test_verify_returns_false_for_wrong_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');
        $result = $this->otpService->verify($user, '000000', OtpPurpose::Registration);

        $this->assertFalse($result);
    }

    public function test_otp_is_consumed_after_successful_verify(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $plainOtp = $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');
        $this->otpService->verify($user, $plainOtp, OtpPurpose::Registration);

        $this->assertDatabaseHas('email_otps', [
            'user_id'     => $user->id,
            'is_consumed' => true,
        ]);
    }

    public function test_verify_fails_after_max_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->otpService->verify($user, '000000', OtpPurpose::Registration);
        }

        $otp = EmailOtp::where('user_id', $user->id)->latest()->first();
        $this->assertTrue($otp->isExhausted());
    }

    public function test_generate_invalidates_previous_otp_for_same_purpose(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');
        $this->otpService->generate($user, OtpPurpose::Registration, '127.0.0.1');

        $count = EmailOtp::where('user_id', $user->id)
            ->where('purpose', OtpPurpose::Registration->value)
            ->where('is_consumed', false)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_verify_otp_endpoint_activates_account(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'account_status'    => 'pending_verification',
            'email_verified_at' => null,
        ]);
        $otp = app(OtpService::class)->generate($user, OtpPurpose::Registration, '127.0.0.1');

        $this->postJson('/api/v1/auth/verify-otp', [
            'email'   => $user->email,
            'otp'     => $otp,
            'purpose' => 'registration',
        ])->assertOk()
          ->assertJsonPath('data.token', fn($t) => ! empty($t));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'active']);
    }

    public function test_wrong_otp_returns_422(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(OtpService::class)->generate($user, OtpPurpose::Registration, '127.0.0.1');

        $this->postJson('/api/v1/auth/verify-otp', [
            'email'   => $user->email,
            'otp'     => '000000',
            'purpose' => 'registration',
        ])->assertStatus(422);
    }

    public function test_forgot_password_always_returns_200(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin@example.com',
        ])->assertOk();
    }

    public function test_reset_password_with_valid_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('oldpassword'),
        ]);
        $otp = app(OtpService::class)->generate($user, OtpPurpose::PasswordReset, '127.0.0.1');

        $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => $user->email,
            'otp'                   => $otp,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('newpassword123', $user->fresh()->password)
        );
    }
}
