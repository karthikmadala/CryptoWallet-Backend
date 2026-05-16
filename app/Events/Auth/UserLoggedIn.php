<?php

namespace App\Events\Auth;

use App\Models\User;

class UserLoggedIn
{
    public function __construct(public readonly User $user, public readonly string $ip) {}
}
