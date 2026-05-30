<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DELETE FROM transactions WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM transactions GROUP BY chain_type, tx_hash) AS t) AND tx_hash IS NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['chain_type', 'tx_hash'], 'transactions_chain_type_tx_hash_unique');
        });

        DB::statement('DELETE FROM tokens WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM tokens GROUP BY chain_type, contract_address) AS t) AND contract_address IS NOT NULL AND deleted_at IS NULL');

        Schema::table('tokens', function (Blueprint $table) {
            $table->unique(['chain_type', 'contract_address'], 'tokens_chain_type_contract_address_unique');
        });

        DB::statement('DELETE FROM wallet_balances WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM wallet_balances GROUP BY wallet_id, token_id, chain_type) AS t)');

        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->unique(['wallet_id', 'token_id', 'chain_type'], 'wallet_balances_wallet_token_chain_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_chain_type_tx_hash_unique');
        });
        Schema::table('tokens', function (Blueprint $table) {
            $table->dropUnique('tokens_chain_type_contract_address_unique');
        });
        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->dropUnique('wallet_balances_wallet_token_chain_unique');
        });
    }
};
