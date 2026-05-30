<?php

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IcoToken extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'ico_tokens';

    protected $fillable = [
        'name',
        'symbol',
        'decimals',
        'contract_address',
        'chain_type',
        'total_supply',
        'description',
        'logo_url',
        'logo_path',
        'logo_original_name',
        'logo_mime_type',
        'logo_size',
        'whitepaper_path',
        'whitepaper_original_name',
        'whitepaper_mime_type',
        'whitepaper_size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'chain_type'      => ChainType::class,
            'total_supply'    => 'decimal:18',
            'is_active'       => 'boolean',
            'decimals'        => 'integer',
            'logo_size'       => 'integer',
            'whitepaper_size' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(IcoSale::class);
    }

    public function activeSale(): ?IcoSale
    {
        return $this->sales()
            ->where('status', 'active')
            ->first();
    }
}
