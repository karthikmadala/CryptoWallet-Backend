<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Auth\AccountStatus;
use App\Enums\Auth\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Wallet\MetaMaskNonceRequest;
use App\Http\Requests\Wallet\MetaMaskVerifyRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleAuthService;
use App\Services\Auth\OtpService;
use App\Services\AuthService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly WalletService $walletService,
        private readonly OtpService $otpService,
        private readonly GoogleAuthService $googleAuthService,
    ) {}

    /** POST /api/v1/auth/register */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        $this->otpService->generate(
            $result['user'],
            \App\Enums\Auth\OtpPurpose::Registration,
            $request->ip(),
            $request->userAgent()
        );

        return api_response(true, 'Registration successful. Check your email for a verification code.', [
            'user'         => new UserResource($result['user']),
            'requires_otp' => true,
            'email'        => $result['user']->email,
        ], null, 201);
    }

    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return api_response(true, 'Login successful.', [
            'user'             => new UserResource($result['user']),
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /** POST /api/v1/auth/refresh  (authenticated) */
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh(auth()->user());

        return api_response(true, 'Token refreshed.', [
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /** POST /api/v1/auth/logout  (authenticated) */
    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());

        return api_response(true, 'Logged out successfully.');
    }

    // ─── MetaMask login flow (public — no Sanctum required) ──────────────────

    /** POST /api/v1/auth/metamask/nonce */
    public function metamaskNonce(MetaMaskNonceRequest $request): JsonResponse
    {
        $nonce = $this->walletService->generateLoginNonce($request->validated('address'));

        return api_response(true, 'Sign this nonce with MetaMask.', ['nonce' => $nonce]);
    }

    /** POST /api/v1/auth/metamask/verify */
    public function metamaskVerify(MetaMaskVerifyRequest $request): JsonResponse
    {
        $user = $this->walletService->verifyLoginSignature(
            $request->validated('address'),
            $request->validated('signature')
        );

        $result = $this->authService->issueTokenPublic($user);

        return api_response(true, 'MetaMask login successful.', [
            'user'             => new UserResource($user),
            'token'            => $result['token'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    /** POST /api/v1/auth/verify-otp */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user    = \App\Models\User::where('email', $request->validated('email'))->firstOrFail();
        $purpose = OtpPurpose::from($request->validated('purpose'));
        $verified = $this->otpService->verify($user, $request->validated('otp'), $purpose);

        if (! $verified) {
            return api_response(false, 'Invalid or expired OTP.', null, null, 422);
        }

        if ($purpose === OtpPurpose::Registration) {
            $user->forceFill([
                'email_verified_at' => now(),
                'account_status'    => AccountStatus::Active->value,
            ])->save();

            $result = $this->authService->issueTokenPublic($user, 'api-token');

            return api_response(true, 'Email verified. Welcome to CryptoVault!', [
                'user'             => new UserResource($result['user']),
                'token'            => $result['token'],
                'token_expires_at' => $result['token_expires_at'],
            ]);
        }

        return api_response(true, 'OTP verified.', ['verified' => true]);
    }

    /** POST /api/v1/auth/resend-otp */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user    = \App\Models\User::where('email', $request->validated('email'))->firstOrFail();
        $purpose = OtpPurpose::from($request->validated('purpose'));

        try {
            $this->otpService->resend($user, $purpose, $request->ip(), $request->userAgent());
        } catch (\RuntimeException $e) {
            return api_response(false, $e->getMessage(), null, null, 429);
        }

        return api_response(true, 'OTP resent. Check your email.');
    }

    /** POST /api/v1/auth/forgot-password */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = \App\Models\User::where('email', $request->validated('email'))->first();

        if ($user) {
            $this->otpService->generate(
                $user,
                OtpPurpose::PasswordReset,
                $request->ip(),
                $request->userAgent()
            );
        }

        return api_response(true, 'If that email exists, a reset code has been sent.');
    }

    /** POST /api/v1/auth/reset-password */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user     = \App\Models\User::where('email', $request->validated('email'))->firstOrFail();
        $verified = $this->otpService->verify($user, $request->validated('otp'), OtpPurpose::PasswordReset);

        if (! $verified) {
            return api_response(false, 'Invalid or expired reset code.', null, null, 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();
        $user->tokens()->delete();

        return api_response(true, 'Password reset successful. Please log in.');
    }

    /** GET /api/v1/auth/google/redirect */
    public function googleRedirect(): JsonResponse
    {
        $url = $this->googleAuthService->getRedirectUrl();
        return api_response(true, 'Google OAuth redirect URL.', ['redirect_url' => $url]);
    }

    /** GET /api/v1/auth/google/callback */
    public function googleCallback(): JsonResponse
    {
        try {
            $result = $this->googleAuthService->handleCallback();
        } catch (\Throwable $e) {
            return api_response(false, 'Google authentication failed.', null, null, 401);
        }

        $tokenResult = $this->authService->issueTokenPublic($result['user'], 'google');

        return api_response(true, 'Google login successful.', [
            'user'             => new UserResource($tokenResult['user']),
            'token'            => $tokenResult['token'],
            'token_expires_at' => $tokenResult['token_expires_at'],
        ]);
    }
}
