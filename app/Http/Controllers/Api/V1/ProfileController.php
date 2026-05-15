<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Wallet;
use App\Services\AuthService;
use App\Enums\ChainType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function show(): JsonResponse
    {
        return api_response(true, 'Profile fetched successfully.', [
            'user' => new UserResource(request()->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return api_response(true, 'Profile updated successfully.', [
            'user' => new UserResource($user->refresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated()['current_password'],
            $request->validated()['password']
        );

        return api_response(true, 'Password changed successfully.');
    }

    public function internalRecipients(Request $request): JsonResponse
    {
        $chain = ChainType::tryFrom((string) $request->query('chain_type'));

        if (! $chain) {
            return api_response(false, 'Invalid chain type.', [], null, 400);
        }

        $search = trim((string) $request->query('search', ''));

        $wallets = Wallet::query()
            ->with(['user:id,name,email'])
            ->active()
            ->internal()
            ->where('user_id', '!=', $request->user()->id)
            ->whereHas('user', function ($query) use ($search): void {
                if ($search === '') {
                    return;
                }

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when(
                $chain->isEvm(),
                fn ($query) => $query->whereIn('chain_type', [
                    ChainType::ETH->value,
                    ChainType::BNB->value,
                    ChainType::POLYGON->value,
                ]),
                fn ($query) => $query->where('chain_type', $chain->value)
            )
            ->orderByDesc('created_at')
            ->limit($search === '' ? 12 : 20)
            ->get();

        $recipients = $wallets
            ->groupBy(fn (Wallet $wallet) => $wallet->user_id . ':' . strtolower($wallet->address))
            ->map(function ($userWallets) use ($chain) {
                $user = $userWallets->first()?->user;
                $preferredWallet = $userWallets->firstWhere('chain_type', $chain) ?? $userWallets->first();

                return [
                    'user' => [
                        'id' => $user?->id,
                        'name' => $user?->name,
                        'email' => $user?->email,
                    ],
                    'wallets' => [[
                        'id' => $preferredWallet?->id,
                        'address' => $preferredWallet?->address,
                        'label' => $preferredWallet?->label,
                        'chain_type' => $chain->value,
                    ]],
                ];
            })
            ->values();

        return api_response(true, 'Internal recipients fetched successfully.', [
            'recipients' => $recipients,
        ]);
    }
}
