<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'application_logo_type' => 'string',
        ];
    }

    public function selectedToken(): BelongsTo
    {
        return $this->belongsTo(IcoToken::class, 'selected_ico_token_id');
    }
}
