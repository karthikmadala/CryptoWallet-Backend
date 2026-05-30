<?php

namespace App\Jobs;

use App\Enums\IcoPurchaseStatus;
use App\Events\IcoPurchaseStatusUpdated;
use App\Exceptions\BlockchainException;
use App\Models\IcoPurchase;
use App\Services\Crypto\BlockchainNodeService;
use App\Services\ICO\AllocationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ConfirmIcoPurchaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 20;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(
        public readonly string $purchaseId,
    ) {
        $this->onQueue('critical');
    }

    public function handle(BlockchainNodeService $nodeService, AllocationService $allocationService): void
    {
        $purchase = IcoPurchase::find($this->purchaseId);

        if (! $purchase) {
            Log::warning('ConfirmIcoPurchaseJob: purchase not found', ['id' => $this->purchaseId]);
            return;
        }

        if ($purchase->status->isTerminal()) {
            Log::info('ConfirmIcoPurchaseJob: already terminal', [
                'id'     => $this->purchaseId,
                'status' => $purchase->status->value,
            ]);
            return;
        }

        try {
            $receipt = $nodeService->getReceipt($purchase->chain_type, $purchase->tx_hash);

            if ($receipt === null) {
                $purchase->increment('confirmation_attempts');
                Log::debug('ConfirmIcoPurchaseJob: receipt pending', [
                    'id'       => $this->purchaseId,
                    'attempts' => $purchase->confirmation_attempts,
                ]);
                $this->release(30);
                return;
            }

            $onChainStatus = $receipt['status'] ?? null;

            if ($onChainStatus === '0x1') {
                $purchase->update([
                    'status'       => IcoPurchaseStatus::CONFIRMED,
                    'confirmed_at' => now(),
                ]);

                $allocationService->finalizeAllocation($purchase->fresh());

                Log::info('ConfirmIcoPurchaseJob: confirmed', [
                    'id'      => $this->purchaseId,
                    'tx_hash' => $purchase->tx_hash,
                ]);
            } else {
                $purchase->update([
                    'status'        => IcoPurchaseStatus::FAILED,
                    'failed_at'     => now(),
                    'error_message' => 'Transaction reverted on-chain',
                ]);

                Log::warning('ConfirmIcoPurchaseJob: reverted on-chain', [
                    'id'      => $this->purchaseId,
                    'tx_hash' => $purchase->tx_hash,
                ]);
            }

            IcoPurchaseStatusUpdated::dispatch($purchase->fresh());
        } catch (BlockchainException $e) {
            $purchase->increment('confirmation_attempts');

            Log::error('ConfirmIcoPurchaseJob: blockchain error', [
                'id'    => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            if ($purchase->confirmation_attempts < $this->tries) {
                $this->release($this->backoff);
            } else {
                $purchase->update([
                    'status'        => IcoPurchaseStatus::FAILED,
                    'failed_at'     => now(),
                    'error_message' => 'Blockchain node error: ' . $e->getMessage(),
                ]);

                IcoPurchaseStatusUpdated::dispatch($purchase->fresh());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $purchase = IcoPurchase::find($this->purchaseId);

        if ($purchase && ! $purchase->status->isTerminal()) {
            $purchase->update([
                'status'        => IcoPurchaseStatus::FAILED,
                'failed_at'     => now(),
                'error_message' => 'Max confirmation attempts reached',
            ]);

            IcoPurchaseStatusUpdated::dispatch($purchase->fresh());
        }

        Log::error('ConfirmIcoPurchaseJob: job failed permanently', [
            'id'    => $this->purchaseId,
            'error' => $exception->getMessage(),
        ]);
    }
}
