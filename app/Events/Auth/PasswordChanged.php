<?php

namespace App\Events\Auth;

use App\Models\User;

class PasswordChanged
{
    public function __construct(public readonly User $user, public readonly string $ip) {}
}
