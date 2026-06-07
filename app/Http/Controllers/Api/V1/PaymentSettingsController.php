<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = AppSetting::instance();

        return api_response(true, 'Payment settings retrieved.', [
            'wallet' => [
                'address'   => $setting->payment_admin_wallet_address,
                'connected' => (bool) $setting->payment_admin_wallet_connected,
            ],
        ]);
    }

    public function saveWallet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => 'required|string|regex:/^0x[0-9a-fA-F]{40}$/',
        ]);

        $setting = AppSetting::instance();
        $setting->forceFill([
            'payment_admin_wallet_address'   => strtolower($data['address']),
            'payment_admin_wallet_connected' => true,
        ])->save();

        return api_response(true, 'Payment wallet saved.', [
            'wallet' => [
                'address'   => $setting->payment_admin_wallet_address,
                'connected' => (bool) $setting->payment_admin_wallet_connected,
            ],
        ]);
    }

    public function disconnectWallet(): JsonResponse
    {
        $setting = AppSetting::instance();
        $setting->forceFill([
            'payment_admin_wallet_address'   => null,
            'payment_admin_wallet_connected' => false,
        ])->save();

        return api_response(true, 'Payment wallet disconnected.', [
            'wallet' => [
                'address'   => null,
                'connected' => false,
            ],
        ]);
    }
}
