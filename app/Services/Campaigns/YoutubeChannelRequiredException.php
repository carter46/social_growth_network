<?php

namespace App\Services\Campaigns;

use InvalidArgumentException;

/** The agent must link and verify a YouTube channel before starting a YouTube task. */
class YoutubeChannelRequiredException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Link and verify your YouTube channel before starting YouTube tasks.');
    }
}
