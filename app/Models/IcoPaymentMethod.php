<?php

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcoPaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'ico_payment_methods';

    protected $fillable = [
        'ico_sale_id',
        'payment_index',
        'symbol',
        'name',
        'contract_address',
        'chain_type',
        'decimals',
        'price_usd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'    => ChainType::class,
            'is_active'     => 'boolean',
            'payment_index' => 'integer',
            'decimals'      => 'integer',
            'price_usd'     => 'decimal:8',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(IcoSale::class, 'ico_sale_id');
    }

    public function isNative(): bool
    {
        return $this->contract_address === null;
    }
}
