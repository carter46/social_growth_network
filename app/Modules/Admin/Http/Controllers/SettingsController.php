<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsProvider;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailIdentity;
use App\Models\IntegrationProvider;
use App\Models\SystemSetting;
use App\Models\MediaUsage;
use App\Models\SocialLink;
use App\Modules\Admin\Services\AuditLogService;
use App\Services\Analytics\Providers\GoogleAnalyticsProvider;
use App\Services\Analytics\Providers\MicrosoftClarityProvider;
use App\Services\Tracking\TrackingScriptRenderer;
use App\Services\Branding\SiteBrandingRepository;
use App\Services\Communications\Contact\PlatformContactRepository;
use App\Services\Communications\Email\EmailProfile;
use App\Services\Communications\Email\EmailService;
use App\Services\Communications\LiveChat\LiveChatManager;
use App\Services\Communications\Social\SocialLinkRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private GoogleAnalyticsProvider $googleAnalytics,
        private MicrosoftClarityProvider $clarity,
        private SiteBrandingRepository $branding,
        private PlatformContactRepository $contact,
        private LiveChatManager $liveChat,
        private SocialLinkRepository $socialLinks,
        private EmailService $emails,
    ) {}

    public function index(): View
    {
        $chat = $this->liveChat->resolved();
        $branding = $this->branding->all();
        $contact = $this->contact->all();

        return view('dashboard.admin.settings', [
            'branding' => $branding,
            'contact' => $contact,
            'liveChat' => $chat,
            'socialLinks' => SocialLink::query()->orderBy('sort_order')->orderBy('id')->get(),
            'googleIdentity' => IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY),
            'googleIdentityJsOrigin' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function emailSettings(): View
    {
        $branding = $this->branding->all();

        return view('dashboard.admin.settings-email', [
            'brevo' => IntegrationProvider::forProvider(IntegrationProvider::BREVO),
            'laravelMail' => IntegrationProvider::forProvider(IntegrationProvider::LARAVEL_MAIL),
            'emailIdentities' => Schema::hasTable('email_identities')
                ? EmailIdentity::query()->orderBy('id')->get()
                : collect(),
            'recentEmailFailures' => $this->safeRecentEmailFailures(),
            'siteName' => $branding['site_name'],
        ]);
    }

    public function paymentSettings(): View
    {
        return view('dashboard.admin.settings-payments', [
            'monnify' => IntegrationProvider::forProvider(IntegrationProvider::MONNIFY),
            'monnifyWebhookUrl' => url('/webhooks/monnify'),
            'manualBankTransfer' => SystemSetting::manualBankTransferDetails(),
            'manualBankTransferEnabled' => SystemSetting::manualBankTransferEnabled(),
            'manualBankTransferSaveUrl' => Route::has('admin.settings.manual-bank-transfer')
                ? route('admin.settings.manual-bank-transfer')
                : null,
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_short_name' => ['nullable', 'string', 'max:60'],
            'heading' => ['nullable', 'string', 'max:200'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'favicon_media_id' => ['nullable', 'integer'],
            'logo_light_media_id' => ['nullable', 'integer'],
            'logo_dark_media_id' => ['nullable', 'integer'],
        ]);

        $this->branding->save($validated);
        $this->syncBrandingMediaUsages($validated);
        config(['app.name' => $validated['site_name']]);

        $synced = false;
        $syncError = null;
        try {
            /** @var \App\Services\Branding\PwaBrandingSync $pwaSync */
            $pwaSync = app(\App\Services\Branding\PwaBrandingSync::class);
            $synced = $pwaSync->sync(array_merge($this->branding->all(), $validated));
            if (! $synced) {
                $syncError = $pwaSync->lastError()
                    ?: __('Check storage/logs for pwa.branding_sync_failed — usually missing storage symlink, unreadable media file, PHP GD disabled, or public/ not writable.');
            }
        } catch (Throwable $e) {
            $synced = false;
            $syncError = $e->getMessage();
            report($e);
        }

        $this->audit->log(auth()->id(), 'settings.branding.updated', null, null, [
            'site_name' => $validated['site_name'],
            'pwa_icons_synced' => $synced,
            'pwa_sync_error' => $syncError,
        ], $request->ip());

        $hasBrandingMedia = (int) ($validated['favicon_media_id'] ?? 0) > 0
            || (int) ($validated['logo_light_media_id'] ?? 0) > 0
            || (int) ($validated['logo_dark_media_id'] ?? 0) > 0;

        if (! $synced && $hasBrandingMedia) {
            return back()->with('error', __(
                'Site information was saved, but favicon/PWA icons could not be regenerated from your branding media. :detail Fix PHP GD / storage paths / public write access, then run: php artisan branding:sync-pwa',
                ['detail' => $syncError ? '('.$syncError.')' : '']
            ));
        }

        if (! $synced) {
            return back()->with('warning', __(
                'Site information saved, but favicon/PWA icons could not be regenerated. :detail Ensure PHP GD is enabled and public/ is writable, then run: php artisan branding:sync-pwa',
                ['detail' => $syncError ? '('.$syncError.')' : '']
            ));
        }

        return back()->with('status', __('Site information saved. Favicon, Apple touch, and PWA icons were refreshed from your branding — reinstall the app / request indexing if Google still shows the old icon.'));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $request->merge([
            'maps_url' => $request->input('maps_url') ?: null,
            'maps_embed_url' => $request->input('maps_embed_url') ?: null,
        ]);

        $validated = $request->validate([
            'phone_support' => ['nullable', 'string', 'max:50'],
            'phone_general' => ['nullable', 'string', 'max:50'],
            'phone_whatsapp' => ['nullable', 'string', 'max:50'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', 'string', 'max:120'],
            'address_country' => ['nullable', 'string', 'max:120'],
            'address_postal' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'string', 'max:40'],
            'longitude' => ['nullable', 'string', 'max:40'],
            'maps_url' => ['nullable', 'url', 'max:500'],
            'maps_embed_url' => ['nullable', 'string', 'max:1000'],
            'support_hours' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'business_hours' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_number' => ['nullable', 'string', 'max:120'],
            'company_number' => ['nullable', 'string', 'max:120'],
            'live_chat_provider' => ['required', 'in:none,smartsupp,jivo,chatway'],
            'smartsupp_key' => ['nullable', 'string', 'max:255'],
            'jivo_widget_id' => ['nullable', 'string', 'max:255'],
            'chatway_widget_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['live_chat_provider'] === 'smartsupp') {
            $existing = (string) (IntegrationProvider::forProvider(IntegrationProvider::SMARTSUPP)->credential('key') ?? '');
            if (blank($validated['smartsupp_key'] ?? null) && $existing === '') {
                return back()->withInput()->withErrors(['smartsupp_key' => 'Smartsupp key is required when Smartsupp is selected.']);
            }
        }
        if ($validated['live_chat_provider'] === 'jivo') {
            $existing = (string) (IntegrationProvider::forProvider(IntegrationProvider::JIVO)->credential('widget_id') ?? '');
            if (blank($validated['jivo_widget_id'] ?? null) && $existing === '') {
                return back()->withInput()->withErrors(['jivo_widget_id' => 'Jivo widget ID is required when Jivo is selected.']);
            }
        }
        if ($validated['live_chat_provider'] === 'chatway') {
            $existing = (string) (IntegrationProvider::forProvider(IntegrationProvider::CHATWAY)->credential('widget_id') ?? '');
            if (blank($validated['chatway_widget_id'] ?? null) && $existing === '') {
                return back()->withInput()->withErrors(['chatway_widget_id' => 'Chatway widget ID is required when Chatway is selected.']);
            }
        }

        $this->contact->save($validated);
        $this->liveChat->save(
            $validated['live_chat_provider'],
            $validated['smartsupp_key'] ?? null,
            $validated['jivo_widget_id'] ?? null,
            $validated['chatway_widget_id'] ?? null,
        );

        $this->audit->log(auth()->id(), 'settings.contact.updated', null, null, [
            'live_chat_provider' => $validated['live_chat_provider'],
        ], $request->ip());

        return back()->with('status', __('Contact & live chat settings saved.'));
    }

    public function updateSocial(Request $request): RedirectResponse
    {
        $links = $request->input('links', []);
        if (is_array($links)) {
            foreach ($links as $i => $row) {
                if (isset($row['url']) && $row['url'] === '') {
                    $links[$i]['url'] = null;
                }
            }
            $request->merge(['links' => $links]);
        }

        $validated = $request->validate([
            'links' => ['nullable', 'array'],
            'links.*.id' => ['nullable', 'integer'],
            'links.*.platform' => ['nullable', 'string', 'max:60'],
            'links.*.url' => ['nullable', 'url', 'max:500'],
            'links.*.icon' => ['nullable', 'string', 'max:60'],
            'links.*.icon_media_id' => ['nullable', 'integer'],
            'links.*.enabled' => ['nullable', 'boolean'],
            'links.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'links.*.delete' => ['nullable', 'boolean'],
        ]);

        // Normalize empty URL rows before create/update.
        $normalized = [];
        foreach ($validated['links'] ?? [] as $row) {
            if (! empty($row['delete']) && ! empty($row['id'])) {
                $normalized[] = $row;
                continue;
            }
            if (blank($row['url'] ?? null) || blank($row['platform'] ?? null)) {
                continue;
            }
            $normalized[] = $row;
        }
        $validated['links'] = $normalized;

        foreach ($validated['links'] ?? [] as $row) {
            if (! empty($row['delete']) && ! empty($row['id'])) {
                SocialLink::query()->whereKey($row['id'])->delete();
                continue;
            }
            if (blank($row['platform'] ?? null) || blank($row['url'] ?? null)) {
                continue;
            }

            $payload = [
                'platform' => $row['platform'],
                'url' => $row['url'],
                'icon' => $row['icon'] ?? $row['platform'],
                'icon_media_id' => filled($row['icon_media_id'] ?? null) ? (int) $row['icon_media_id'] : null,
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];

            if (! empty($row['id'])) {
                SocialLink::query()->whereKey($row['id'])->update($payload);
            } else {
                SocialLink::query()->create($payload);
            }
        }

        $this->socialLinks->flush();
        $this->audit->log(auth()->id(), 'settings.social.updated', null, null, null, $request->ip());

        return back()->with('status', __('Social links saved.'));
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brevo_enabled' => ['nullable', 'boolean'],
            'brevo_api_key' => ['nullable', 'string', 'max:255'],
            'laravel_mail_enabled' => ['nullable', 'boolean'],
            'fallback_mailer' => ['nullable', 'in:smtp,sendmail'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', 'max:20'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'sendmail_path' => ['nullable', 'string', 'max:255'],
            'identities' => ['nullable', 'array'],
            'identities.*.from_name' => ['required', 'string', 'max:120'],
            'identities.*.from_email' => ['required', 'email', 'max:255'],
            'identities.*.reply_to_email' => ['nullable', 'email', 'max:255'],
            'identities.*.notify_to_email' => ['nullable', 'email', 'max:255'],
            'identities.*.enabled' => ['nullable', 'boolean'],
        ]);

        $brevo = IntegrationProvider::forProvider(IntegrationProvider::BREVO);
        $brevo->enabled = $request->boolean('brevo_enabled');
        if (filled($validated['brevo_api_key'] ?? null)) {
            $brevo->mergeCredentials(['api_key' => $validated['brevo_api_key']]);
        }
        $brevo->status = $brevo->enabled && filled($brevo->credential('api_key')) ? 'connected' : 'idle';
        $brevo->save();

        $laravel = IntegrationProvider::forProvider(IntegrationProvider::LARAVEL_MAIL);
        $laravel->enabled = $request->boolean('laravel_mail_enabled');
        $creds = [
            'mailer' => $validated['fallback_mailer'] ?? 'smtp',
            'host' => $validated['smtp_host'] ?? '',
            'port' => $validated['smtp_port'] ?? 587,
            'encryption' => $validated['smtp_encryption'] ?? 'tls',
            'username' => $validated['smtp_username'] ?? '',
            'sendmail_path' => $validated['sendmail_path'] ?? '',
        ];
        if (filled($validated['smtp_password'] ?? null)) {
            $creds['password'] = $validated['smtp_password'];
        } else {
            $creds['password'] = $laravel->credential('password', '');
        }
        $laravel->mergeCredentials($creds);
        $laravel->status = $laravel->enabled ? 'connected' : 'idle';
        $laravel->save();

        $hasNotifyInbox = Schema::hasTable('email_identities')
            && Schema::hasColumn('email_identities', 'notify_to_email');

        foreach ($validated['identities'] ?? [] as $profile => $row) {
            $payload = [
                'from_name' => $row['from_name'],
                'from_email' => $row['from_email'],
                'reply_to_email' => $row['reply_to_email'] ?? null,
                'enabled' => (bool) ($row['enabled'] ?? true),
            ];
            if ($hasNotifyInbox) {
                $payload['notify_to_email'] = $row['notify_to_email'] ?? null;
            }

            EmailIdentity::query()->updateOrCreate(
                ['profile' => $profile],
                $payload,
            );
        }

        $this->contact->flush();
        $this->audit->log(auth()->id(), 'settings.email.updated', null, null, [
            'brevo_enabled' => $brevo->enabled,
            'laravel_mail_enabled' => $laravel->enabled,
        ], $request->ip());

        return back()->with('status', __('Email settings saved.'));
    }

    public function testMail(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
            'test_subject' => ['nullable', 'string', 'max:150'],
        ]);

        $to = $validated['test_email'];
        $siteName = $this->branding->siteName();
        $subject = $validated['test_subject'] ?: $siteName.' — test email';
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $result = $this->emails->sendRaw(
                $to,
                $subject,
                "This is a test email from {$siteName} Admin Settings.\n\nIf you received this, your mail configuration is working.\n\nSent at: ".now()->toDateTimeString(),
                EmailProfile::NoReply,
            );

            $brevo = IntegrationProvider::forProvider(IntegrationProvider::BREVO)->fresh();
            $errorDetail = $result->error
                ?: $brevo->last_error
                ?: ($brevo->meta['last_fallback_reason'] ?? null);
            $fallbackReason = $brevo->meta['last_fallback_reason'] ?? null;
            $usedFallback = $result->success
                && $result->provider === IntegrationProvider::LARAVEL_MAIL
                && filled($fallbackReason);

            $this->audit->log(auth()->id(), 'settings.mail_test', null, null, [
                'recipient' => $to,
                'ok' => $result->success,
                'provider' => $result->provider,
                'error' => $errorDetail,
                'message_id' => $result->messageId,
                'http_status' => $result->httpStatus,
            ], $request->ip());

            if (! $result->success) {
                $message = 'Mail send failed via '.$result->provider.': '.($errorDetail ?: 'Unknown error');

                if ($wantsJson) {
                    return response()->json([
                        'ok' => false,
                        'message' => $message,
                        'provider' => $result->provider,
                        'error' => $errorDetail,
                        'http_status' => $result->httpStatus,
                    ], 422);
                }

                return back()->withInput()->withErrors(['test_email' => $message]);
            }

            if ($usedFallback) {
                $message = __('Sent via Laravel Mail fallback. Brevo error: :error', [
                    'error' => $fallbackReason,
                ]);
            } else {
                $message = __('Test email sent to :email via :provider.', [
                    'email' => $to,
                    'provider' => $result->provider === 'brevo' ? 'Brevo' : $result->provider,
                ]);
                if ($result->messageId) {
                    $message .= ' Message ID: '.$result->messageId;
                }
            }

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                    'provider' => $result->provider,
                    'message_id' => $result->messageId,
                    'http_status' => $result->httpStatus,
                    'used_fallback' => $usedFallback,
                    'fallback_reason' => $usedFallback ? $fallbackReason : null,
                ]);
            }

            return back()->with('status', $message);
        } catch (Throwable $e) {
            $message = 'Mail send failed: '.$e->getMessage();

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'error' => $e->getMessage(),
                ], 500);
            }

            return back()->withInput()->withErrors(['test_email' => $message]);
        }
    }

    public function updateAnalytics(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_enabled' => ['nullable', 'boolean'],
            'google_measurement_id' => ['nullable', 'string', 'max:32'],
            'google_property_id' => ['nullable', 'string', 'max:32'],
            'clarity_enabled' => ['nullable', 'boolean'],
            'clarity_project_id' => ['nullable', 'string', 'max:64'],
        ]);

        $google = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS);
        $clarity = AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_MICROSOFT_CLARITY);

        $googleEnabled = $request->boolean('google_enabled');
        $clarityEnabled = $request->boolean('clarity_enabled');

        if ($googleEnabled && blank($validated['google_measurement_id'] ?? null)) {
            return back()->withInput()->withErrors([
                'google_measurement_id' => 'Measurement ID is required when Google Analytics is enabled.',
            ]);
        }

        if ($clarityEnabled && blank($validated['clarity_project_id'] ?? null)) {
            return back()->withInput()->withErrors([
                'clarity_project_id' => 'Project ID is required when Microsoft Clarity is enabled.',
            ]);
        }

        $google->fill([
            'enabled' => $googleEnabled,
            'status' => $googleEnabled ? 'configured' : 'idle',
        ]);
        $google->mergeCredentials([
            'measurement_id' => trim((string) ($validated['google_measurement_id'] ?? '')),
            'property_id' => trim((string) ($validated['google_property_id'] ?? '')),
        ]);
        $google->save();

        $clarity->fill([
            'enabled' => $clarityEnabled,
            'status' => $clarityEnabled ? 'configured' : 'idle',
        ]);
        $clarity->mergeCredentials([
            'project_id' => trim((string) ($validated['clarity_project_id'] ?? '')),
        ]);
        $clarity->save();

        app(TrackingScriptRenderer::class)->flushCache();

        $this->audit->log(auth()->id(), 'settings.analytics.updated', null, null, [
            'google_enabled' => $googleEnabled,
            'clarity_enabled' => $clarityEnabled,
        ], $request->ip());

        return redirect()
            ->route('admin.tracking')
            ->with('status', __('Analytics settings saved. Manage all marketing tags under Marketing & Tracking.'));
    }

    public function testAnalyticsConnection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:google_analytics,microsoft_clarity'],
            'google_measurement_id' => ['nullable', 'string', 'max:32'],
            'google_property_id' => ['nullable', 'string', 'max:32'],
            'clarity_project_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validated['provider'] === AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS) {
            $measurementId = trim((string) ($validated['google_measurement_id'] ?? ''));
            if ($measurementId === '') {
                $measurementId = (string) (AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_GOOGLE_ANALYTICS)
                    ->credential('measurement_id') ?? '');
            }

            $result = $this->googleAnalytics->connectionTestFromInput([
                'measurement_id' => $measurementId,
                'property_id' => trim((string) ($validated['google_property_id'] ?? '')),
            ]);
        } else {
            $projectId = trim((string) ($validated['clarity_project_id'] ?? ''));
            if ($projectId === '') {
                $projectId = (string) (AnalyticsProvider::forProvider(AnalyticsProvider::PROVIDER_MICROSOFT_CLARITY)
                    ->credential('project_id') ?? '');
            }

            $result = $this->clarity->connectionTestFromInput([
                'project_id' => $projectId,
            ]);
        }

        $provider = IntegrationProvider::forProvider($validated['provider']);
        if ($result['ok']) {
            $provider->recordSuccess();
        } else {
            $provider->recordFailure($result['message']);
        }

        $this->audit->log(auth()->id(), 'settings.analytics.connection_test', null, null, [
            'provider' => $validated['provider'],
            'ok' => $result['ok'],
        ], $request->ip());

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'analytics_connection' => $result['message'],
            ]);
        }

        return back()->with('status', $result['message']);
    }

    public function updateGoogleIdentity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_identity_enabled' => ['nullable', 'boolean'],
            'google_identity_client_id' => ['nullable', 'string', 'max:255'],
            'google_identity_client_secret' => ['nullable', 'string', 'max:255'],
            'google_identity_one_tap_enabled' => ['nullable', 'boolean'],
            'google_identity_auto_select_enabled' => ['nullable', 'boolean'],
            'google_identity_one_tap_show_home' => ['nullable', 'boolean'],
            'google_identity_one_tap_show_login' => ['nullable', 'boolean'],
            'google_identity_one_tap_show_register' => ['nullable', 'boolean'],
            'google_identity_one_tap_disable_after_dismiss' => ['nullable', 'boolean'],
            'google_identity_one_tap_prompt_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $enabled = $request->boolean('google_identity_enabled');
        $clientId = trim((string) ($validated['google_identity_client_id'] ?? ''));

        if ($enabled && $clientId === '') {
            return back()->withInput()->withErrors([
                'google_identity_client_id' => 'Client ID is required when Google Sign-In is enabled.',
            ]);
        }

        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $row->enabled = $enabled;

        $credentials = ['client_id' => $clientId];
        $secret = trim((string) ($validated['google_identity_client_secret'] ?? ''));
        if ($secret !== '') {
            $credentials['client_secret'] = $secret;
        }
        $row->mergeCredentials($credentials);

        $row->meta = array_merge($row->meta ?? [], [
            'one_tap_enabled' => $request->boolean('google_identity_one_tap_enabled'),
            'auto_select_enabled' => $request->boolean('google_identity_auto_select_enabled'),
            'one_tap_show_home' => $request->boolean('google_identity_one_tap_show_home'),
            'one_tap_show_login' => $request->boolean('google_identity_one_tap_show_login'),
            'one_tap_show_register' => $request->boolean('google_identity_one_tap_show_register'),
            'one_tap_disable_after_dismiss' => $request->boolean('google_identity_one_tap_disable_after_dismiss'),
            'one_tap_prompt_cooldown_hours' => max(1, (int) ($validated['google_identity_one_tap_prompt_cooldown_hours'] ?? 24)),
        ]);

        $row->status = $enabled && $clientId !== '' ? 'connected' : 'idle';
        $row->save();

        $this->audit->log(auth()->id(), 'settings.google_identity.updated', $row, null, [
            'enabled' => $enabled,
            'one_tap_enabled' => (bool) ($row->meta['one_tap_enabled'] ?? false),
        ], $request->ip());

        return back()->with('status', __('Google Identity settings saved.'));
    }

    public function testGoogleIdentity(Request $request): RedirectResponse|JsonResponse
    {
        $row = IntegrationProvider::forProvider(IntegrationProvider::GOOGLE_IDENTITY);
        $clientId = trim((string) $request->input('google_identity_client_id', $row->credential('client_id', '')));
        $wantsJson = $request->expectsJson() || $request->ajax();

        $result = app(\App\Services\Auth\Identity\GoogleIdTokenVerifier::class)->testConfiguration($clientId);

        if ($result['ok']) {
            $row->recordSuccess();
        } else {
            $row->recordFailure($result['message']);
        }

        $this->audit->log(auth()->id(), 'settings.google_identity.connection_test', $row, null, [
            'ok' => $result['ok'],
        ], $request->ip());

        if (! $result['ok']) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $result['message'],
                    'errors' => ['google_identity_test' => [$result['message']]],
                ], 422);
            }

            return back()->withInput()->withErrors([
                'google_identity_test' => $result['message'],
            ]);
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'message' => $result['message'],
            ]);
        }

        return back()->with('status', $result['message']);
    }

    public function updateManualBankTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'manual_bank_transfer_enabled' => ['nullable', 'boolean'],
            'manual_bank_transfer_bank_name' => ['nullable', 'string', 'max:120'],
            'manual_bank_transfer_account_number' => ['nullable', 'string', 'max:40'],
            'manual_bank_transfer_account_name' => ['nullable', 'string', 'max:120'],
            'manual_bank_transfer_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $enabled = $request->boolean('manual_bank_transfer_enabled');

        if ($enabled) {
            foreach ([
                'manual_bank_transfer_bank_name' => 'Bank name',
                'manual_bank_transfer_account_number' => 'Account number',
                'manual_bank_transfer_account_name' => 'Account name',
            ] as $field => $label) {
                if (blank($validated[$field] ?? null)) {
                    return back()->withInput()->withErrors([
                        $field => $label.' is required when manual bank transfer is enabled.',
                    ]);
                }
            }
        }

        SystemSetting::set('manual_bank_transfer_enabled', $enabled);
        SystemSetting::set('manual_bank_transfer_bank_name', $validated['manual_bank_transfer_bank_name'] ?? '');
        SystemSetting::set('manual_bank_transfer_account_number', $validated['manual_bank_transfer_account_number'] ?? '');
        SystemSetting::set('manual_bank_transfer_account_name', $validated['manual_bank_transfer_account_name'] ?? '');
        SystemSetting::set('manual_bank_transfer_instructions', $validated['manual_bank_transfer_instructions'] ?? '');

        $this->audit->log(auth()->id(), 'settings.manual_bank_transfer.updated', null, null, [
            'enabled' => $enabled,
        ], $request->ip());

        return back()->with('status', __('Manual bank transfer settings saved.'));
    }

    public function updateMonnify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monnify_enabled' => ['nullable', 'boolean'],
            'monnify_api_key' => ['nullable', 'string', 'max:255'],
            'monnify_secret_key' => ['nullable', 'string', 'max:255'],
            'monnify_contract_code' => ['nullable', 'string', 'max:100'],
            'monnify_wallet_account_number' => ['nullable', 'string', 'max:30'],
            'monnify_environment' => ['nullable', 'in:sandbox,live'],
            'monnify_reserved_accounts_without_kyc' => ['nullable', 'boolean'],
            'monnify_webhook_allowed_ips' => ['nullable', 'string', 'max:500'],
        ]);

        $enabled = $request->boolean('monnify_enabled');
        $row = IntegrationProvider::forProvider(IntegrationProvider::MONNIFY);
        $row->enabled = $enabled;

        $credentials = [];
        foreach ([
            'api_key' => 'monnify_api_key',
            'secret_key' => 'monnify_secret_key',
            'contract_code' => 'monnify_contract_code',
            'wallet_account_number' => 'monnify_wallet_account_number',
        ] as $credKey => $input) {
            $value = trim((string) ($validated[$input] ?? ''));
            if ($value !== '') {
                $credentials[$credKey] = $value;
            }
        }
        if ($credentials !== []) {
            $row->mergeCredentials($credentials);
        }

        $allowedIps = collect(preg_split('/[\s,]+/', (string) ($validated['monnify_webhook_allowed_ips'] ?? '')))
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();

        $row->meta = array_merge($row->meta ?? [], [
            'environment' => $validated['monnify_environment'] ?? ($row->meta['environment'] ?? 'sandbox'),
            'reserved_accounts_without_kyc' => $request->boolean('monnify_reserved_accounts_without_kyc'),
            // Empty input clears override so runtime default (35.242.133.146) applies.
            'webhook_allowed_ips' => $allowedIps !== [] ? $allowedIps : null,
        ]);

        $configured = filled($row->credential('api_key'))
            && filled($row->credential('secret_key'))
            && filled($row->credential('contract_code'));

        $row->status = $enabled && $configured ? 'connected' : 'idle';
        $row->save();

        app(\App\Modules\Wallet\Payments\Monnify\MonnifyClient::class)->clearTokenCache();

        $this->audit->log(auth()->id(), 'settings.monnify.updated', $row, null, [
            'enabled' => $enabled,
            'environment' => $row->meta['environment'] ?? 'sandbox',
        ], $request->ip());

        return back()->with('status', __('Monnify settings saved.'));
    }

    public function testMonnify(Request $request): RedirectResponse|JsonResponse
    {
        $client = app(\App\Modules\Wallet\Payments\Monnify\MonnifyClient::class);
        $row = IntegrationProvider::forProvider(IntegrationProvider::MONNIFY);
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $client->clearTokenCache();
            $token = $client->accessToken();
            $ok = filled($token);
            $message = 'Monnify login succeeded.';
            if ($ok && filled($client->walletAccountNumber())) {
                try {
                    $bal = app(\App\Modules\Wallet\Payments\Monnify\MonnifyPaymentRail::class)->getMerchantWalletBalance();
                    $message .= ' Merchant wallet balance: ₦'.number_format($bal, 2);
                } catch (Throwable $e) {
                    $message .= ' (Wallet balance check: '.$e->getMessage().')';
                }
            }
            $row->recordSuccess();
            $this->audit->log(auth()->id(), 'settings.monnify.connection_test', $row, null, ['ok' => true], $request->ip());

            if ($wantsJson) {
                return response()->json(['ok' => true, 'message' => $message]);
            }

            return back()->with('status', $message);
        } catch (Throwable $e) {
            $row->recordFailure($e->getMessage());
            $this->audit->log(auth()->id(), 'settings.monnify.connection_test', $row, null, ['ok' => false], $request->ip());

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'errors' => ['monnify_test' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors([
                'monnify_test' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, EmailDeliveryAttempt>
     */
    private function safeRecentEmailFailures(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('email_delivery_attempts')) {
            return collect();
        }

        try {
            return EmailDeliveryAttempt::query()
                ->where('success', false)
                ->latest('created_at')
                ->limit(10)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncBrandingMediaUsages(array $validated): void
    {
        MediaUsage::query()
            ->where('usable_type', 'site_branding')
            ->where('usable_id', 1)
            ->delete();

        foreach ([
            'favicon' => $validated['favicon_media_id'] ?? null,
            'logo_light' => $validated['logo_light_media_id'] ?? null,
            'logo_dark' => $validated['logo_dark_media_id'] ?? null,
        ] as $field => $mediaId) {
            if (! $mediaId) {
                continue;
            }
            MediaUsage::query()->create([
                'media_asset_id' => (int) $mediaId,
                'usable_type' => 'site_branding',
                'usable_id' => 1,
                'field' => $field,
            ]);
        }
    }
}
