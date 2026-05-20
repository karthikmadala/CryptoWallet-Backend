<?php

namespace App\Services\ICO;

use App\Enums\ChainType;
use App\Enums\IcoSaleStatus;
use App\Enums\IcoSaleType;
use App\Exceptions\ICOException;
use App\Models\IcoPaymentMethod;
use App\Models\IcoSale;
use App\Models\IcoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleManagementService
{
    // ── Token management ─────────────────────────────────────────────────────

    public function createToken(array $data, ?Request $request = null): IcoToken
    {
        $files    = app(\App\Services\FileUploadService::class);
        $logoData = [];
        $wpData   = [];

        if ($request?->hasFile('logo')) {
            $logoData = $files->storeLogo($request->file('logo'), null);
            \App\Jobs\ProcessLogoUploadJob::dispatch($logoData['path']);
        }

        if ($request?->hasFile('whitepaper')) {
            $wpData = $files->storeWhitepaper($request->file('whitepaper'), null);
        }

        return IcoToken::create([
            'name'             => $data['name'],
            'symbol'           => strtoupper($data['symbol']),
            'decimals'         => $data['decimals'] ?? 18,
            'contract_address' => $data['contract_address'] ?? null,
            'chain_type'       => $data['chain_type'],
            'total_supply'     => $data['total_supply'] ?? '0',
            'description'      => $data['description'] ?? null,
            'logo_url'         => $data['logo_url'] ?? null,
            'logo_path'             => $logoData['path'] ?? null,
            'logo_original_name'    => $logoData['original_name'] ?? null,
            'logo_mime_type'        => $logoData['mime_type'] ?? null,
            'logo_size'             => $logoData['size'] ?? null,
            'whitepaper_path'            => $wpData['path'] ?? null,
            'whitepaper_original_name'   => $wpData['original_name'] ?? null,
            'whitepaper_mime_type'       => $wpData['mime_type'] ?? null,
            'whitepaper_size'            => $wpData['size'] ?? null,
            'is_active'        => $data['is_active'] ?? false,
        ]);
    }

    public function updateToken(IcoToken $token, array $data, ?Request $request = null): IcoToken
    {
        $files   = app(\App\Services\FileUploadService::class);
        $updates = array_filter([
            'name'             => $data['name'] ?? null,
            'symbol'           => isset($data['symbol']) ? strtoupper($data['symbol']) : null,
            'decimals'         => $data['decimals'] ?? null,
            'contract_address' => $data['contract_address'] ?? null,
            'chain_type'       => $data['chain_type'] ?? null,
            'total_supply'     => $data['total_supply'] ?? null,
            'description'      => $data['description'] ?? null,
            'logo_url'         => $data['logo_url'] ?? null,
            'is_active'        => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request?->hasFile('logo')) {
            $logoData = $files->storeLogo($request->file('logo'), $token->logo_path);
            \App\Jobs\ProcessLogoUploadJob::dispatch($logoData['path']);
            $updates = array_merge($updates, [
                'logo_path'          => $logoData['path'],
                'logo_original_name' => $logoData['original_name'],
                'logo_mime_type'     => $logoData['mime_type'],
                'logo_size'          => $logoData['size'],
            ]);
        }

        if ($request?->hasFile('whitepaper')) {
            $wpData  = $files->storeWhitepaper($request->file('whitepaper'), $token->whitepaper_path);
            $updates = array_merge($updates, [
                'whitepaper_path'          => $wpData['path'],
                'whitepaper_original_name' => $wpData['original_name'],
                'whitepaper_mime_type'     => $wpData['mime_type'],
                'whitepaper_size'          => $wpData['size'],
            ]);
        }

        $token->update($updates);

        return $token->fresh();
    }

    // ── Sale management ──────────────────────────────────────────────────────

    public function createSale(IcoToken $token, array $data): IcoSale
    {
        return IcoSale::create([
            'ico_token_id'     => $token->id,
            'sale_type'        => $data['sale_type'],
            'status'           => IcoSaleStatus::DRAFT->value,
            'contract_address' => $data['contract_address'],
            'chain_type'       => $data['chain_type'],
            'token_price_usd'  => $data['token_price_usd'],
            'total_allocation' => $data['total_allocation'],
            'sold_amount'      => '0',
            'min_purchase_usd' => $data['min_purchase_usd'] ?? null,
            'max_purchase_usd' => $data['max_purchase_usd'] ?? null,
            'starts_at'        => $data['starts_at'] ?? null,
            'ends_at'          => $data['ends_at'] ?? null,
        ]);
    }

    public function updateSale(IcoSale $sale, array $data): IcoSale
    {
        if ($sale->status->isActive()) {
            // Only scheduling fields can change while active
            $allowed = ['starts_at', 'ends_at', 'min_purchase_usd', 'max_purchase_usd'];
            $data    = array_intersect_key($data, array_flip($allowed));
        }

        $sale->update(array_filter($data, fn ($v) => $v !== null));

        return $sale->fresh();
    }

    public function activateSale(IcoSale $sale): IcoSale
    {
        if (! $sale->status->canActivate()) {
            throw new ICOException("Sale cannot be activated from status: {$sale->status->value}");
        }

        // Only one active sale per token
        $hasActive = IcoSale::where('ico_token_id', $sale->ico_token_id)
            ->where('id', '!=', $sale->id)
            ->where('status', IcoSaleStatus::ACTIVE->value)
            ->exists();

        if ($hasActive) {
            throw new ICOException('Another sale for this token is already active.');
        }

        $sale->update(['status' => IcoSaleStatus::ACTIVE->value]);

        return $sale->fresh();
    }

    public function pauseSale(IcoSale $sale): IcoSale
    {
        if (! $sale->status->canPause()) {
            throw new ICOException("Sale cannot be paused from status: {$sale->status->value}");
        }

        $sale->update(['status' => IcoSaleStatus::PAUSED->value]);

        return $sale->fresh();
    }

    public function endSale(IcoSale $sale): IcoSale
    {
        if (! $sale->status->canEnd()) {
            throw new ICOException("Sale cannot be ended from status: {$sale->status->value}");
        }

        $sale->update(['status' => IcoSaleStatus::ENDED->value]);

        return $sale->fresh();
    }

    // ── Payment method management ────────────────────────────────────────────

    public function addPaymentMethod(IcoSale $sale, array $data): IcoPaymentMethod
    {
        return IcoPaymentMethod::create([
            'ico_sale_id'      => $sale->id,
            'payment_index'    => $data['payment_index'],
            'symbol'           => strtoupper($data['symbol']),
            'contract_address' => $data['contract_address'] ?? null,
            'chain_type'       => $data['chain_type'],
            'is_active'        => $data['is_active'] ?? true,
        ]);
    }

    public function updatePaymentMethod(IcoPaymentMethod $method, array $data): IcoPaymentMethod
    {
        $method->update(array_filter([
            'symbol'           => isset($data['symbol']) ? strtoupper($data['symbol']) : null,
            'contract_address' => $data['contract_address'] ?? null,
            'is_active'        => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return $method->fresh();
    }

    public function deletePaymentMethod(IcoPaymentMethod $method): void
    {
        if ($method->sale->status->isActive()) {
            throw new ICOException('Cannot remove payment method from an active sale.');
        }

        $method->delete();
    }
}
