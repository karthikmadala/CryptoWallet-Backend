<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            return User::create([
                'name'              => $data['name'],
                'email'             => strtolower($data['email']),
                'password'          => Hash::make($data['password']),
                'role'              => 'user',
                'account_status'    => \App\Enums\Auth\AccountStatus::PendingVerification->value,
                'auth_provider'     => 'local',
                'menu_restrictions' => ['ico', 'staking'],
                'encryption_salt'   => base64_encode(random_bytes(16)),
            ]);
        });

        event(new \App\Events\Auth\UserRegistered($user, request()->ip()));
        return ['user' => $user, 'requires_otp' => true];
    }

    public function login(array $data): array
    {
        $user = User::query()
            ->where('email', strtolower($data['email']))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $status = $user->account_status ?? \App\Enums\Auth\AccountStatus::Active;

        if ($status === \App\Enums\Auth\AccountStatus::Suspended || $status === \App\Enums\Auth\AccountStatus::Locked) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Account is ' . $status->value . '. Contact support.',
                    'data'    => null,
                    'errors'  => null,
                ], 403)
            );
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'is_online'     => true,
            'user_agent'    => mb_substr(request()->userAgent() ?? '', 0, 512, 'UTF-8'),
        ])->save();

        $result = $this->issueToken($user, $data['device_name'] ?? 'api-token');
        event(new \App\Events\Auth\UserLoggedIn($user, request()->ip()));
        return $result;
    }

    public function refresh(User $user): array
    {
        $current = $user->currentAccessToken();
        $deviceName = $current?->name ?? 'api-token';

        if ($current) {
            $current->delete();
        }

        return $this->issueToken($user, $deviceName);
    }

    public function logout(User $user): void
    {
        $user->forceFill([
            'is_online'      => false,
            'last_logout_at' => now(),
        ])->save();

        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is invalid.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();
        $user->tokens()->delete();
        event(new \App\Events\Auth\PasswordChanged($user, request()->ip()));
    }

    /** Issue a token for an externally-authenticated user (e.g. MetaMask). */
    public function issueTokenPublic(User $user, string $deviceName = 'metamask'): array
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'is_online'     => true,
            'user_agent'    => mb_substr(request()->userAgent() ?? '', 0, 512, 'UTF-8'),
        ])->save();

        return $this->issueToken($user, $deviceName);
    }

    private function issueToken(User $user, string $deviceName): array
    {
        $user->tokens()
            ->where('name', $deviceName)
            ->delete();

        $token = $user->createToken(
            $deviceName,
            ['api:access'],
            now()->addMinutes((int) config('sanctum.expiration', 10080))
        );

        return [
            'user'             => $user,
            'token'            => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toISOString(),
        ];
    }
}
