<?php

namespace App\Modules\Wallet\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A provider call failed. $providerCode is the provider's own error code when it sent one;
 * $transient is true for timeouts, 429 and 5xx (the request may or may not have taken effect).
 */
class GatewayException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        public readonly ?int $httpStatus = null,
        public readonly bool $transient = false,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
