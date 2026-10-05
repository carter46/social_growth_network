<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\Communications\Contact\PlatformContactRepository;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Services\Notifications\EmailIdentityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NewsletterSubscriptionController extends Controller
{
    private const SUCCESS = "You're subscribed. Thanks for joining our newsletter.";

    public function store(Request $request, EmailService $mailer, PlatformContactRepository $contact): RedirectResponse
    {
        // Honeypot: real visitors never see or fill this field.
        if (filled($request->input('website'))) {
            return back()->with('newsletter_status', self::SUCCESS)->withFragment('newsletter');
        }

        $data = $request->validateWithBag('newsletter', [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
        ]);

        $email = Str::lower(trim($data['email']));
        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);
        $isNewSignup = ! $subscriber->exists || ! $subscriber->isActive();

        if ($isNewSignup) {
            $subscriber->fill([
                'user_id' => $request->user()?->id ?? $subscriber->user_id,
                'source' => $data['source'] ?? 'website',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ])->save();

            app()->terminating(fn () => $this->notifyTeam($subscriber, $mailer, $contact));
        }

        return back()->with('newsletter_status', self::SUCCESS)->withFragment('newsletter');
    }

    private function notifyTeam(NewsletterSubscriber $subscriber, EmailService $mailer, PlatformContactRepository $contact): void
    {
        $details = $contact->all();
        $to = app(EmailIdentityResolver::class)->notifyToEmailForProfile(EmailProfile::General)
            ?: $details['email_info']
            ?: $details['email_support']
            ?: (string) config('mail.from.address');

        if (! filled($to)) {
            return;
        }

        $body = implode("\n", [
            'A new visitor joined the newsletter.',
            '',
            'Email: '.$subscriber->email,
            'Signed up from: '.($subscriber->source ?: 'website'),
            'Date: '.$subscriber->subscribed_at?->toDayDateTimeString(),
            '',
            'View all subscribers: '.route('admin.newsletter'),
        ]);

        try {
            $mailer->sendRaw($to, 'New newsletter subscriber: '.$subscriber->email, $body, EmailProfile::General);
        } catch (Throwable $e) {
            Log::warning('newsletter.notify_failed', ['email' => $subscriber->email, 'error' => $e->getMessage()]);
        }
    }
}
