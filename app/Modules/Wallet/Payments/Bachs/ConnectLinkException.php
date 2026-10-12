<?php

namespace App\Modules\Wallet\Payments\Bachs;

use RuntimeException;

/** Bachs refused to create an onboarding link. The message is safe to show the agent. */
class ConnectLinkException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $providerCode = null)
    {
        parent::__construct($message);
    }
}
