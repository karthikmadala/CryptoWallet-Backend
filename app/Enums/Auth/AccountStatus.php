<?php

namespace App\Enums\Auth;

enum AccountStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active              = 'active';
    case Suspended           = 'suspended';
    case Locked              = 'locked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canLogin(): bool
    {
        return $this === self::Active || $this === self::PendingVerification;
    }
}
