<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionStatus;
use App\Models\Role;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_destroy_soft_deletes_wallet_and_hides_it_from_listing(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['api:access']);

        $this->deleteJson("/api/v1/wallets/{$wallet->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('wallets', ['id' => $wallet->id]);

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonPath('data.wallets', []);
    }

    public function test_reimporting_soft_deleted_wallet_restores_existing_row(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'address' => '0x1111111111111111111111111111111111111111',
            'chain_type' => 'eth',
            'wallet_type' => 'external',
        ]);

        $wallet->delete();

        /** @var WalletService $service */
        $service = app(WalletService::class);

        $restored = $service->importExternal($user, [
            'address' => '0x1111111111111111111111111111111111111111',
            'chain_type' => 'eth',
            'label' => 'Restored Wallet',
        ]);

        $this->assertSame($wallet->id, $restored->id);
        $this->assertNull($restored->deleted_at);
        $this->assertTrue($restored->is_active);
        $this->assertSame('Restored Wallet', $restored->label);
    }

    public function test_soft_deleting_user_cascades_to_wallets_and_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
        ]);

        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'from_address' => $wallet->address,
            'to_address' => '0x2222222222222222222222222222222222222222',
            'amount' => '1.000000000000000000',
            'chain_type' => 'eth',
            'status' => TransactionStatus::PENDING->value,
        ]);

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSoftDeleted('wallets', ['id' => $wallet->id]);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_admin_can_restore_soft_deleted_token_by_recreating_same_symbol_and_chain(): void
    {
        $role  = Role::create(['name' => 'admin', 'label' => 'Admin', 'is_super_admin' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'role_id' => $role->id]);
        $token = Token::create([
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'chain_type' => 'eth',
            'decimals' => 18,
            'enabled' => true,
        ]);

        Sanctum::actingAs($admin, ['api:access']);

        $this->deleteJson("/api/v1/admin/tokens/{$token->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('tokens', ['id' => $token->id]);

        $this->postJson('/api/v1/admin/tokens', [
            'symbol' => 'ETH',
            'name' => 'Ethereum Restored',
            'chain_type' => 'eth',
            'contract' => null,
            'decimals' => 18,
            'current_price_usd' => 3000,
            'enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Token restored')
            ->assertJsonPath('data.token.id', $token->id)
            ->assertJsonPath('data.token.name', 'Ethereum Restored');

        $this->assertDatabaseHas('tokens', [
            'id' => $token->id,
            'name' => 'Ethereum Restored',
            'deleted_at' => null,
        ]);
    }
}
