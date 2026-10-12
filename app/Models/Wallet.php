<?php

namespace App\Models;

use App\Enums\WalletType;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'locked_balance' => 'decimal:4',
            'type' => WalletType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fundings(): HasMany
    {
        return $this->hasMany(WalletFunding::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(WalletHold::class);
    }

    /**
     * Spendable funds at ledger precision (4 decimals): total balance minus active locks.
     * Compare with bccomp at scale 4; sub-cent rewards are part of it.
     */
    public function availableBalance(): string
    {
        return Money::sub((string) $this->balance, (string) $this->locked_balance, Money::ledgerScale());
    }

    /**
     * The most that can be withdrawn: the available balance rounded down to the cent.
     * Any sub-cent remainder stays in the wallet.
     */
    public function withdrawableBalance(): string
    {
        $available = $this->availableBalance();
        if (bccomp($available, '0', 4) <= 0) {
            return '0.00';
        }

        return bcadd($available, '0', 2);
    }

    /** The sub-cent part of the available balance that cannot be withdrawn yet. */
    public function subCentRemainder(): string
    {
        return bcsub($this->availableBalance(), $this->withdrawableBalance(), 4);
    }
}
