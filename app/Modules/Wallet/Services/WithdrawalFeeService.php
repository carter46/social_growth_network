<?php

namespace App\Modules\Wallet\Services;

use App\Models\PayoutAccount;
use App\Models\PayoutCoverage;
use App\Models\PayoutFeeRule;
use App\Models\SystemSetting;
use App\Models\Withdrawal;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Payments\Data\PayoutQuote;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

/**
 * Agent fee = provider fee (live quote or verified entry) + optional platform fee.
 * Also the effective minimum per route and the re-quote tolerance at approval.
 */
class WithdrawalFeeService
{
    public const DECISION_KEEP = 'keep';

    public const DECISION_ABSORB = 'absorb';

    public const DECISION_CONFIRM = 'confirm';

    public function __construct(
        private PayoutQuoteService $quotes,
    ) {}

    public static function globalMin(): string
    {
        return Money::round((string) SystemSetting::get('withdrawal_min_amount', '10'), 2);
    }

    public static function globalMax(): string
    {
        return Money::round((string) SystemSetting::get('withdrawal_max_amount', '5000'), 2);
    }

    /**
     * Full breakdown for a withdrawal request. Throws a ValidationException on "amount" when the
     * route is unavailable, the fee is unknown, the amount is outside the limits or nothing is left to send.
     *
     * @return array{amount: string, provider_fee: string, platform_fee: string, fee: string, net: string, min: string, max: string, gateway_key: string, fee_rule_id: ?int, quote: array<string, mixed>, high_fee: bool}
     */
    public function quote(string $amount, PayoutAccount $account, ?string $gatewayKey = null): array
    {
        $amount = Money::round($amount, 2);
        $destination = PayoutAccountDetails::fromPayoutAccount($account);
        $gatewayKey ??= $this->quotes->gatewayFor($destination);
        $label = $account->methodLabel().' in '.$account->countryName();

        if ($gatewayKey === null) {
            throw ValidationException::withMessages(['amount' => __('Payouts to :route are paused right now.', ['route' => $label])]);
        }

        $quote = $this->quotes->quote($amount, $destination, $gatewayKey);
        if (! $quote) {
            throw ValidationException::withMessages(['amount' => __('Payouts to :route are temporarily unavailable. Try again later.', ['route' => $label])]);
        }

        $limits = $this->limits($account->country, $account->method, $gatewayKey, $quote);
        if (Money::cmp($amount, $limits['min'], 2) < 0) {
            throw ValidationException::withMessages(['amount' => __('The minimum for :method in :country is :min.', [
                'method' => mb_strtolower($account->methodLabel()),
                'country' => $account->countryName(),
                'min' => money($limits['min'], 2),
            ])]);
        }
        if (Money::cmp($amount, $limits['max'], 2) > 0) {
            throw ValidationException::withMessages(['amount' => __('The maximum for :method in :country is :max.', [
                'method' => mb_strtolower($account->methodLabel()),
                'country' => $account->countryName(),
                'max' => money($limits['max'], 2),
            ])]);
        }

        $rule = PayoutFeeRule::bestMatch($account->country, $account->method, $gatewayKey);
        $platformFee = $this->platformFee($amount, $rule);
        $providerFee = Money::round($quote->providerFee, 2);
        $fee = Money::add($providerFee, $platformFee, 2);
        $net = Money::sub($amount, $fee, 2);

        if (! Money::isPositive($net, 2)) {
            throw ValidationException::withMessages(['amount' => __('The fee is larger than this amount. Enter a higher amount.')]);
        }

        $highFeeShare = (float) config('payments.payout.high_fee_hint_percent', 10);

        return [
            'amount' => $amount,
            'provider_fee' => $providerFee,
            'platform_fee' => $platformFee,
            'fee' => $fee,
            'net' => $net,
            'min' => $limits['min'],
            'max' => $limits['max'],
            'gateway_key' => $gatewayKey,
            'fee_rule_id' => $rule?->id,
            'quote' => $quote->toArray(),
            'high_fee' => Money::cmp(Money::mul($amount, (string) ($highFeeShare / 100), 4), $fee, 4) < 0,
        ];
    }

    /**
     * Effective minimum = highest of the global floor, the admin minimum, the provider minimum and the
     * fee-share minimum. Maximum = lowest of the global, admin and provider maximums.
     *
     * @return array{min: string, max: string, fee_share_min: ?string}
     */
    public function limits(string $country, string $method, ?string $gatewayKey, ?PayoutQuote $quote = null): array
    {
        $rule = PayoutFeeRule::bestMatch($country, $method, $gatewayKey, fn (PayoutFeeRule $r) => $r->min_withdrawal !== null || $r->max_withdrawal !== null);

        $providerMin = null;
        $providerMax = null;
        if ($gatewayKey) {
            $row = PayoutCoverage::query()->where('gateway_key', $gatewayKey)->where('country', strtoupper($country))->where('method', $method)->first();
            $providerMin = $this->toUsd($row?->provider_min, $row?->currency, $quote);
            $providerMax = $this->toUsd($row?->provider_max, $row?->currency, $quote);
        }

        $feeShareMin = $this->feeShareMinimum($country, $method, $gatewayKey, $quote);

        $min = Money::max(self::globalMin(), $rule?->min_withdrawal, $providerMin, $feeShareMin);
        $maxCandidates = array_filter([self::globalMax(), $rule?->max_withdrawal, $providerMax], fn ($v) => $v !== null && $v !== '');
        $max = array_reduce($maxCandidates, fn ($carry, $v) => $carry === null || Money::cmp($v, $carry, 2) < 0 ? (string) $v : $carry);

        return [
            'min' => PayoutQuoteService::ceilCents($min),
            'max' => Money::round($max ?? self::globalMax(), 2),
            'fee_share_min' => $feeShareMin,
        ];
    }

    /**
     * Smallest amount where the provider fee is at most the max fee share of the amount.
     */
    public function feeShareMinimum(string $country, string $method, ?string $gatewayKey, ?PayoutQuote $quote = null): ?string
    {
        $share = (string) config('payments.payout.max_fee_share_percent', 20);
        if (! Money::isPositive($share)) {
            return null;
        }

        $rule = $this->quotes->verifiedRule($country, $method, $gatewayKey);
        if ($rule) {
            $flat = (string) ($rule->verified_provider_fee_flat ?? '0');
            $pct = (string) ($rule->verified_provider_fee_percent ?? '0');
            $room = Money::sub($share, $pct, 6);
            if (! Money::isPositive($room, 6)) {
                return null;
            }

            return PayoutQuoteService::ceilCents(Money::div(Money::mul($flat, '100', 8), $room, 8));
        }

        if ($quote && Money::isPositive($quote->providerFee, 2)) {
            return PayoutQuoteService::ceilCents(Money::div(Money::mul($quote->providerFee, '100', 8), $share, 8));
        }

        return null;
    }

    public function platformFee(string $amount, ?PayoutFeeRule $rule): string
    {
        if (! $rule) {
            return '0.00';
        }

        $fee = Money::add(
            (string) ($rule->flat_fee ?? '0'),
            Money::div(Money::mul($amount, (string) ($rule->percent_fee ?? '0'), 8), '100', 8),
            8,
        );
        if ($rule->min_fee !== null && Money::cmp($fee, (string) $rule->min_fee, 8) < 0) {
            $fee = (string) $rule->min_fee;
        }
        if ($rule->max_fee !== null && Money::cmp($fee, (string) $rule->max_fee, 8) > 0) {
            $fee = (string) $rule->max_fee;
        }

        return Money::round($fee, 2);
    }

    /** Highest extra provider fee the platform absorbs: max(flat, percent of the stored fee). */
    public function tolerance(string $storedFee): string
    {
        $flat = (string) config('payments.payout.fee_tolerance_flat', '1.00');
        $pct = Money::div(Money::mul($storedFee, (string) config('payments.payout.fee_tolerance_percent', 5), 8), '100', 8);

        return Money::round(Money::max($flat, $pct), 2);
    }

    /**
     * Re-quote at approval or before switching gateway.
     *
     * @return array{decision: string, quote: ?PayoutQuote, new_fee: ?string, difference: string}
     */
    public function requote(Withdrawal $withdrawal, string $gatewayKey): array
    {
        $destination = PayoutAccountDetails::fromWithdrawal($withdrawal);
        $quote = $this->quotes->quote($withdrawal->netPayoutAmount(), $destination, $gatewayKey);
        if (! $quote) {
            return ['decision' => self::DECISION_CONFIRM, 'quote' => null, 'new_fee' => null, 'difference' => '0.00'];
        }

        $stored = Money::round((string) ($withdrawal->provider_fee ?? '0'), 2);
        $new = Money::round($quote->providerFee, 2);
        $difference = Money::sub($new, $stored, 2);

        if (Money::cmp($difference, '0', 2) <= 0) {
            return ['decision' => self::DECISION_KEEP, 'quote' => $quote, 'new_fee' => $new, 'difference' => '0.00'];
        }
        if (Money::cmp($difference, $this->tolerance($stored), 2) <= 0) {
            return ['decision' => self::DECISION_ABSORB, 'quote' => $quote, 'new_fee' => $new, 'difference' => $difference];
        }

        return ['decision' => self::DECISION_CONFIRM, 'quote' => $quote, 'new_fee' => $new, 'difference' => $difference];
    }

    private function toUsd(mixed $value, ?string $currency, ?PayoutQuote $quote): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strtoupper((string) $currency) === 'USD') {
            return Money::round((string) $value, 2);
        }
        if ($quote && $quote->fxRate && Money::isPositive($quote->fxRate, 6)) {
            return PayoutQuoteService::ceilCents(Money::div((string) $value, $quote->fxRate, 8));
        }

        return null;
    }
}
