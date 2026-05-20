<?php

use App\Jobs\DispatchWalletBalanceSyncsJob;
use App\Jobs\FetchTokenPricesJob;
use App\Jobs\MonitorPendingTransactionsJob;
use App\Jobs\SyncIncomingTransactionsJob;
use App\Jobs\TrackPendingIcoPurchasesJob;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Deposit detection — scan all wallets for new incoming txs every 5 minutes.
Schedule::job(new SyncIncomingTransactionsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('sync-incoming-transactions');

// Confirmation tracking — poll submitted transactions every 2 minutes.
Schedule::job(new MonitorPendingTransactionsJob)
    ->everyTwoMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->name('monitor-pending-transactions');

// Balance sync fan-out — dispatches one UpdateWalletBalancesJob per active wallet every 5 minutes.
Schedule::job(new DispatchWalletBalanceSyncsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('dispatch-wallet-balance-syncs');

// Price refresh — fetch native token prices from explorer every 5 minutes.
Schedule::job(new FetchTokenPricesJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->name('fetch-token-prices');

Schedule::call(function () {
    \App\Models\IcoUsedNonce::pruneExpired();
})->hourly()->name('ico:prune-nonces')->withoutOverlapping();

// ICO purchase confirmation recovery — re-dispatch stuck purchases every 5 minutes.
Schedule::job(new TrackPendingIcoPurchasesJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->name('track-pending-ico-purchases');

Artisan::command('transactions:cleanup-recipient-mirrors', function () {
    $deleted = 0;

    Transaction::query()
        ->with('wallet')
        ->whereNull('tx_hash')
        ->where('signing_method', 'client')
        ->where('status', 'submitted')
        ->orderBy('created_at')
        ->chunk(200, function ($transactions) use (&$deleted): void {
            foreach ($transactions as $transaction) {
                $wallet = $transaction->wallet;
                if (! $wallet) {
                    continue;
                }

                if (strtolower((string) $transaction->to_address) !== strtolower((string) $wallet->address)) {
                    continue;
                }

                $canonicalExists = Transaction::query()
                    ->whereNotNull('tx_hash')
                    ->where('chain_type', $transaction->chain_type?->value ?? $transaction->chain_type)
                    ->where('from_address', strtolower((string) $transaction->from_address))
                    ->where('to_address', strtolower((string) $transaction->to_address))
                    ->where('amount', (string) $transaction->amount)
                    ->exists();

                if (! $canonicalExists) {
                    continue;
                }

                $transaction->delete();
                $deleted++;
            }
        });

    $this->info("deleted={$deleted}");
})->purpose('Delete mirrored recipient-side transaction records created by the old internal transfer approach');
