<?php

namespace App\Enums;

enum IcoSaleType: string
{
    case PRE_SALE     = 'pre_sale';
    case PUBLIC_SALE  = 'public_sale';
    case PRIVATE_SALE = 'private_sale';

    public function label(): string
    {
        return match ($this) {
            self::PRE_SALE     => 'Pre-Sale',
            self::PUBLIC_SALE  => 'Public Sale',
            self::PRIVATE_SALE => 'Private Sale',
        };
    }
}
