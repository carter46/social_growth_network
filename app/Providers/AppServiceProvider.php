<?php

namespace App\Providers;

use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\Contracts\Analytics\HeatmapProviderInterface;
use App\Contracts\Analytics\MarketingAnalyticsProviderInterface;
use App\Events\OrderCompleted;
use App\Events\OrderManualBankTransferPaymentFailed;
use App\Events\OrderManualBankTransferSubmitted;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Events\UserRegistered;
use App\Events\UserVerified;
use App\Events\WalletFunded;
use App\Events\WalletFundingSubmitted;
use App\Events\WalletWithdrawalCompleted;
use App\Events\WithdrawalAwaitingProviderAuthorization;
use App\Events\WithdrawalPayoutFailed;
use App\Events\WithdrawalRequested;
use App\Listeners\CreateUserToolsFromOrder;
use App\Listeners\FulfillDomainRegistrations;
use App\Listeners\DispatchMarketingAnalytics;
use App\Listeners\NotifyAdmins;
use App\Listeners\NotifyUsersFromEvent;
use App\Listeners\RecordProductActivity;
use App\Listeners\WriteAuditLogFromEvent;
use App\Modules\Admin\Services\AuditLogService;
use App\Modules\Wallet\Contracts\WalletProviderInterface;
use App\Modules\Wallet\Payments\PayoutGateway;
use App\Modules\Wallet\Providers\ManualProvider;
use App\Modules\Wallet\Services\WalletProvisioningService;
use App\Modules\Wallet\Services\WalletService;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\InternalBusinessProvider;
use App\Services\Analytics\ProductAnalyticsProvider;
use App\Services\Analytics\Providers\GoogleAnalyticsProvider;
use App\Services\Analytics\Providers\MicrosoftClarityProvider;
use App\Services\Analytics\UserActivityRecorder;
use App\Services\ThemeManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private array $analyticsEvents = [
        UserRegistered::class,
        UserVerified::class,
        WalletFunded::class,
        WalletFundingSubmitted::class,
        WalletWithdrawalCompleted::class,
        WithdrawalRequested::class,
        WithdrawalAwaitingProviderAuthorization::class,
        WithdrawalPayoutFailed::class,
        OrderCompleted::class,
        OrderManualBankTransferSubmitted::class,
        OrderManualBankTransferPaymentFailed::class,
        TicketOpened::class,
        TicketReplied::class,
    ];

    public function register(): void
    {
        $this->app->singleton(\App\Support\Demo\DemoBatchTracker::class);
        $this->app->bind(WalletProviderInterface::class, ManualProvider::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(\App\Modules\Wallet\Payments\Monnify\MonnifyClient::class);
        $this->app->singleton(\App\Modules\Wallet\Payments\Monnify\MonnifyPaymentRail::class);
        $this->app->bind(
            \App\Modules\Wallet\Payments\Contracts\PaymentRailInterface::class,
            \App\Modules\Wallet\Payments\Monnify\MonnifyPaymentRail::class
        );
        $this->app->bind(
            PayoutGateway::class,
            fn ($app) => PayoutGateway::from($app->make(\App\Modules\Wallet\Payments\Contracts\PaymentRailInterface::class))
        );
        $this->app->singleton(\App\Services\Domains\Providers\NameCom\NameComClient::class);
        $this->app->singleton(\App\Services\Domains\Providers\NameCom\NameComProvider::class);
        $this->app->singleton(\App\Services\Domains\Providers\DomainNameApi\DomainNameApiClient::class);
        $this->app->singleton(\App\Services\Domains\Providers\DomainNameApi\DomainNameApiProvider::class);
        $this->app->singleton(\App\Services\Domains\DomainProviderManager::class);
        $this->app->singleton(\App\Services\Domains\PlatformDomainPricingPolicy::class);
        $this->app->singleton(\App\Services\Domains\DomainQuoteService::class);
        $this->app->singleton(\App\Services\Domains\DomainDnsLookupService::class);
        $this->app->singleton(\App\Services\Domains\DomainConnectionService::class);
        $this->app->singleton(\App\Services\Domains\DomainCheckoutValidator::class);
        $this->app->singleton(\App\Services\Domains\DomainRegistrationFulfillmentService::class);
        $this->app->singleton(\App\Services\Domains\DomainNameserverService::class);
        $this->app->singleton(\App\Services\Domains\DomainAuditLogger::class);
        $this->app->singleton(\App\Services\Domains\DomainCacheInvalidator::class);
        $this->app->singleton(\App\Services\Domains\DomainProviderConfigValidator::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(\App\Services\Notifications\NotificationDispatcher::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(\App\Services\Media\MediaPathService::class);
        $this->app->singleton(\App\Services\Branding\SiteBrandingRepository::class);
        $this->app->singleton(\App\Services\Communications\Contact\PlatformContactRepository::class);
        $this->app->singleton(\App\Services\Communications\Social\SocialLinkRepository::class);
        $this->app->singleton(\App\Services\Communications\LiveChat\LiveChatManager::class);
        $this->app->singleton(\App\Services\Communications\Email\EmailDeliveryLogger::class);
        $this->app->singleton(\App\Services\Communications\Email\EmailService::class);
        $this->app->singleton(\App\Services\Communications\Email\Providers\BrevoApiProvider::class);
        $this->app->singleton(\App\Services\Communications\Email\Providers\LaravelMailProvider::class);

        $this->app->singleton(UserActivityRecorder::class);
        $this->app->singleton(GoogleAnalyticsProvider::class);
        $this->app->singleton(MicrosoftClarityProvider::class);
        $this->app->singleton(AnalyticsTracker::class);
        $this->app->singleton(InternalBusinessProvider::class);
        $this->app->singleton(ProductAnalyticsProvider::class);
        $this->app->singleton(AnalyticsService::class);

        $this->app->bind(MarketingAnalyticsProviderInterface::class, GoogleAnalyticsProvider::class);
        $this->app->bind(HeatmapProviderInterface::class, MicrosoftClarityProvider::class);
        $this->app->bind(AnalyticsServiceInterface::class, AnalyticsService::class);
    }

    public function boot(): void
    {
        $this->registerAnalyticsListeners();
        Event::listen(OrderCompleted::class, CreateUserToolsFromOrder::class);
        Event::listen(OrderCompleted::class, FulfillDomainRegistrations::class);
        $this->applySiteBrandingConfig();
        $this->configureProductionUrls();

        View::composer(['layouts.dashboard-user', 'layouts.dashboard-admin'], function ($view) {
            /** @var ThemeManager $themes */
            $themes = app(ThemeManager::class);
            $user = auth()->user();
            $preference = $themes->preferenceFor($user);
            $resolved = $themes->resolve($preference, ThemeManager::PREFERENCE_LIGHT);
            $payload = $themes->payloadFor($user, ThemeManager::PREFERENCE_LIGHT);

            $impersonatorName = null;
            if (session('impersonating') && session('impersonator_id')) {
                $impersonatorName = \App\Models\User::query()
                    ->whereKey(session('impersonator_id'))
                    ->value('name');
            }

            $branding = app(\App\Services\Branding\SiteBrandingRepository::class)->all();
            $favicon = $branding['favicon_media_id']
                ? media_url_from_id($branding['favicon_media_id'], null, 'original')
                : null;

            $googleIdentityConfig = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('integration_providers')) {
                    $googleIdentityConfig = \App\Services\Auth\Identity\GoogleIdentityProvider::configForFrontend();
                }
            } catch (\Throwable) {
                $googleIdentityConfig = [];
            }

            $view->with([
                'dashboardThemePreference' => $preference,
                'dashboardThemeResolved' => $resolved,
                'dashboardThemePayload' => $payload,
                'impersonatorName' => $impersonatorName,
                'siteName' => $branding['site_name'],
                'siteBranding' => $branding,
                'faviconUrl' => $favicon,
                'googleIdentityConfig' => $googleIdentityConfig,
            ]);
        });

        View::composer(['layouts.marketing', 'layouts.auth', 'components.layouts.auth', 'auth.login', 'auth.register', 'pages.home'], function ($view) {
            $branding = app(\App\Services\Branding\SiteBrandingRepository::class)->all();
            $footer = \App\ViewModels\FooterViewModel::make();
            $favicon = $branding['favicon_media_id']
                ? media_url_from_id($branding['favicon_media_id'], null, 'original')
                : null;

            $googleIdentityConfig = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('integration_providers')) {
                    $googleIdentityConfig = \App\Services\Auth\Identity\GoogleIdentityProvider::configForFrontend();
                }
            } catch (\Throwable) {
                $googleIdentityConfig = [];
            }

            $view->with([
                'siteName' => $branding['site_name'],
                'siteHeading' => $branding['heading'],
                'siteTagline' => $branding['tagline'],
                'siteBranding' => $branding,
                'footer' => $footer,
                'faviconUrl' => $favicon,
                'googleIdentityConfig' => $googleIdentityConfig,
            ]);
        });
    }

    private function configureProductionUrls(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $url = (string) config('app.url');
        if ($url !== '' && str_starts_with($url, 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    private function applySiteBrandingConfig(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                return;
            }
            $branding = app(\App\Services\Branding\SiteBrandingRepository::class)->all();
            $name = $branding['site_name'] ?? '';
            if ($name !== '') {
                config(['app.name' => $name]);
                config([
                    'pwa.manifest.name' => $name,
                    'pwa.manifest.short_name' => ($branding['site_short_name'] ?? '') !== ''
                        ? $branding['site_short_name']
                        : $name,
                    'pwa.manifest.description' => ($branding['meta_description'] ?? '') !== ''
                        ? $branding['meta_description']
                        : config('pwa.manifest.description'),
                ]);
            }

            // PWA icon files are regenerated via admin Site Settings save or `php artisan branding:sync-pwa`.
            // Do not sync here on every request — GD + disk writes can timeout shared hosting.
        } catch (\Throwable) {
            // Database may be unavailable during early boot / package discovery.
        }
    }

    private function registerAnalyticsListeners(): void
    {
        $listeners = [
            [RecordProductActivity::class, 'handle'],
            [DispatchMarketingAnalytics::class, 'handle'],
            [WriteAuditLogFromEvent::class, 'handle'],
            [NotifyAdmins::class, 'handle'],
            [NotifyUsersFromEvent::class, 'handle'],
        ];

        foreach ($this->analyticsEvents as $event) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
