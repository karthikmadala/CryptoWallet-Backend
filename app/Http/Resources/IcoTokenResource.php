<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IcoTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'symbol'           => $this->symbol,
            'decimals'         => $this->decimals,
            'contract_address' => $this->contract_address,
            'chain'            => $this->chain_type->value,
            'total_supply'     => $this->total_supply,
            'description'      => $this->description,
            // Logo: resolved public URL (path takes priority over legacy URL)
            'logo_url'              => $this->logo_path
                ? asset('storage/' . $this->logo_path)
                : $this->logo_url,
            'logo_path'             => $this->logo_path,
            'logo_original_name'    => $this->logo_original_name,
            'logo_mime_type'        => $this->logo_mime_type,
            'logo_size'             => $this->logo_size,
            // Whitepaper
            'whitepaper_url'             => $this->whitepaper_path
                ? asset('storage/' . $this->whitepaper_path)
                : null,
            'whitepaper_original_name'   => $this->whitepaper_original_name,
            'whitepaper_mime_type'       => $this->whitepaper_mime_type,
            'whitepaper_size'            => $this->whitepaper_size,
            'is_active'        => $this->is_active,
            'sales_count'      => $this->whenLoaded('sales', fn () => $this->sales->count(), 0),
            'created_at'       => $this->created_at?->toIso8601String(),
            'deleted_at'       => $this->deleted_at?->toIso8601String(),
        ];
    }
}
