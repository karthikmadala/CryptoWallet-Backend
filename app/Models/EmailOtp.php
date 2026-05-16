<?php

namespace App\Models;

use App\Enums\Auth\OtpPurpose;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOtp extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'email', 'otp_hash', 'otp_salt', 'purpose',
        'expires_at', 'verified_at', 'attempts', 'max_attempts',
        'resend_count', 'last_resend_at', 'ip_address', 'user_agent', 'is_consumed',
    ];

    protected $attributes = [
        'attempts'      => 0,
        'max_attempts'  => 5,
        'resend_count'  => 0,
        'is_consumed'   => false,
    ];

    protected function casts(): array
    {
        return [
            'expires_at'     => 'datetime',
            'verified_at'    => 'datetime',
            'last_resend_at' => 'datetime',
            'is_consumed'    => 'boolean',
            'purpose'        => OtpPurpose::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function isValid(): bool
    {
        return ! $this->is_consumed && ! $this->isExpired() && ! $this->isExhausted();
    }
}
