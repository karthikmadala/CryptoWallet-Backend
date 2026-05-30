<?php

namespace App\Services\Auth;

use App\Enums\Auth\AccountStatus;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthService
{
    private const SODIUM_KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    public function getRedirectUrl(): string
    {
        return Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
    }

    public function handleCallback(): array
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $existingSocial = SocialAccount::where('provider', 'google')
            ->where('provider_user_id', $googleUser->getId())
            ->with('user')
            ->first();

        if ($existingSocial) {
            $this->updateTokens($existingSocial, $googleUser);
            return ['user' => $existingSocial->user, 'is_new' => false];
        }

        $user = User::where('email', strtolower($googleUser->getEmail()))->first();

        if (! $user) {
            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => strtolower($googleUser->getEmail()),
                'password'          => Hash::make(Str::random(32)),
                'role'              => 'user',
                'auth_provider'     => 'google',
                'account_status'    => AccountStatus::Active->value,
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
                'account_status'    => AccountStatus::Active->value,
                'auth_provider'     => 'google',
            ])->save();
        }

        SocialAccount::create([
            'user_id'           => $user->id,
            'provider'          => 'google',
            'provider_user_id'  => $googleUser->getId(),
            'provider_email'    => $googleUser->getEmail(),
            'provider_avatar'   => $googleUser->getAvatar(),
            'access_token_enc'  => $this->encrypt($googleUser->token),
            'refresh_token_enc' => $googleUser->refreshToken ? $this->encrypt($googleUser->refreshToken) : null,
            'token_expires_at'  => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
        ]);

        return ['user' => $user->fresh(), 'is_new' => $user->wasRecentlyCreated];
    }

    private function updateTokens(SocialAccount $account, $googleUser): void
    {
        $account->forceFill([
            'access_token_enc'  => $this->encrypt($googleUser->token),
            'refresh_token_enc' => $googleUser->refreshToken
                ? $this->encrypt($googleUser->refreshToken)
                : $account->refresh_token_enc,
            'token_expires_at'  => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
        ])->save();
    }

    private function encrypt(string $plaintext): string
    {
        $key    = sodium_crypto_generichash(config('app.key'), 'google-oauth-token-encryption-v1', self::SODIUM_KEY_BYTES);
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        sodium_memzero($key);

        return base64_encode($nonce . $cipher);
    }
}
