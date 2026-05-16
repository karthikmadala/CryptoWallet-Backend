<?php

namespace App\Services\Auth;

use App\Enums\Auth\OtpPurpose;
use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const OTP_TTL_MINUTES   = 5;
    private const MAX_RESEND_WINDOW = 15;
    private const MAX_RESEND_COUNT  = 3;

    /**
     * Generate a new OTP, invalidate previous ones for the same purpose,
     * queue the mail, and return the plain-text code.
     */
    public function generate(User $user, OtpPurpose $purpose, string $ip, ?string $userAgent = null): string
    {
        EmailOtp::where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->where('is_consumed', false)
            ->update(['is_consumed' => true]);

        $plain = (string) random_int(100000, 999999);
        $salt  = bin2hex(random_bytes(16));
        $hash  = hash_hmac('sha256', $plain . $salt, config('app.key'));

        EmailOtp::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'otp_hash'   => $hash,
            'otp_salt'   => $salt,
            'purpose'    => $purpose->value,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        Mail::to($user->email)->queue(new OtpMail($user, $plain, $purpose));

        return $plain;
    }

    /**
     * Verify a submitted OTP. Returns true on success, false on failure.
     */
    public function verify(User $user, string $submitted, OtpPurpose $purpose): bool
    {
        $record = EmailOtp::where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->where('is_consumed', false)
            ->latest()
            ->first();

        if (! $record || ! $record->isValid()) {
            return false;
        }

        $record->increment('attempts');

        $computedHash = hash_hmac('sha256', $submitted . $record->otp_salt, config('app.key'));

        if (! hash_equals($record->otp_hash, $computedHash)) {
            return false;
        }

        $record->forceFill([
            'is_consumed' => true,
            'verified_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Resend OTP for the user. Throws \RuntimeException when resend limit is hit.
     */
    public function resend(User $user, OtpPurpose $purpose, string $ip, ?string $userAgent = null): void
    {
        $latest = EmailOtp::where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->where('is_consumed', false)
            ->latest()
            ->first();

        $windowStart = now()->subMinutes(self::MAX_RESEND_WINDOW);

        if (
            $latest &&
            $latest->last_resend_at &&
            $latest->last_resend_at->greaterThan($windowStart) &&
            $latest->resend_count >= self::MAX_RESEND_COUNT
        ) {
            throw new \RuntimeException('OTP resend limit reached. Please wait before trying again.');
        }

        if ($latest && ! $latest->is_consumed) {
            $latest->increment('resend_count');
            $latest->forceFill(['last_resend_at' => now()])->save();
        }

        $this->generate($user, $purpose, $ip, $userAgent);
    }
}
