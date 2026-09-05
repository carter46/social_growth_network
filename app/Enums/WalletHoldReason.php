<?php

namespace App\Enums;

enum WalletHoldReason: string
{
    case Withdrawal = 'withdrawal';
    case Compliance = 'compliance';
}
