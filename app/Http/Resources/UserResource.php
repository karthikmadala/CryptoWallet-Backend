<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleModel = $this->role_id ? $this->role()->with('permissions')->first() : null;

        $data = [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'role'               => $this->role,
            'role_id'            => $this->role_id,
            'role_label'         => $roleModel?->label,
            'is_super_admin'     => (bool) $roleModel?->is_super_admin,
            'permissions'        => $roleModel ? $roleModel->permissions->pluck('name')->values()->all() : [],
            'roles'              => $roleModel ? [$roleModel->name] : ($this->role ? [$this->role] : []),
            'account_status'     => $this->account_status instanceof \App\Enums\Auth\AccountStatus
                ? $this->account_status->value
                : $this->account_status,
            'menu_restrictions'  => $this->menu_restrictions ?? [],
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
            'auth_provider'      => $this->auth_provider ?? 'local',
            'is_online'          => (bool) ($this->is_online ?? false),
            'last_login_at'      => $this->last_login_at?->toISOString(),
            'last_logout_at'     => $this->last_logout_at?->toISOString(),
            'user_agent'         => $this->when(
                $request->user()?->role === 'admin',
                $this->user_agent
            ),
        ];

        if (isset($this->wallets_count)) {
            $data['wallet_count'] = $this->wallets_count;
        }

        return $data;
    }
}
