<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ICO\ConfirmPurchaseRequest;
use App\Http\Requests\Api\V1\ICO\PrepareSalePurchaseRequest;
use App\Jobs\ConfirmIcoPurchaseJob;
use App\Services\ICO\PurchaseOrchestratorService;
use App\Services\ICO\SaleQueryService;
use App\Services\IcoPurchaseService;
use Illuminate\Http\JsonResponse;

class IcoSaleController extends Controller
{
    public function __construct(
        private readonly SaleQueryService           $saleQuery,
        private readonly PurchaseOrchestratorService $orchestrator,
        private readonly IcoPurchaseService          $purchaseService,
    ) {}

    /**
     * GET /ico/tokens
     * List all active ICO tokens with their active sale summary.
     */
    public function tokens(): JsonResponse
    {
        $tokens = $this->saleQuery->listActiveTokens();

        return api_response(true, 'Active ICO tokens retrieved.', [
            'tokens' => $tokens->map(fn ($token) => $this->formatToken($token)),
        ]);
    }

    /**
     * GET /ico/tokens/{token}/sale
     * Get the active sale for a token, including payment methods and allocation.
     */
    public function activeSale(string $tokenId): JsonResponse
    {
        $sale = $this->saleQuery->getActiveSaleForToken($tokenId);

        if (! $sale) {
            return api_response(false, 'No active sale for this token.', null, null, 404);
        }

        return api_response(true, 'Active sale retrieved.', ['sale' => $this->formatSale($sale)]);
    }

    /**
     * GET /ico/sales/{sale}
     * Get sale details.
     */
    public function show(string $saleId): JsonResponse
    {
        $sale = $this->saleQuery->getSaleWithDetails($saleId);

        return api_response(true, 'Sale retrieved.', ['sale' => $this->formatSale($sale)]);
    }

    /**
     * POST /ico/sales/{sale}/prepare
     * Validate allocation, generate backend signature, return unsigned tx params.
     */
    public function preparePurchase(PrepareSalePurchaseRequest $request, string $saleId): JsonResponse
    {
        $sale = $this->saleQuery->getSaleWithDetails($saleId);
        $v    = $request->validated();

        $result = $this->orchestrator->preparePurchase(
            $sale,
            $v['payment_method_id'],
            $v['wallet_address'],
            (string) $v['token_amount'],
            $v['payment_amount'],
        );

        return api_response(true, 'Purchase prepared. Sign and broadcast via MetaMask.', [
            'tx_params' => $result['tx_params'],
            'signature' => $result['signature'],
        ]);
    }

    /**
     * GET /ico/purchases
     * Current user's full purchase history.
     */
    public function purchaseHistory(): JsonResponse
    {
        $purchases = $this->saleQuery->getUserPurchaseHistory(auth()->id());

        return api_response(true, 'Purchase history retrieved.', [
            'purchases'  => $purchases->map(fn ($p) => $this->formatPurchase($p)),
            'pagination' => [
                'total'        => $purchases->total(),
                'per_page'     => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page'    => $purchases->lastPage(),
            ],
        ]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatToken(\App\Models\IcoToken $token): array
    {
        $activeSale = $token->sales->first();

        return [
            'id'           => $token->id,
            'name'         => $token->name,
            'symbol'       => $token->symbol,
            'decimals'     => $token->decimals,
            'contract_address' => $token->contract_address,
            'chain'        => $token->chain_type->value,
            'logo_url'     => $token->logo_url,
            'description'  => $token->description,
            'active_sale'  => $activeSale ? $this->formatSaleSummary($activeSale) : null,
        ];
    }

    private function formatSale(\App\Models\IcoSale $sale): array
    {
        $remaining = $sale->remainingAllocation();

        return [
            'id'               => $sale->id,
            'sale_type'        => $sale->sale_type->value,
            'sale_type_label'  => $sale->sale_type->label(),
            'status'           => $sale->status->value,
            'chain'            => $sale->chain_type->value,
            'token_price_usd'  => $sale->token_price_usd,
            'total_allocation' => $sale->total_allocation,
            'sold_amount'      => $sale->sold_amount,
            'remaining'        => $remaining,
            'progress_pct'     => bccomp((string) $sale->total_allocation, '0', 18) > 0
                ? round((float) bcdiv((string) $sale->sold_amount, (string) $sale->total_allocation, 6) * 100, 2)
                : 0,
            'min_purchase_usd' => $sale->min_purchase_usd,
            'max_purchase_usd' => $sale->max_purchase_usd,
            'starts_at'        => $sale->starts_at?->toIso8601String(),
            'ends_at'          => $sale->ends_at?->toIso8601String(),
            'token'            => $sale->token ? [
                'id'     => $sale->token->id,
                'name'   => $sale->token->name,
                'symbol' => $sale->token->symbol,
            ] : null,
            'payment_methods' => $sale->paymentMethods
                ->where('is_active', true)
                ->map(fn ($pm) => [
                    'id'            => $pm->id,
                    'symbol'        => $pm->symbol,
                    'payment_index' => $pm->payment_index,
                    'is_native'     => $pm->isNative(),
                ])->values(),
        ];
    }

    private function formatSaleSummary(\App\Models\IcoSale $sale): array
    {
        return [
            'id'              => $sale->id,
            'sale_type'       => $sale->sale_type->value,
            'status'          => $sale->status->value,
            'token_price_usd' => $sale->token_price_usd,
            'sold_amount'     => $sale->sold_amount,
            'total_allocation'=> $sale->total_allocation,
            'ends_at'         => $sale->ends_at?->toIso8601String(),
        ];
    }

    private function formatPurchase(\App\Models\IcoPurchase $purchase): array
    {
        return [
            'id'           => $purchase->id,
            'tx_hash'      => $purchase->tx_hash,
            'status'       => $purchase->status->value,
            'token_amount' => $purchase->token_amount,
            'chain'        => $purchase->chain_type->value,
            'confirmed_at' => $purchase->confirmed_at?->toIso8601String(),
            'created_at'   => $purchase->created_at?->toIso8601String(),
            'sale'         => $purchase->sale ? [
                'id'        => $purchase->sale->id,
                'sale_type' => $purchase->sale->sale_type->value,
                'token'     => $purchase->sale->token ? [
                    'symbol' => $purchase->sale->token->symbol,
                ] : null,
            ] : null,
        ];
    }
}
