<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $roles): mixed
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return api_response(false, 'Unauthenticated.', null, [
                'authorization' => ['Authentication required.'],
            ], 401);
        }

        // Super admin bypass (uses isSuperAdmin which loads role relationship correctly)
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Parse roles: comma-separated = ANY match
        $roleList = array_map('trim', explode(',', $roles));

        $roleModel = $user->role_id ? $user->role()->first() : null;

        $hasRole = false;
        foreach ($roleList as $role) {
            if ($roleModel && $roleModel->name === $role) {
                $hasRole = true;
                break;
            }
            // Legacy string role field check
            if ($user->role === $role) {
                $hasRole = true;
                break;
            }
        }

        if (! $hasRole) {
            return api_response(false, 'Forbidden.', null, [
                'authorization' => ["Required role: {$roles}"],
            ], 403);
        }

        return $next($request);
    }
}