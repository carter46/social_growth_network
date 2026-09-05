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
}
