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
                'name'           => $data['name'],
                'email'          => strtolower($data['email']),
                'password'       => Hash::make($data['password']),
                'role'           => 'user',
                'account_status' => \App\Enums\Auth\AccountStatus::PendingVerification->value,
            ]);
        });

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
        ])->save();

        return $this->issueToken($user, $data['device_name'] ?? 'api-token');
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
    }

    /** Issue a token for an externally-authenticated user (e.g. MetaMask). */
    public function issueTokenPublic(User $user, string $deviceName = 'metamask'): array
    {
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
