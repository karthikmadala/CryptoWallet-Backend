<?php

namespace App\Events;

use App\Models\IcoPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IcoPurchaseStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly IcoPurchase $purchase,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.User.{$this->purchase->user_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'id'            => $this->purchase->id,
            'tx_hash'       => $this->purchase->tx_hash,
            'status'        => $this->purchase->status->value,
            'token_amount'  => (string) $this->purchase->token_amount,
            'chain'         => $this->purchase->chain_type->value,
            'confirmed_at'  => $this->purchase->confirmed_at?->toISOString(),
            'error_message' => $this->purchase->error_message,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ico.purchase.status';
    }
}
