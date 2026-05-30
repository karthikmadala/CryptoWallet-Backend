<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\IcoPurchaseStatus;
use App\Models\IcoPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IcoPurchaseService
{
    /**
     * Record a submitted ICO purchase and prepare it for confirmation tracking.
     * Idempotent — returns existing record if the tx_hash + chain is already recorded.
     *
     * The caller (controller) dispatches ConfirmIcoPurchaseJob AFTER this returns,
     * outside the DB transaction, to avoid job executing before commit.
     */
    public function submitForConfirmation(User $user, array $data): IcoPurchase
    {
        $chain   = ChainType::from($data['chain']);
        $txHash  = strtolower($data['tx_hash']);
        $address = strtolower($data['from_address']);

        if (! preg_match($chain->addressPattern(), $address)) {
            throw ValidationException::withMessages([
                'from_address' => ["Invalid {$chain->label()} address format."],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $chain, $txHash, $address): IcoPurchase {
            $existing = IcoPurchase::where('chain_type', $chain->value)
                ->where('tx_hash', $txHash)
                ->first();

            if ($existing) {
                Log::info('IcoPurchaseService: duplicate confirm request', [
                    'id'      => $existing->id,
                    'tx_hash' => $txHash,
                    'status'  => $existing->status->value,
                ]);
                return $existing;
            }

            $purchase = IcoPurchase::create([
                'user_id'       => $user->id,
                'tx_hash'       => $txHash,
                'chain_type'    => $chain->value,
                'from_address'  => $address,
                'token_amount'  => $data['token_amount'],
                'eth_value'     => $data['eth_value'],
                'payment_index' => (int) $data['payment_index'],
                'status'        => IcoPurchaseStatus::SUBMITTED->value,
            ]);

            Log::info('IcoPurchaseService: purchase submitted for confirmation', [
                'id'      => $purchase->id,
                'tx_hash' => $txHash,
                'user_id' => $user->id,
                'chain'   => $chain->value,
            ]);

            return $purchase;
        });
    }
}
