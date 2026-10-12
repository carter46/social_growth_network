<?php

namespace App\Console\Commands;

use App\Models\PayoutCoverage;
use App\Models\PayoutFeeRule;
use App\Modules\Wallet\Payments\Data\PayoutAccountDetails;
use App\Modules\Wallet\Services\PayoutQuoteService;
use App\Services\Notifications\AdminPaymentAlertNotifier;
use App\Support\Money\Money;
use Illuminate\Console\Command;

/**
 * Re-quotes a sample amount per verified route and flags routes whose live fee drifted from the
 * verified fee entry, so minimums can be reviewed.
 */
class CheckPayoutFeeQuotesCommand extends Command
{
    protected $signature = 'payments:check-fee-quotes {--amount=100 : Sample amount in USD}';

    protected $description = 'Compare live payout fee quotes with verified fee entries';

    public function handle(PayoutQuoteService $quotes, AdminPaymentAlertNotifier $alerts): int
    {
        $amount = Money::round((string) $this->option('amount'), 2);
        $threshold = (string) config('payments.payout.fee_drift_alert_percent', 10);
        $flagged = 0;

        PayoutCoverage::query()
            ->whereIn('state', [PayoutCoverage::VERIFIED_API, PayoutCoverage::VERIFIED_MANUAL])
            ->get()
            ->filter(fn (PayoutCoverage $row) => $row->isUsableState())
            ->each(function (PayoutCoverage $row) use ($quotes, $alerts, $amount, $threshold, &$flagged) {
                $rule = PayoutFeeRule::bestMatch($row->country, $row->method, $row->gateway_key, fn (PayoutFeeRule $r) => $r->hasVerifiedProviderFee());
                if (! $rule) {
                    return;
                }

                $destination = new PayoutAccountDetails($row->country, $row->currency, $row->method, 'Fee check');
                $live = $quotes->live($amount, $destination, $row->gateway_key, false);
                if (! $live) {
                    return;
                }

                $verified = PayoutQuoteService::verifiedFee($rule, $amount);
                $base = Money::isPositive($verified, 2) ? $verified : '0.01';
                $driftPct = Money::div(Money::mul(Money::sub($live->providerFee, $verified, 4), '100', 4), $base, 2);
                $label = $row->gateway_key.' '.$row->country.' '.$row->method;
                $this->line($label.': live '.$live->providerFee.' vs verified '.$verified.' ('.$driftPct.'%)');

                if (Money::cmp(ltrim($driftPct, '-'), $threshold, 2) > 0) {
                    $flagged++;
                    $rule->update([
                        'drift_note' => 'Live fee '.$live->providerFee.' vs verified '.$verified.' on '.$amount.' ('.$driftPct.'%)',
                        'drift_flagged_at' => now(),
                    ]);
                    $alerts->coverageAlert(
                        'Payout fee drift: '.$label,
                        'Live fee '.money($live->providerFee, 2).' vs verified '.money($verified, 2).' on '.money($amount, 2).'. Review the fee entry and minimum.',
                        'fee_drift.'.$row->gateway_key.'.'.$row->country.'.'.$row->method.'.'.now()->toDateString(),
                        ['rule_id' => $rule->id],
                    );
                }
            });

        $this->info($flagged.' route(s) flagged.');

        return self::SUCCESS;
    }
}
