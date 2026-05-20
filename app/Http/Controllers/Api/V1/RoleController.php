<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /** GET /api/v1/admin/roles */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(fn (Role $r) => $this->roleResponse($r));
        return api_response(true, 'Roles retrieved.', ['roles' => $roles]);
    }

    /** GET /api/v1/admin/permissions */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->map(fn (Permission $p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'label'       => $p->label,
            'description' => $p->description,
        ]);
        return api_response(true, 'Permissions retrieved.', ['permissions' => $permissions]);
    }

    /** POST /api/v1/admin/roles */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:50|unique:roles,name|alpha_dash',
            'label'         => 'required|string|max:100',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name'           => $validated['name'],
            'label'          => $validated['label'],
            'is_super_admin' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $permIds = Permission::whereIn('name', $validated['permissions'])->pluck('id');
            $role->permissions()->sync($permIds);
        }

        $role->load('permissions');

        return api_response(true, 'Role created.', ['role' => $this->roleResponse($role)], null, 201);
    }

    /** PUT /api/v1/admin/roles/{role} */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_super_admin) {
            return api_response(false, 'Cannot modify the super_admin role.', [], null, 403);
        }

        $validated = $request->validate([
            'label'         => 'sometimes|required|string|max:100',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['label'])) {
            $role->update(['label' => $validated['label']]);
        }

        if (array_key_exists('permissions', $validated)) {
            $permIds = $validated['permissions']
                ? Permission::whereIn('name', $validated['permissions'])->pluck('id')
                : collect();
            $role->permissions()->sync($permIds);
        }

        $role->load('permissions');

        // Bust permission cache for every user assigned to this role
        User::where('role_id', $role->id)->each(fn (User $u) => $u->clearPermissionCache());

        return api_response(true, 'Role updated.', ['role' => $this->roleResponse($role)]);
    }

    /** DELETE /api/v1/admin/roles/{role} */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_super_admin) {
            return api_response(false, 'Cannot delete the super_admin role.', [], null, 403);
        }

        $userCount = User::where('role_id', $role->id)->count();
        if ($userCount > 0) {
            return api_response(false, "Cannot delete role with {$userCount} assigned user(s). Reassign them first.", [], null, 422);
        }

        $role->delete();

        return api_response(true, 'Role deleted.');
    }

    /** PATCH /api/v1/admin/users/{userId}/role — assign a role to a user */
    public function assignRole(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $user->update(['role_id' => $validated['role_id']]);
        $user->clearPermissionCache();

        return api_response(true, 'Role assigned.', [
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /** PATCH /api/v1/admin/users/{userId}/status */
    public function updateStatus(Request $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'account_status' => 'required|string|in:active,suspended,pending_verification,banned',
        ]);

        $user->update(['account_status' => $validated['account_status']]);

        return api_response(true, 'Account status updated.', [
            'user' => new UserResource($user->fresh()),
        ]);
    }

    private function roleResponse(Role $role): array
    {
        return [
            'id'             => $role->id,
            'name'           => $role->name,
            'label'          => $role->label,
            'is_super_admin' => (bool) $role->is_super_admin,
            'permissions'    => $role->permissions->pluck('name')->values()->all(),
            'users_count'    => User::where('role_id', $role->id)->count(),
        ];
    }
}
