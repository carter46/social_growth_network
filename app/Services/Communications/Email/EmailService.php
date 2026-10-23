<?php

namespace App\Services\Communications\Email;

use App\Jobs\RetryFailedEmailJob;
use App\Models\EmailIdentity;
use App\Models\IntegrationProvider;
use App\Services\Communications\Email\Providers\BrevoApiProvider;
use App\Services\Communications\Email\Providers\LaravelMailProvider;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

class EmailService
{
    public function __construct(
        private BrevoApiProvider $brevo,
        private LaravelMailProvider $laravelMail,
        private EmailDeliveryLogger $logger,
    ) {}

    /**
     * @param  int  $deferredStage  0 = initial send, 1 = after 5m retry, 2 = after 30m retry
     */
    public function send(OutgoingEmail $email, int $deferredStage = 0): SendResult
    {
        return $this->sendWithReport($email, $deferredStage)->result;
    }

    /**
     * Same send path as send(), but returns every provider attempt for admin diagnostics.
     *
     * @param  int  $deferredStage  0 = initial send, 1 = after 5m retry, 2 = after 30m retry
     */
    public function sendWithReport(OutgoingEmail $email, int $deferredStage = 0): EmailSendReport
    {
        $this->hydrateTemplate($email);
        [$fromName, $fromEmail, $replyTo] = $this->resolveIdentity($email);
        $correlationId = $this->logger->newCorrelationId();
        $attempt = 0;
        $attempts = [];
        $brevoError = null;

        $brevoResult = $this->attemptBrevo($email, $fromName, $fromEmail, $replyTo, $correlationId, ++$attempt);
        $attempts[] = $this->attemptRow('Brevo', $brevoResult, 1);
        if ($brevoResult->success) {
            return new EmailSendReport($brevoResult, $attempts, EmailSendReport::OUTCOME_BREVO, $fromName, $fromEmail, $replyTo);
        }
        $brevoError = $brevoResult->error;

        $brevoRetry = $this->attemptBrevo($email, $fromName, $fromEmail, $replyTo, $correlationId, ++$attempt);
        $attempts[] = $this->attemptRow('Brevo (retry)', $brevoRetry, 2);
        if ($brevoRetry->success) {
            return new EmailSendReport($brevoRetry, $attempts, EmailSendReport::OUTCOME_BREVO, $fromName, $fromEmail, $replyTo);
        }
        $brevoError = $brevoRetry->error ?: $brevoError;

        $laravelResult = $this->attemptLaravel($email, $fromName, $fromEmail, $replyTo, $correlationId, ++$attempt, true);
        $attempts[] = $this->attemptRow('SMTP / Laravel Mail (fallback)', $laravelResult, 3);
        if ($laravelResult->success) {
            $this->rememberFallback($brevoError);

            return new EmailSendReport($laravelResult, $attempts, EmailSendReport::OUTCOME_FALLBACK, $fromName, $fromEmail, $replyTo);
        }

        $failed = $this->handleTotalFailure($email, $laravelResult, $deferredStage, $correlationId, $attempt);

        return new EmailSendReport($failed, $attempts, EmailSendReport::OUTCOME_FAILED, $fromName, $fromEmail, $replyTo);
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptRow(string $label, SendResult $result, int $step): array
    {
        return [
            'step' => $step,
            'label' => $label,
            'provider' => $result->provider,
            'success' => $result->success,
            'error' => $result->error,
            'message_id' => $result->messageId,
            'http_status' => $result->httpStatus,
            'latency_ms' => $result->latencyMs,
        ];
    }

    public function sendRaw(
        string $to,
        string $subject,
        string $body,
        EmailProfile $profile = EmailProfile::NoReply,
        bool $html = false,
    ): SendResult {
        return $this->sendRawWithReport($to, $subject, $body, $profile, $html)->result;
    }

    public function sendRawWithReport(
        string $to,
        string $subject,
        string $body,
        EmailProfile $profile = EmailProfile::NoReply,
        bool $html = false,
    ): EmailSendReport {
        return $this->sendWithReport(new OutgoingEmail(
            to: [['email' => $to]],
            subject: $subject,
            htmlContent: $html ? $body : null,
            textContent: $html ? \App\Support\Email\HtmlToText::convert($body) : $body,
            profile: $profile,
            tags: ['transactional'],
        ));
    }

    public function sendMailableHtml(
        string $to,
        string $subject,
        string $html,
        ?string $text = null,
        EmailProfile $profile = EmailProfile::NoReply,
        ?string $templateKey = null,
    ): SendResult {
        return $this->send(new OutgoingEmail(
            to: [['email' => $to]],
            subject: $subject,
            htmlContent: $html,
            textContent: $text ?? \App\Support\Email\HtmlToText::convert($html),
            templateKey: $templateKey,
            profile: $profile,
            tags: $templateKey ? [$templateKey] : ['transactional'],
        ));
    }

    private function attemptBrevo(
        OutgoingEmail $email,
        string $fromName,
        string $fromEmail,
        ?string $replyTo,
        string $correlationId,
        int $attemptNumber,
    ): SendResult {
        if (! $this->brevo->isAvailable()) {
            $result = SendResult::fail(IntegrationProvider::BREVO, 'Brevo is disabled or missing API key.');
            $this->logger->log($correlationId, $email, $result, $attemptNumber);

            return $result;
        }

        $result = $this->brevo->send($email, $fromName, $fromEmail, $replyTo);
        $row = IntegrationProvider::forProvider(IntegrationProvider::BREVO);
        if ($result->success) {
            $row->recordSuccess($result->latencyMs);
            $this->bumpDailyUsage($row);
            $this->clearFallbackMeta($row);
        } else {
            $row->recordFailure($this->formatError($result));
        }
        $this->logger->log($correlationId, $email, $result, $attemptNumber);

        return $result;
    }

    private function attemptLaravel(
        OutgoingEmail $email,
        string $fromName,
        string $fromEmail,
        ?string $replyTo,
        string $correlationId,
        int $attemptNumber,
        bool $isFallback,
    ): SendResult {
        if (! $this->laravelMail->isAvailable()) {
            $result = SendResult::fail(IntegrationProvider::LARAVEL_MAIL, 'Laravel mail fallback is unavailable.');
            $this->logger->log($correlationId, $email, $result, $attemptNumber, $isFallback);

            return $result;
        }

        $result = $this->laravelMail->send($email, $fromName, $fromEmail, $replyTo);
        $row = IntegrationProvider::forProvider(IntegrationProvider::LARAVEL_MAIL);
        if ($result->success) {
            $row->recordSuccess($result->latencyMs);
            $this->bumpDailyUsage($row);
        } else {
            $row->recordFailure((string) $result->error);
        }
        $this->logger->log($correlationId, $email, $result, $attemptNumber, $isFallback, $result->success ? 'fallback_success' : 'failed');

        return $result;
    }

    private function handleTotalFailure(
        OutgoingEmail $email,
        SendResult $last,
        int $deferredStage,
        string $correlationId,
        int $attemptNumber,
    ): SendResult {
        $queue = (string) config('queue.default', 'sync');

        if ($queue !== 'sync' && $deferredStage < 1) {
            Log::warning('email.retry_scheduled', ['stage' => 1, 'minutes' => 5]);
            RetryFailedEmailJob::dispatch($email, 1)->delay(now()->addMinutes(5));

            return SendResult::fail($last->provider, 'Deferred retry in 5 minutes: '.$last->error, $last->latencyMs, $last->httpStatus, $last->providerErrorCode, $last->responseBody, $last->requestId, 'deferred');
        }

        if ($queue !== 'sync' && $deferredStage === 1) {
            Log::warning('email.retry_scheduled', ['stage' => 2, 'minutes' => 30]);
            RetryFailedEmailJob::dispatch($email, 2)->delay(now()->addMinutes(30));

            return SendResult::fail($last->provider, 'Deferred retry in 30 minutes: '.$last->error, $last->latencyMs, $last->httpStatus, $last->providerErrorCode, $last->responseBody, $last->requestId, 'deferred');
        }

        $this->notifyAdminsOfFailure($email, (string) $last->error);

        return $last;
    }

    private function formatError(SendResult $result): string
    {
        $parts = [];
        if ($result->httpStatus) {
            $parts[] = 'HTTP '.$result->httpStatus;
        }
        if ($result->providerErrorCode) {
            $parts[] = (string) $result->providerErrorCode;
        }
        $parts[] = (string) $result->error;

        return mb_substr(implode(' — ', array_filter($parts)), 0, 2000);
    }

    private function rememberFallback(?string $brevoError): void
    {
        $row = IntegrationProvider::forProvider(IntegrationProvider::BREVO);
        $meta = $row->meta ?? [];
        $meta['last_fallback_reason'] = $brevoError;
        $meta['last_fallback_at'] = now()->toIso8601String();
        $row->meta = $meta;
        $row->save();
    }

    private function clearFallbackMeta(IntegrationProvider $row): void
    {
        $meta = $row->meta ?? [];
        unset($meta['last_fallback_reason'], $meta['last_fallback_at']);
        $row->meta = $meta;
        $row->save();
    }

    private function notifyAdminsOfFailure(OutgoingEmail $email, string $error): void
    {
        try {
            app(NotificationDispatcher::class)->notifyAdmins(
                new NotificationMessage(
                    type: 'email.delivery_failed',
                    title: 'Email delivery failed',
                    body: mb_substr('Subject: '.$email->subject.' — '.$error, 0, 2000),
                    actionUrl: route('admin.settings.email-settings'),
                    meta: [
                        'template' => $email->templateKey,
                        'to' => $email->to,
                        'event' => 'email.send_failed',
                    ],
                    permission: 'system.manage',
                    dedupeKey: 'email.delivery_failed.'.md5($email->subject.$error.implode(',', array_column($email->to, 'email'))),
                    emailSubject: 'Email delivery failed',
                ),
                ['database']
            );
        } catch (Throwable $e) {
            Log::error('email.admin_notify_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function resolveIdentity(OutgoingEmail $email): array
    {
        $identity = EmailIdentity::forProfile($email->profile->value)
            ?? EmailIdentity::defaultIdentity();

        $fromName = $identity?->from_name ?: (string) config('mail.from.name', config('app.name'));
        $fromEmail = $identity?->from_email ?: (string) config('mail.from.address', 'noreply@example.com');
        $replyTo = $email->replyTo ?: ($identity?->reply_to_email);

        return [$fromName, $fromEmail, $replyTo];
    }

    private function hydrateTemplate(OutgoingEmail $email): void
    {
        if (! $email->templateKey || ($email->htmlContent || $email->textContent)) {
            return;
        }

        $view = config('communications.templates.'.$email->templateKey);
        if (! is_string($view) || ! View::exists($view)) {
            return;
        }

        $email->htmlContent = View::make($view, $email->params)->render();
        $email->textContent = \App\Support\Email\HtmlToText::convert($email->htmlContent);
    }

    private function bumpDailyUsage(IntegrationProvider $row): void
    {
        $meta = $row->meta ?? [];
        $day = now()->toDateString();
        $usage = $meta['daily_usage'] ?? [];
        $usage[$day] = (int) ($usage[$day] ?? 0) + 1;
        $usage = array_slice($usage, -14, null, true);
        $meta['daily_usage'] = $usage;
        $meta['last_email_sent_at'] = now()->toIso8601String();
        $row->meta = $meta;
        $row->save();
    }
}
