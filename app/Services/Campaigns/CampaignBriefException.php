<?php

namespace App\Services\Campaigns;

use InvalidArgumentException;

class CampaignBriefException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly string $field = 'target_url')
    {
        parent::__construct($message);
    }
}
