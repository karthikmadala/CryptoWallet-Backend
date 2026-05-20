<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IcoPurchase;
use App\Models\IcoSale;
use App\Models\IcoToken;
use App\Services\AuditLogService;
use App\Services\ICO\SaleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IcoAdminController extends Controller
{
    public function __construct(
        private readonly SaleManagementService $management,
        private readonly AuditLogService        $audit,
    ) {}

    // ── Token CRUD ────────────────────────────────────────────────────────────

    public function listTokens(): JsonResponse
    {
        $tokens = IcoToken::withTrashed()->with('sales')->orderByDesc('created_at')->get();

        return api_response(true, 'Tokens retrieved.', [
            'tokens' => $tokens->map(fn ($t) => $this->formatToken($t)),
        ]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'symbol'           => 'required|string|max:20',
            'decimals'         => 'integer|min:0|max:18',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'       => 'required|string|in:eth,bnb,polygon',
            'total_supply'     => 'nullable|numeric|gt:0',
            'description'      => 'nullable|string|max:2000',
            'logo_url'         => 'nullable|url|max:500',
            'is_active'        => 'boolean',
        ]);

        $token = $this->management->createToken($data);
        $this->audit->log('ico_token', $token->id, 'created', [], $token->toArray());

        return api_response(true, 'Token created.', ['token' => $this->formatToken($token)], null, 201);
    }

    public function updateToken(Request $request, IcoToken $icoToken): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'symbol'           => 'sometimes|string|max:20',
            'decimals'         => 'sometimes|integer|min:0|max:18',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'       => 'sometimes|string|in:eth,bnb,polygon',
            'total_supply'     => 'nullable|numeric|gt:0',
            'description'      => 'nullable|string|max:2000',
            'logo_url'         => 'nullable|url|max:500',
            'is_active'        => 'sometimes|boolean',
        ]);

        $before = $icoToken->toArray();
        $token  = $this->management->updateToken($icoToken, $data);
        $this->audit->log('ico_token', $token->id, 'updated', $before, $token->toArray());

        return api_response(true, 'Token updated.', ['token' => $this->formatToken($token)]);
    }

    public function deleteToken(IcoToken $icoToken): JsonResponse
    {
        if ($icoToken->sales()->where('status', 'active')->exists()) {
            return api_response(false, 'Cannot delete token with an active sale.', null, null, 422);
        }

        $this->audit->log('ico_token', $icoToken->id, 'deleted', $icoToken->toArray(), []);
        $icoToken->delete();

        return api_response(true, 'Token deleted.');
    }

    // ── Sale CRUD ─────────────────────────────────────────────────────────────

    public function listSales(Request $request): JsonResponse
    {
        $query = IcoSale::with(['token', 'paymentMethods'])
            ->orderByDesc('created_at');

        if ($tokenId = $request->query('token_id')) {
            $query->where('ico_token_id', $tokenId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $sales = $query->paginate($request->integer('per_page', 20));

        return api_response(true, 'Sales retrieved.', [
            'sales' => $sales->map(fn ($s) => $this->formatSaleAdmin($s)),
            'pagination' => [
                'total'        => $sales->total(),
                'per_page'     => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
            ],
        ]);
    }

    public function createSale(Request $request, IcoToken $icoToken): JsonResponse
    {
        $data = $request->validate([
            'sale_type'        => 'required|string|in:pre_sale,public_sale,private_sale',
            'contract_address' => 'required|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'       => 'required|string|in:eth,bnb,polygon',
            'token_price_usd'  => 'required|numeric|gt:0',
            'total_allocation' => 'required|numeric|gt:0',
            'min_purchase_usd' => 'nullable|numeric|gt:0',
            'max_purchase_usd' => 'nullable|numeric|gt:0',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date|after:starts_at',
        ]);

        $sale = $this->management->createSale($icoToken, $data);
        $this->audit->log('ico_sale', $sale->id, 'created', [], $sale->toArray());

        return api_response(true, 'Sale created.', ['sale' => $this->formatSaleAdmin($sale)], null, 201);
    }

    public function updateSale(Request $request, IcoSale $icoSale): JsonResponse
    {
        $data = $request->validate([
            'sale_type'        => 'sometimes|string|in:pre_sale,public_sale,private_sale',
            'contract_address' => 'sometimes|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'token_price_usd'  => 'sometimes|numeric|gt:0',
            'total_allocation' => 'sometimes|numeric|gt:0',
            'min_purchase_usd' => 'nullable|numeric|gt:0',
            'max_purchase_usd' => 'nullable|numeric|gt:0',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
        ]);

        $before = $icoSale->toArray();
        $sale   = $this->management->updateSale($icoSale, $data);
        $this->audit->log('ico_sale', $sale->id, 'updated', $before, $sale->toArray());

        return api_response(true, 'Sale updated.', ['sale' => $this->formatSaleAdmin($sale)]);
    }

    public function activateSale(IcoSale $icoSale): JsonResponse
    {
        $sale = $this->management->activateSale($icoSale);
        $this->audit->log('ico_sale', $sale->id, 'activated', [], $sale->toArray());

        return api_response(true, 'Sale activated.', ['sale' => $this->formatSaleAdmin($sale)]);
    }

    public function pauseSale(IcoSale $icoSale): JsonResponse
    {
        $sale = $this->management->pauseSale($icoSale);
        $this->audit->log('ico_sale', $sale->id, 'paused', [], $sale->toArray());

        return api_response(true, 'Sale paused.', ['sale' => $this->formatSaleAdmin($sale)]);
    }

    public function endSale(IcoSale $icoSale): JsonResponse
    {
        $sale = $this->management->endSale($icoSale);
        $this->audit->log('ico_sale', $sale->id, 'ended', [], $sale->toArray());

        return api_response(true, 'Sale ended.', ['sale' => $this->formatSaleAdmin($sale)]);
    }

    // ── Payment method CRUD ───────────────────────────────────────────────────

    public function listPaymentMethods(IcoSale $icoSale): JsonResponse
    {
        return api_response(true, 'Payment methods retrieved.', [
            'payment_methods' => $icoSale->paymentMethods->map(fn ($pm) => $this->formatPaymentMethod($pm)),
        ]);
    }

    public function createPaymentMethod(Request $request, IcoSale $icoSale): JsonResponse
    {
        $data = $request->validate([
            'payment_index'    => 'required|integer|min:0',
            'symbol'           => 'required|string|max:20',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'       => 'required|string|in:eth,bnb,polygon',
            'is_active'        => 'boolean',
        ]);

        $method = $this->management->addPaymentMethod($icoSale, $data);
        $this->audit->log('ico_payment_method', $method->id, 'created', [], $method->toArray());

        return api_response(true, 'Payment method created.', [
            'payment_method' => $this->formatPaymentMethod($method),
        ], null, 201);
    }

    public function updatePaymentMethod(Request $request, IcoSale $icoSale, string $methodId): JsonResponse
    {
        $method = $icoSale->paymentMethods()->findOrFail($methodId);

        $data = $request->validate([
            'symbol'           => 'sometimes|string|max:20',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'is_active'        => 'sometimes|boolean',
        ]);

        $before = $method->toArray();
        $method = $this->management->updatePaymentMethod($method, $data);
        $this->audit->log('ico_payment_method', $method->id, 'updated', $before, $method->toArray());

        return api_response(true, 'Payment method updated.', [
            'payment_method' => $this->formatPaymentMethod($method),
        ]);
    }

    public function deletePaymentMethod(IcoSale $icoSale, string $methodId): JsonResponse
    {
        $method = $icoSale->paymentMethods()->findOrFail($methodId);
        $this->management->deletePaymentMethod($method);
        $this->audit->log('ico_payment_method', $method->id, 'deleted', $method->toArray(), []);

        return api_response(true, 'Payment method deleted.');
    }

    // ── Purchase monitoring ───────────────────────────────────────────────────

    public function purchases(Request $request): JsonResponse
    {
        $query = IcoPurchase::with(['user', 'sale.token'])
            ->orderByDesc('created_at');

        if ($saleId = $request->query('sale_id')) {
            $query->where('sale_id', $saleId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $purchases = $query->paginate($request->integer('per_page', 50));

        return api_response(true, 'Purchases retrieved.', [
            'purchases' => $purchases->map(fn ($p) => [
                'id'           => $p->id,
                'tx_hash'      => $p->tx_hash,
                'status'       => $p->status->value,
                'token_amount' => $p->token_amount,
                'chain'        => $p->chain_type->value,
                'from_address' => $p->from_address,
                'user_id'      => $p->user_id,
                'user_email'   => $p->user?->email,
                'sale_id'      => $p->sale_id,
                'token_symbol' => $p->sale?->token?->symbol,
                'confirmed_at' => $p->confirmed_at?->toIso8601String(),
                'failed_at'    => $p->failed_at?->toIso8601String(),
                'created_at'   => $p->created_at?->toIso8601String(),
                'attempts'     => $p->confirmation_attempts,
                'error'        => $p->error_message,
            ]),
            'pagination' => [
                'total'        => $purchases->total(),
                'per_page'     => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page'    => $purchases->lastPage(),
            ],
        ]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatToken(IcoToken $token): array
    {
        return [
            'id'               => $token->id,
            'name'             => $token->name,
            'symbol'           => $token->symbol,
            'decimals'         => $token->decimals,
            'contract_address' => $token->contract_address,
            'chain'            => $token->chain_type->value,
            'total_supply'     => $token->total_supply,
            'description'      => $token->description,
            'logo_url'         => $token->logo_url,
            'is_active'        => $token->is_active,
            'sales_count'      => $token->sales->count(),
            'created_at'       => $token->created_at?->toIso8601String(),
            'deleted_at'       => $token->deleted_at?->toIso8601String(),
        ];
    }

    private function formatSaleAdmin(IcoSale $sale): array
    {
        return [
            'id'               => $sale->id,
            'ico_token_id'     => $sale->ico_token_id,
            'sale_type'        => $sale->sale_type->value,
            'status'           => $sale->status->value,
            'contract_address' => $sale->contract_address,
            'chain'            => $sale->chain_type->value,
            'token_price_usd'  => $sale->token_price_usd,
            'total_allocation' => $sale->total_allocation,
            'sold_amount'      => $sale->sold_amount,
            'remaining'        => $sale->remainingAllocation(),
            'min_purchase_usd' => $sale->min_purchase_usd,
            'max_purchase_usd' => $sale->max_purchase_usd,
            'starts_at'        => $sale->starts_at?->toIso8601String(),
            'ends_at'          => $sale->ends_at?->toIso8601String(),
            'created_at'       => $sale->created_at?->toIso8601String(),
            'payment_methods'  => $sale->paymentMethods->map(fn ($pm) => $this->formatPaymentMethod($pm))->values(),
        ];
    }

    private function formatPaymentMethod(\App\Models\IcoPaymentMethod $pm): array
    {
        return [
            'id'               => $pm->id,
            'payment_index'    => $pm->payment_index,
            'symbol'           => $pm->symbol,
            'contract_address' => $pm->contract_address,
            'chain'            => $pm->chain_type->value,
            'is_active'        => $pm->is_active,
            'is_native'        => $pm->isNative(),
        ];
    }
}
