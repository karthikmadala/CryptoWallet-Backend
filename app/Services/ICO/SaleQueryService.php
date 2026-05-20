<?php

namespace App\Services\ICO;

use App\Enums\IcoSaleStatus;
use App\Models\IcoPurchase;
use App\Models\IcoSale;
use App\Models\IcoToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleQueryService
{
    public function listActiveTokens(): Collection
    {
        return IcoToken::with(['sales' => fn ($q) => $q->where('status', IcoSaleStatus::ACTIVE->value)])
            ->where('is_active', true)
            ->get();
    }

    public function getActiveSaleForToken(string $tokenId): ?IcoSale
    {
        return IcoSale::with('paymentMethods')
            ->where('ico_token_id', $tokenId)
            ->where('status', IcoSaleStatus::ACTIVE->value)
            ->first();
    }

    public function getSaleWithDetails(string $saleId): IcoSale
    {
        return IcoSale::with(['token', 'paymentMethods'])
            ->findOrFail($saleId);
    }

    public function getUserPurchasesForSale(string $saleId, int $userId): LengthAwarePaginator
    {
        return IcoPurchase::where('sale_id', $saleId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function getUserPurchaseHistory(int $userId): LengthAwarePaginator
    {
        return IcoPurchase::with('sale.token')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /** Total confirmed tokens purchased by a wallet address for a sale. */
    public function getWalletConfirmedAmount(string $saleId, string $fromAddress): string
    {
        $result = IcoPurchase::where('sale_id', $saleId)
            ->where('from_address', strtolower($fromAddress))
            ->where('status', 'confirmed')
            ->sum('token_amount');

        return (string) ($result ?? '0');
    }
}
