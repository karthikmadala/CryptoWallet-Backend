<?php

namespace App\Listeners\Auth;

use App\Models\AdminAuditLog;
use Illuminate\Support\Str;

class LogAuthEvent
{
    public function handle(object $event): void
    {
        $user   = property_exists($event, 'user') ? $event->user : null;
        $ip     = property_exists($event, 'ip') ? $event->ip : null;
        $detail = property_exists($event, 'purpose') ? ['purpose' => $event->purpose?->value] : null;

        AdminAuditLog::create([
            'actor_id'    => $user?->id,
            'entity_type' => 'user',
            'entity_id'   => $user?->id,
            'action'      => 'auth.' . Str::snake(class_basename($event)),
            'ip_address'  => $ip,
            'new_values'  => $detail,
            'created_at'  => now(),
        ]);
    }
}
