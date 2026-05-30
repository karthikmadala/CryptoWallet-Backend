<?php

namespace App\Services\ICO;

use App\Exceptions\ICOException;
use App\Models\IcoPurchase;
use App\Models\IcoSale;
use Illuminate\Support\Facades\DB;

class AllocationService
{
    /**
     * Validate that a token amount is available and wallet limits are respected.
     * Must be called inside a DB transaction with lockForUpdate on the sale.
     */
    public function validatePurchase(IcoSale $sale, string $tokenAmount, string $fromAddress): void
    {
        $remaining = $sale->remainingAllocation();

        if (bccomp($tokenAmount, $remaining, 18) > 0) {
            throw new ICOException(
                "Insufficient allocation. Requested {$tokenAmount}, available {$remaining}."
            );
        }

        if ($sale->max_purchase_usd !== null) {
            $purchaseUsd = bcmul($tokenAmount, (string) $sale->token_price_usd, 8);
            $walletConfirmed = $this->getWalletConfirmedUsd($sale, $fromAddress);
            $totalAfter = bcadd($walletConfirmed, $purchaseUsd, 8);

            if (bccomp($totalAfter, (string) $sale->max_purchase_usd, 8) > 0) {
                throw new ICOException(
                    "Purchase exceeds maximum wallet allocation of \${$sale->max_purchase_usd}."
                );
            }
        }

        if ($sale->min_purchase_usd !== null) {
            $purchaseUsd = bcmul($tokenAmount, (string) $sale->token_price_usd, 8);

            if (bccomp($purchaseUsd, (string) $sale->min_purchase_usd, 8) < 0) {
                throw new ICOException(
                    "Purchase below minimum of \${$sale->min_purchase_usd}."
                );
            }
        }
    }

    /**
     * Increment sold_amount after a confirmed purchase.
     * Must be called inside a DB transaction.
     */
    public function finalizeAllocation(IcoPurchase $purchase): void
    {
        if (! $purchase->sale_id) {
            return;
        }

        DB::table('ico_sales')
            ->where('id', $purchase->sale_id)
            ->update([
                'sold_amount' => DB::raw("sold_amount + {$purchase->token_amount}"),
                'updated_at'  => now(),
            ]);
    }

    private function getWalletConfirmedUsd(IcoSale $sale, string $fromAddress): string
    {
        $confirmedTokens = IcoPurchase::where('sale_id', $sale->id)
            ->where('from_address', strtolower($fromAddress))
            ->where('status', 'confirmed')
            ->sum('token_amount');

        return bcmul((string) $confirmedTokens, (string) $sale->token_price_usd, 8);
    }
}
