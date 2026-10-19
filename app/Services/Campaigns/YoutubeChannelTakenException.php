<?php

namespace App\Services\Campaigns;

use InvalidArgumentException;

class YoutubeChannelTakenException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $channelId,
        public readonly ?string $channelUrl = null,
        public readonly ?string $channelTitle = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage());
    }

    public static function defaultMessage(): string
    {
        return 'This YouTube channel is already linked to another account. Sign in to that account to manage it.';
    }
}
