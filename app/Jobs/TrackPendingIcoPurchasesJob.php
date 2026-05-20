<?php

namespace App\Jobs;

use App\Enums\IcoPurchaseStatus;
use App\Models\IcoPurchase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackPendingIcoPurchasesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $stale = IcoPurchase::where('status', IcoPurchaseStatus::SUBMITTED->value)
            ->where('updated_at', '<', now()->subMinutes(3))
            ->where('confirmation_attempts', '<', 20)
            ->get();

        foreach ($stale as $purchase) {
            $lockKey = "ico_confirm_dispatched:{$purchase->id}";

            // 60-second lock prevents duplicate dispatch when scheduler overlaps
            if (Cache::add($lockKey, true, 60)) {
                ConfirmIcoPurchaseJob::dispatch($purchase->id);

                Log::info('TrackPendingIcoPurchasesJob: re-dispatched confirmation', [
                    'id'      => $purchase->id,
                    'tx_hash' => $purchase->tx_hash,
                ]);
            }
        }

        Log::info('TrackPendingIcoPurchasesJob: completed', ['count' => $stale->count()]);
    }
}
