<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\IcoPurchaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcoPurchase extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'sale_id',
        'payment_method_id',
        'tx_hash',
        'chain_type',
        'from_address',
        'token_amount',
        'eth_value',
        'payment_index',
        'status',
        'confirmation_attempts',
        'confirmed_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'            => ChainType::class,
            'status'                => IcoPurchaseStatus::class,
            'token_amount'          => 'decimal:18',
            'payment_index'         => 'integer',
            'confirmation_attempts' => 'integer',
            'confirmed_at'          => 'datetime',
            'failed_at'             => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(IcoSale::class, 'sale_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(IcoPaymentMethod::class, 'payment_method_id');
    }
}
