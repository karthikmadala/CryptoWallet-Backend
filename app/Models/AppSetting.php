<?php

namespace App\Models;

use App\Enums\LogoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'application_logo_type',
        'application_logo_path',
        'fallback_logo_path',
        'selected_ico_token_id',
        'payment_admin_wallet_address',
        'payment_admin_wallet_connected',
        'kyc_required',
    ];

    protected $hidden = [
        'application_logo_path',
        'fallback_logo_path',
    ];

    protected function casts(): array
    {
        return [
            'application_logo_type' => LogoType::class,
            'payment_admin_wallet_connected' => 'boolean',
            'kyc_required'                  => 'boolean',
        ];
    }

    public static function instance(): static
    {
        return static::firstOrCreate([], [
            'application_logo_type' => LogoType::Custom,
        ]);
    }

    public function selectedToken(): BelongsTo
    {
        return $this->belongsTo(IcoToken::class, 'selected_ico_token_id');
    }
}
