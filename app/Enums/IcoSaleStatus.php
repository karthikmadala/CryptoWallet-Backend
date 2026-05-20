<?php

namespace App\Enums;

enum IcoSaleStatus: string
{
    case DRAFT  = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ENDED  = 'ended';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canActivate(): bool
    {
        return $this === self::DRAFT || $this === self::PAUSED;
    }

    public function canPause(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canEnd(): bool
    {
        return $this === self::ACTIVE || $this === self::PAUSED;
    }
}
