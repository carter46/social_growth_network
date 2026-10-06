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
        };
    }

    public static function labelFor(?string $type): string
    {
        $case = $type ? self::tryFrom($type) : null;

        return $case?->label() ?? ucfirst(str_replace('_', ' ', (string) $type));
    }
}
