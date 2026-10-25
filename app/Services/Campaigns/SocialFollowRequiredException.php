<?php

namespace App\Services\Campaigns;

use RuntimeException;

class SocialFollowRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Follow our social accounts and get approved before starting paid tasks.')
    {
        parent::__construct($message);
    }
}
