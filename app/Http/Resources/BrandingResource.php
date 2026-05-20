<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'logo_url'               => app(\App\Services\BrandingService::class)->resolve(),
            'logo_type'              => $this->application_logo_type instanceof \App\Enums\LogoType
                ? $this->application_logo_type->value
                : $this->application_logo_type,
            'selected_ico_token_id'  => $this->selected_ico_token_id,
            'application_logo_path'  => $this->application_logo_path,
        ];
    }
}
