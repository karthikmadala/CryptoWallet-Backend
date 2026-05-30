<?php

namespace Tests\Feature\Api;

use App\Models\Token;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletAndPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_index_returns_wallet_collection(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, ['api:access']);

        $response = $this->getJson('/api/v1/wallets');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallets.0.wallet.id', $wallet->id);
    }

    public function test_portfolio_endpoint_returns_aggregated_balances(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'chain_type' => 'eth',
            'wallet_type' => 'external',
            'is_active' => true,
        ]);

        $token = Token::query()->create([
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'chain_type' => 'eth',
            'coingecko_id' => 'ethereum',
            'decimals' => 18,
            'current_price_usd' => 3000,
        ]);

        WalletBalance::query()->create([
            'wallet_id' => $wallet->id,
            'token_id' => $token->id,
            'balance' => 1.5,
            'balance_usd' => 4500,
            'fetched_at' => now(),
        ]);

        Sanctum::actingAs($user, ['api:access']);

        $response = $this->getJson('/api/v1/portfolio');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.portfolio.total_value_usd', '4500.00')
            ->assertJsonPath('data.portfolio.wallet_count', 1);
    }
}
