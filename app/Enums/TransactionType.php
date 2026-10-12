<?php

namespace App\Enums;

enum TransactionType: string
{
    case Funding = 'funding';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
    case PlatformFee = 'platform_fee';
    case Purchase = 'purchase';
    case AdminAdjustment = 'admin_adjustment';
    case Reversal = 'reversal';
    case WithdrawalUnlock = 'withdrawal_unlock';
    case WithdrawalHold = 'withdrawal_hold';
    case CampaignReward = 'campaign_reward';
    case ReferralCommission = 'referral_commission';
    case CampaignBudgetHold = 'campaign_budget_hold';
    case CampaignBudgetRelease = 'campaign_budget_release';
    case WithdrawalFee = 'withdrawal_fee';
    case PayoutFeeAbsorbed = 'payout_fee_absorbed';
    case BounceFee = 'bounce_fee';
    case BounceFeeWaiver = 'bounce_fee_waiver';
    case CurrencyConversionRounding = 'currency_conversion_rounding';
    case PayoutProviderFeeRecovered = 'payout_provider_fee_recovered';
    case PayoutProviderFeeCost = 'payout_provider_fee_cost';
    case ReferralCommissionExpense = 'referral_commission_expense';
    case UnfundedCampaignRewardExpense = 'unfunded_campaign_reward_expense';

    public function label(): string
    {
        return match ($this) {
            self::Funding => 'Funding',
            self::Withdrawal => 'Withdrawal',
            self::Refund => 'Refund',
            self::PlatformFee => 'Platform fee',
            self::Purchase => 'Purchase',
            self::AdminAdjustment => 'Admin adjustment',
            self::Reversal => 'Reversal',
            self::WithdrawalUnlock => 'Withdrawal released',
            self::WithdrawalHold => 'Withdrawal hold',
            self::CampaignReward => 'Campaign reward',
            self::ReferralCommission => 'Referral commission',
            self::CampaignBudgetHold => 'Campaign budget held',
            self::CampaignBudgetRelease => 'Campaign budget settled',
            self::WithdrawalFee => 'Withdrawal fee',
            self::PayoutFeeAbsorbed => 'Payout fee absorbed',
            self::BounceFee => 'Bounce fee',
            self::BounceFeeWaiver => 'Bounce fee waived',
            self::CurrencyConversionRounding => 'Currency conversion rounding',
            self::PayoutProviderFeeRecovered => 'Provider fee recovered',
            self::PayoutProviderFeeCost => 'Provider fee paid',
            self::ReferralCommissionExpense => 'Referral commission expense',
            self::UnfundedCampaignRewardExpense => 'Unfunded campaign reward expense',
        };
    }

    public static function labelFor(?string $type): string
    {
        $case = $type ? self::tryFrom($type) : null;

        return $case?->label() ?? ucfirst(str_replace('_', ' ', (string) $type));
    }
}
