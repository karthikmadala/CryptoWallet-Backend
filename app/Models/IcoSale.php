<?php

namespace App\Models;

use App\Enums\ChainType;
use App\Enums\IcoSaleStatus;
use App\Enums\IcoSaleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IcoSale extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'ico_sales';

    protected $fillable = [
        'ico_token_id',
        'sale_type',
        'status',
        'contract_address',
        'owner_address',
        'signer_address',
        'signer_private_key_enc',
        'chain_type',
        'token_price_usd',
        'total_allocation',
        'sold_amount',
        'min_purchase_usd',
        'max_purchase_usd',
        'starts_at',
        'ends_at',
    ];

    protected $hidden = ['signer_private_key_enc'];

    protected function casts(): array
    {
        return [
            'sale_type'        => IcoSaleType::class,
            'status'           => IcoSaleStatus::class,
            'chain_type'       => ChainType::class,
            'token_price_usd'  => 'decimal:8',
            'total_allocation' => 'decimal:18',
            'sold_amount'      => 'decimal:18',
            'min_purchase_usd' => 'decimal:8',
            'max_purchase_usd' => 'decimal:8',
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(IcoToken::class, 'ico_token_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(IcoPaymentMethod::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(IcoPurchase::class, 'sale_id');
    }

    public function remainingAllocation(): string
    {
        return bcsub((string) $this->total_allocation, (string) $this->sold_amount, 18);
    }

    public function isWithinSchedule(): bool
    {
        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
