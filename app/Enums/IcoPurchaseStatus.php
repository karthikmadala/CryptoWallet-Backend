<?php

namespace App\Enums;

enum IcoPurchaseStatus: string
{
    case SUBMITTED = 'submitted';  // tx hash received from frontend
    case CONFIRMED = 'confirmed';  // receipt mined with status=0x1
    case FAILED    = 'failed';     // receipt mined with status=0x0, or max retries exceeded

    public function isTerminal(): bool
    {
        return $this === self::CONFIRMED || $this === self::FAILED;
    }
}
