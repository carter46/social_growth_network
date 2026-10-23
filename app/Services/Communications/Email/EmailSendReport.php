<?php

namespace App\Services\Communications\Email;

/**
 * Full trail for one send: every provider attempt plus the final outcome.
 * Used by the admin test-mail diagnostic so a fallback is never shown as a clean success.
 */
final class EmailSendReport
{
    public const OUTCOME_BREVO = 'brevo';

    public const OUTCOME_FALLBACK = 'fallback';

    public const OUTCOME_FAILED = 'failed';

    /**
     * @param  list<array<string, mixed>>  $attempts
     */
    public function __construct(
        public readonly SendResult $result,
        public readonly array $attempts,
        public readonly string $outcome,
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly ?string $replyTo = null,
    ) {}

    public function usedFallback(): bool
    {
        return $this->outcome === self::OUTCOME_FALLBACK;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'ok' => $this->outcome === self::OUTCOME_BREVO,
            'delivered' => $this->result->success,
            'used_fallback' => $this->usedFallback(),
            'from_name' => $this->fromName,
            'from_email' => $this->fromEmail,
            'reply_to' => $this->replyTo,
            'result' => $this->result->toDiagnosticArray(),
            'attempts' => $this->attempts,
        ];
    }
}
