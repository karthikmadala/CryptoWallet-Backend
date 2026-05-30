<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'              => 'System Admin',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'role_id'           => $superAdminRole?->id,
                'account_status'    => 'active',
                'email_verified_at' => now(),
                'auth_provider'     => 'local',
            ]
        );

        // Fix existing admin record that was created before role_id existed
        if (! $admin->wasRecentlyCreated && $superAdminRole && $admin->role_id !== $superAdminRole->id) {
            $admin->update(['role_id' => $superAdminRole->id]);
            $admin->clearPermissionCache();
        }

        User::query()->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'              => 'Demo User',
                'password'          => Hash::make('password'),
                'role'              => 'user',
                'account_status'    => 'active',
                'email_verified_at' => now(),
                'auth_provider'     => 'local',
            ]
        );
    }
}
