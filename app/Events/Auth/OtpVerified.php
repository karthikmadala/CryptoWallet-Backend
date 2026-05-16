<?php

namespace App\Events\Auth;

use App\Enums\Auth\OtpPurpose;
use App\Models\User;

class OtpVerified
{
    public function __construct(
        public readonly User $user,
        public readonly OtpPurpose $purpose,
        public readonly string $ip,
    ) {}
}
