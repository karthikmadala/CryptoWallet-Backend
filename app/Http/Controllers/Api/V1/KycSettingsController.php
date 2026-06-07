<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = AppSetting::instance();

        return api_response(true, 'KYC settings retrieved.', [
            'kyc_required' => (bool) $settings->kyc_required,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kyc_required' => 'required|boolean',
        ]);

        $settings = AppSetting::instance();
        $settings->update(['kyc_required' => $validated['kyc_required']]);

        return api_response(true, 'KYC enforcement ' . ($validated['kyc_required'] ? 'enabled' : 'disabled') . '.', [
            'kyc_required' => (bool) $settings->kyc_required,
        ]);
    }
}
