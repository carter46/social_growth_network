<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('incoming_crypto_transactions');
        Schema::dropIfExists('wallet_balance_history');
        Schema::dropIfExists('crypto_sell_requests');
        Schema::dropIfExists('crypto_deposit_wallets');
        Schema::dropIfExists('exchange_rate_history');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('otc_pricing_settings');
    }

    public function down(): void
    {
        // Irreversible — crypto OTC removed in Phase 3.
    }
};
