<?php

namespace App\Services\ICO;

use App\Exceptions\ICOException;
use App\Models\IcoPaymentMethod;
use App\Models\IcoSale;
use App\Models\IcoUsedNonce;
use App\Services\Crypto\BlockchainNodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignatureService
{
    public function __construct(
        private readonly BlockchainNodeService $nodeService,
    ) {}

    /**
     * Generate a single-use backend signature for an ICO sale purchase.
     * Nonce is stored in ico_used_nonces scoped to the sale.
     */
    public function createSaleSignature(
        IcoSale $sale,
        IcoPaymentMethod $paymentMethod,
        string $walletAddress,
        string $tokenAmount,
        string $paymentAmount,
    ): array {
        $signerKey = config('crypto.ico.signer_key');

        if (! $signerKey) {
            throw new ICOException('ICO_SIGNER_KEY not configured.');
        }

        $nonce     = (string) Str::uuid();
        $expiresAt = now()->addSeconds(120);

        DB::transaction(function () use ($nonce, $walletAddress, $sale, $expiresAt): void {
            IcoUsedNonce::create([
                'sale_id'      => $sale->id,
                'nonce'        => $nonce,
                'user_address' => strtolower($walletAddress),
                'user_id'      => auth()->id(),
                'expires_at'   => $expiresAt,
                'used_at'      => now(),
            ]);
        });

        $sig = $this->nodeService->createSign(
            $sale->chain_type,
            $paymentMethod->payment_index,
            $walletAddress,
            $sale->contract_address,
            $paymentAmount,
        );

        return [
            'v'          => $sig['v'],
            'r'          => '0x' . ltrim($sig['r'], '0x'),
            's'          => '0x' . ltrim($sig['s'], '0x'),
            'nonce'      => $nonce,
            'expires_at' => $expiresAt->timestamp,
        ];
    }

    public function isNonceConsumed(string $nonce, string $saleId): bool
    {
        return IcoUsedNonce::where('nonce', $nonce)
            ->where('sale_id', $saleId)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
