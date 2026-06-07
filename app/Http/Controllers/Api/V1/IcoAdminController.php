<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IcoTokenResource;
use App\Jobs\ProcessLogoUploadJob;
use App\Models\IcoPurchase;
use App\Models\IcoSale;
use App\Models\IcoToken;
use App\Services\AuditLogService;
use App\Services\BrandingService;
use App\Services\FileUploadService;
use App\Services\ICO\SaleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IcoAdminController extends Controller
{
    public function __construct(
        private readonly SaleManagementService $management,
        private readonly AuditLogService       $audit,
        private readonly FileUploadService     $files,
        private readonly BrandingService       $branding,
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
            'logo' => [
                'nullable', 'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'whitepaper' => [
                'nullable', 'file',
                'mimetypes:application/pdf,application/msword,' .
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document,' .
                    'image/jpeg,image/png,image/webp',
                'max:10240',
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        $token = $this->management->createToken($data, $request);
        $this->audit->log('ico_token', $token->id, 'created', [], $token->toArray());

        $postSave = $this->handlePostSave($token);

        return api_response(true, 'Token created.', array_merge(
            ['token' => new IcoTokenResource($token->load('sales'))],
            $postSave,
        ), null, 201);
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
            'logo' => [
                'nullable', 'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'whitepaper' => [
                'nullable', 'file',
                'mimetypes:application/pdf,application/msword,' .
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document,' .
                    'image/jpeg,image/png,image/webp',
                'max:10240',
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        $before = $icoToken->toArray();
        $token  = $this->management->updateToken($icoToken, $data, $request);
        $this->audit->log('ico_token', $token->id, 'updated', $before, $token->toArray());

        $postSave = $this->handlePostSave($token);

        return api_response(true, 'Token updated.', array_merge(
            ['token' => new IcoTokenResource($token->load('sales'))],
            $postSave,
        ));
    }

    public function deleteToken(string $icoToken): JsonResponse
    {
        $token = IcoToken::withTrashed()->find($icoToken);
        if (!$token) {
            return api_response(false, 'ICO token not found.', null, null, 404);
        }

        if ($token->trashed()) {
            return api_response(true, 'Token already deleted.');
        }

        if ($token->sales()->where('status', 'active')->exists()) {
            return api_response(false, 'Cannot delete token with an active sale.', null, null, 422);
        }

        $this->audit->log('ico_token', $token->id, 'deleted', $token->toArray(), []);
        $token->delete();

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

    public function createSale(Request $request, string $icoToken): JsonResponse
    {
        $token = IcoToken::withTrashed()->find($icoToken);
        if (!$token || $token->trashed()) {
            return api_response(false, 'ICO token not found or deleted. Choose an active token.', null, null, 404);
        }

        $data = $request->validate([
            'sale_type'              => 'required|string|in:pre_sale,public_sale,private_sale',
            'contract_address'       => 'required|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'             => 'required|string|in:eth,bnb,polygon',
            'owner_address'          => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'signer_address'         => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'signer_private_key'     => 'nullable|string|regex:/^(0x)?[0-9a-fA-F]{64}$/',
            'token_price_usd'        => 'required|numeric|gt:0',
            'total_allocation'       => 'required|numeric|gt:0',
            'min_purchase_usd'       => 'nullable|numeric|gt:0',
            'max_purchase_usd'       => 'nullable|numeric|gt:0',
            'starts_at'              => 'nullable|date',
            'ends_at'                => 'nullable|date|after:starts_at',
            'payment_methods'        => 'nullable|array|max:20',
            'payment_methods.*.payment_index'    => 'required|integer|min:0',
            'payment_methods.*.symbol'           => 'required|string|max:20',
            'payment_methods.*.name'             => 'nullable|string|max:100',
            'payment_methods.*.contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'payment_methods.*.price_feed'       => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'payment_methods.*.decimals'         => 'nullable|integer|min:0|max:36',
            'payment_methods.*.is_active'        => 'nullable|boolean',
        ]);

        $sale = $this->management->createSale($token, $data);
        $this->audit->log('ico_sale', $sale->id, 'created', [], $sale->toArray());

        return api_response(true, 'Sale created.', ['sale' => $this->formatSaleAdmin($sale)], null, 201);
    }

    public function updateSale(Request $request, IcoSale $icoSale): JsonResponse
    {
        $data = $request->validate([
            'sale_type'          => 'sometimes|string|in:pre_sale,public_sale,private_sale',
            'contract_address'   => 'sometimes|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'owner_address'      => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'signer_address'     => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'signer_private_key' => 'nullable|string|regex:/^(0x)?[0-9a-fA-F]{64}$/',
            'token_price_usd'    => 'sometimes|numeric|gt:0',
            'total_allocation'   => 'sometimes|numeric|gt:0',
            'min_purchase_usd'   => 'nullable|numeric|gt:0',
            'max_purchase_usd'   => 'nullable|numeric|gt:0',
            'starts_at'          => 'nullable|date',
            'ends_at'            => 'nullable|date',
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
            'name'             => 'nullable|string|max:100',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'chain_type'       => 'required|string|in:eth,bnb,polygon',
            'decimals'         => 'nullable|integer|min:0|max:36',
            'price_usd'        => 'nullable|numeric|gte:0',
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
            'name'             => 'nullable|string|max:100',
            'contract_address' => 'nullable|string|regex:/^0x[0-9a-fA-F]{40}$/',
            'decimals'         => 'nullable|integer|min:0|max:36',
            'price_usd'        => 'nullable|numeric|gte:0',
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

    private function handlePostSave(IcoToken $token): array
    {
        $count = IcoToken::count();

        if ($count === 1 && $token->logo_path) {
            $this->branding->autoAssign($token);
            return ['branding_auto_assigned' => true, 'branding_modal_required' => false];
        }

        return [
            'branding_auto_assigned'  => false,
            'branding_modal_required' => $count > 1 && (bool) $token->logo_path,
        ];
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
            'logo_path'                => $token->logo_path ? asset('storage/' . $token->logo_path) : null,
            'whitepaper_url'           => $token->whitepaper_path ? asset('storage/' . $token->whitepaper_path) : null,
            'whitepaper_original_name' => $token->whitepaper_original_name,
            'whitepaper_mime_type'     => $token->whitepaper_mime_type,
            'whitepaper_size'          => $token->whitepaper_size,
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
            'owner_address'    => $sale->owner_address,
            'signer_address'   => $sale->signer_address,
            'has_signer_key'   => (bool) $sale->getRawOriginal('signer_private_key_enc'),
            'chain'            => $sale->chain_type->value,
            'token'            => $sale->token ? [
                'id'     => $sale->token->id,
                'name'   => $sale->token->name,
                'symbol' => $sale->token->symbol,
            ] : null,
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
            'name'             => $pm->name ?? $pm->symbol,
            'contract_address' => $pm->contract_address,
            'chain'            => $pm->chain_type->value,
            'decimals'         => $pm->decimals,
            'price_usd'        => $pm->price_usd,
            'is_active'        => $pm->is_active,
            'is_native'        => $pm->isNative(),
        ];
    }
}
