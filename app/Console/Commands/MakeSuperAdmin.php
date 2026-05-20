<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature   = 'user:make-super-admin {email}';
    protected $description = 'Assign the super_admin role to a user by email';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $role = Role::where('name', 'super_admin')->first();
        if (! $role) {
            $this->error('super_admin role not found. Run: php artisan db:seed --class=PermissionSeeder');
            return self::FAILURE;
        }

        $user->update(['role_id' => $role->id, 'role' => 'admin']);
        $user->clearPermissionCache();

        $this->info("✓ {$email} is now super_admin (role_id={$role->id}).");
        return self::SUCCESS;
    }
}
