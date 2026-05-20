<?php

namespace Tests\Feature\Api;

use App\Models\Token;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset array cache between tests — clears both cached portfolios and rate limit counters
        Cache::flush();
    }

    // ─── GET /api/v1/portfolio/{wallet} ──────────────────────────────────────

    public function test_show_returns_structured_wallet_portfolio(): void
    {
        [$user, $wallet] = $this->seedWalletWithBalance(balance: '1.5', priceUsd: 3000.0);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.portfolio.total_value_usd', '4500.00')
            ->assertJsonPath('data.portfolio.wallet.id', $wallet->id)
            ->assertJsonPath('data.portfolio.wallet.chain_type', 'eth')
            ->assertJsonPath('data.portfolio.balances.0.symbol', 'ETH')
            ->assertJsonPath('data.portfolio.balances.0.price_usd', 3000.0)
            ->assertJsonStructure([
                'success', 'message', 'errors',
                'data' => [
                    'portfolio' => [
                        'wallet'  => ['id', 'address', 'chain_type', 'chain_label', 'wallet_type', 'label', 'last_synced_at'],
                        'balances' => [['symbol', 'name', 'contract', 'balance', 'price_usd', 'value_usd', 'last_synced_at']],
                        'total_value_usd',
                    ],
                ],
            ]);
    }

    public function test_show_returns_empty_balances_for_unsynced_wallet(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertOk()
            ->assertJsonPath('data.portfolio.balances', [])
            ->assertJsonPath('data.portfolio.total_value_usd', '0.00');
    }

    public function test_show_requires_authentication(): void
    {
        $wallet = Wallet::factory()->create();

        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_403_for_another_users_wallet(): void
    {
        $owner  = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $owner->id]);

        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_404_for_nonexistent_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/portfolio/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ─── Cache behaviour ─────────────────────────────────────────────────────

    public function test_show_serves_stale_data_from_cache_on_repeat_request(): void
    {
        [$user, $wallet, , $balance] = $this->seedWalletWithBalance(balance: '1.0', priceUsd: 2000.0);
        Sanctum::actingAs($user);

        // First request — cache miss, builds from DB
        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertOk()
            ->assertJsonPath('data.portfolio.total_value_usd', '2000.00');

        // Update DB balance to new values
        $balance->update(['balance' => '2.0', 'balance_usd' => '4000.00000000']);

        // Second request — cache HIT; must still return old value
        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertOk()
            ->assertJsonPath('data.portfolio.total_value_usd', '2000.00');
    }

    public function test_refresh_true_is_forwarded_to_service_and_busts_cache(): void
    {
        [$user, $wallet] = $this->seedWalletWithBalance(balance: '1.0', priceUsd: 2000.0);
        Sanctum::actingAs($user);

        // Mock the service to verify refresh=true is passed and avoid real blockchain calls
        $this->mock(PortfolioService::class)
            ->shouldReceive('getWalletPortfolio')
            ->once()
            ->withArgs(fn(Wallet $w, bool $refresh) => $w->id === $wallet->id && $refresh === true)
            ->andReturn([
                'wallet'          => [
                    'id'             => $wallet->id,
                    'address'        => $wallet->address,
                    'chain_type'     => 'eth',
                    'chain_label'    => 'Ethereum',
                    'wallet_type'    => 'external',
                    'label'          => $wallet->label,
                    'last_synced_at' => null,
                ],
                'balances'        => [],
                'total_value_usd' => '9999.00',
            ]);

        $this->getJson("/api/v1/portfolio/{$wallet->id}?refresh=true")
            ->assertOk()
            ->assertJsonPath('data.portfolio.total_value_usd', '9999.00');
    }

    public function test_refresh_false_does_not_call_sync(): void
    {
        [$user, $wallet] = $this->seedWalletWithBalance(balance: '1.0', priceUsd: 2000.0);
        Sanctum::actingAs($user);

        // Service must be called with refresh=false when param is absent
        $this->mock(PortfolioService::class)
            ->shouldReceive('getWalletPortfolio')
            ->once()
            ->withArgs(fn(Wallet $w, bool $refresh) => $w->id === $wallet->id && $refresh === false)
            ->andReturn([
                'wallet'          => [
                    'id' => $wallet->id, 'address' => $wallet->address,
                    'chain_type' => 'eth', 'chain_label' => 'Ethereum',
                    'wallet_type' => 'external', 'label' => $wallet->label,
                    'last_synced_at' => null,
                ],
                'balances'        => [],
                'total_value_usd' => '2000.00',
            ]);

        $this->getJson("/api/v1/portfolio/{$wallet->id}")
            ->assertOk();
    }

    // ─── Rate limiting ───────────────────────────────────────────────────────

    public function test_rate_limit_returns_429_after_10_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/portfolio')->assertOk();
        }

        $this->getJson('/api/v1/portfolio')->assertStatus(429);
    }

    public function test_rate_limit_is_per_user_not_global(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Exhaust userA's limit
        Sanctum::actingAs($userA);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/portfolio')->assertOk();
        }
        $this->getJson('/api/v1/portfolio')->assertStatus(429);

        // userB still has their own budget
        Sanctum::actingAs($userB);
        $this->getJson('/api/v1/portfolio')->assertOk();
    }

    // ─── GET /api/v1/portfolio ───────────────────────────────────────────────

    public function test_index_returns_portfolio_resource_format(): void
    {
        [$user] = $this->seedWalletWithBalance(balance: '1.5', priceUsd: 3000.0);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/portfolio')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.portfolio.total_value_usd', '4500.00')
            ->assertJsonPath('data.portfolio.wallet_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'portfolio' => ['total_value_usd', 'wallet_count', 'wallets'],
                ],
            ]);
    }

    public function test_index_excludes_inactive_wallets(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/portfolio')
            ->assertOk()
            ->assertJsonPath('data.portfolio.wallet_count', 0)
            ->assertJsonPath('data.portfolio.total_value_usd', '0.00');
    }

    // ─── GET /api/v1/portfolio/chain/{chain} ─────────────────────────────────

    public function test_chain_endpoint_returns_400_for_invalid_chain(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/portfolio/chain/solana')
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_chain_endpoint_returns_only_wallets_for_requested_chain(): void
    {
        [$user, $ethWallet] = $this->seedWalletWithBalance(chainType: 'eth', balance: '1.0', priceUsd: 3000.0);
        Wallet::factory()->create(['user_id' => $user->id, 'chain_type' => 'bnb', 'is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/portfolio/chain/eth');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.wallets');

        // BNB wallet must not appear
        $walletIds = collect($response->json('data.wallets'))->pluck('wallet.id');
        $this->assertTrue($walletIds->contains($ethWallet->id));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Create a user, active wallet, token, and a pre-populated WalletBalance row.
     *
     * @return array{0: User, 1: Wallet, 2: Token, 3: WalletBalance}
     */
    private function seedWalletWithBalance(
        string $chainType = 'eth',
        string $balance = '1.5',
        float $priceUsd = 3000.0,
    ): array {
        $user = User::factory()->create();

        $wallet = Wallet::factory()->create([
            'user_id'    => $user->id,
            'chain_type' => $chainType,
            'is_active'  => true,
        ]);

        $token = Token::create([
            'symbol'            => strtoupper($chainType),
            'name'              => 'Test Token',
            'chain_type'        => $chainType,
            'coingecko_id'      => $chainType . '-token',
            'decimals'          => 18,
            'current_price_usd' => $priceUsd,
        ]);

        $walletBalance = WalletBalance::create([
            'wallet_id'   => $wallet->id,
            'token_id'    => $token->id,
            'balance'     => $balance,
            'balance_usd' => bcmul($balance, (string) $priceUsd, 8),
            'fetched_at'  => now(),
        ]);

        return [$user, $wallet, $token, $walletBalance];
    }
}
