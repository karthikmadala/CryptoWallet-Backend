<?php

namespace App\Services\ICO;

use App\Enums\IcoSaleStatus;
use App\Exceptions\ICOException;
use App\Models\IcoPaymentMethod;
use App\Models\IcoSale;
use App\Services\ICOService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrchestratorService
{
    public function __construct(
        private readonly AllocationService $allocation,
        private readonly SignatureService  $signature,
        private readonly ICOService        $icoService,
    ) {}

    /**
     * Validate everything and return unsigned tx params + the backend signature.
     * Caller (controller) dispatches ConfirmIcoPurchaseJob after receiving the tx hash.
     *
     * @return array{tx_params: array, signature: array, payment_method: IcoPaymentMethod}
     */
    public function preparePurchase(
        IcoSale $sale,
        string $paymentMethodId,
        string $walletAddress,
        string $tokenAmount,
        string $paymentAmount,
    ): array {
        $this->validateSaleIsOpen($sale);

        $paymentMethod = $sale->paymentMethods()
            ->where('id', $paymentMethodId)
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => ['Payment method not available for this sale.'],
            ]);
        }

        // Lock sale row and validate allocation inside a transaction
        DB::transaction(function () use ($sale, $tokenAmount, $walletAddress): void {
            IcoSale::lockForUpdate()->find($sale->id);
            $this->allocation->validatePurchase($sale->fresh(), $tokenAmount, $walletAddress);
        });

        $sig = $this->signature->createSaleSignature(
            $sale,
            $paymentMethod,
            $walletAddress,
            $tokenAmount,
            $paymentAmount,
        );

        $txParams = $this->icoService->prepareBuyTokensTransaction(
            $walletAddress,
            $paymentMethod->payment_index,
            $tokenAmount,
            $sig,
            $paymentAmount,
            $sale->chain_type,
        );

        // Include the sale contract address (overrides config-based address)
        $txParams['to'] = $sale->contract_address;

        return [
            'tx_params'      => $txParams,
            'signature'      => $sig,
            'payment_method' => $paymentMethod,
        ];
    }

    private function validateSaleIsOpen(IcoSale $sale): void
    {
        if (! $sale->status->isActive()) {
            throw ValidationException::withMessages([
                'sale_id' => ['This sale is not currently active.'],
            ]);
        }

        if (! $sale->isWithinSchedule()) {
            throw ValidationException::withMessages([
                'sale_id' => ['This sale is outside its scheduled window.'],
            ]);
        }

        $remaining = $sale->remainingAllocation();
        if (bccomp($remaining, '0', 18) <= 0) {
            throw ValidationException::withMessages([
                'sale_id' => ['This sale is sold out.'],
            ]);
        }
    }
}
