<?php

namespace App\Http\Middleware;

use App\Enums\Auth\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before accessing this feature.',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        if ($user->account_status !== AccountStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Contact support.',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}
