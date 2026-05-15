<?php

namespace App\Jobs;

use App\Enums\ChainType;
use App\Models\Token;
use App\Services\Crypto\BlockchainNodeService;
use App\Services\Crypto\ExplorerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchTokenPricesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 600;

    public function __construct(private readonly ?array $coingeckoIds = null)
    {
        $this->onQueue('default');
    }

    public function handle(BlockchainNodeService $node): void
    {
        $nativeChains = [ChainType::ETH, ChainType::BNB, ChainType::POLYGON];
        $updated = 0;

        foreach ($nativeChains as $chain) {
            try {
                // Fetch native price (ETH/BNB/MATIC) via node_api_base
                $price = $node->getNativePrice($chain);

                if ($price === null) {
                    continue;
                }

                $token = Token::where('chain_type', $chain->value)
                    ->whereNull('contract_address')
                    ->first();

                if (! $token) {
                    continue;
                }

                $token->update([
                    'current_price_usd' => $price,
                    'price_updated_at' => now(),
                ]);
                $updated++;
            } catch (\Throwable $e) {
                Log::warning('FetchTokenPricesJob: node price refresh failed', [
                    'chain' => $chain->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also fetch prices for tokens with coingecko_id
        $erc20Tokens = Token::whereNotNull('coingecko_id')
            ->whereNotNull('contract_address')
            ->where('enabled', true)
            ->get();

        if ($erc20Tokens->isNotEmpty()) {
            try {
                $ids = $erc20Tokens->pluck('coingecko_id')->unique()->all();
                $prices = $node->getPrices($ids);

                /** @var Token $token */
                foreach ($erc20Tokens as $token) {
                    if (isset($prices[$token->coingecko_id])) {
                        $token->update([
                            'current_price_usd' => $prices[$token->coingecko_id],
                            'price_updated_at' => now(),
                        ]);
                        $updated++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('FetchTokenPricesJob: ERC-20 price refresh failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('FetchTokenPricesJob: token prices updated via node_api_base', [
            'updated_tokens' => $updated,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchTokenPricesJob: job failed after all retries', [
            'error' => $e->getMessage(),
        ]);
    }

    public function uniqueId(): string
    {
        $ids = $this->coingeckoIds ?? [];
        sort($ids);

        return 'fetch-token-prices:' . md5(implode(',', $ids));
    }
}
