<?php

namespace App\Http\Middleware;

use App\Enums\Auth\AccountStatus;
use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $settings = AppSetting::instance();

        if (! $settings->kyc_required) {
            return $next($request);
        }

        if ($user->account_status === AccountStatus::Active) {
            return $next($request);
        }

        if (
            $user->account_status === AccountStatus::Suspended ||
            $user->account_status === AccountStatus::Locked
        ) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'KYC verification required. Please complete identity verification to access this feature.',
            'data'    => null,
            'errors'  => ['code' => 'kyc_required'],
        ], 403);
    }
}
