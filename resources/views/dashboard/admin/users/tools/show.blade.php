@extends('layouts.dashboard-admin')

@section('title', $tool->resolvedDisplayName())

@section('content')
@php
    use App\Enums\UserToolStatus;

    $isPendingSetup = $tool->status === UserToolStatus::PendingSetup;
    $integration = $tool->integration;
    $fresh = $freshCredentials ?? null;
    $webhookUrl = $integration
        ? url('/webhooks/site-integrations/'.$integration->integration_id)
        : ($fresh['webhook_url'] ?? null);

    $credentialRows = [];
    if ($integration || $fresh) {
        $credentialRows = [
            ['label' => 'Integration ID', 'value' => $fresh['integration_id'] ?? $integration?->integration_id ?? '', 'secret' => false],
            ['label' => 'Client ID', 'value' => $fresh['client_id'] ?? $integration?->client_id ?? '', 'secret' => false],
            ['label' => 'Client Secret', 'value' => $fresh['client_secret'] ?? $integration?->client_secret ?? '', 'secret' => true],
            ['label' => 'Webhook Secret', 'value' => $fresh['webhook_secret'] ?? $integration?->webhook_secret ?? '', 'secret' => true],
            ['label' => 'Webhook URL', 'value' => $fresh['webhook_url'] ?? $webhookUrl ?? '', 'secret' => false],
        ];
        if ($tool->site_url) {
            $credentialRows[] = ['label' => 'Site URL', 'value' => $tool->site_url, 'secret' => false];
        }
    }

    $ownedDocsSubtitle = 'Give these values to the merchant developer for this customer-owned site. '
        .'<a href="'.route('developers.integrations.show', ['path' => 'MERCHANT-GUIDE']).'" class="text-primary hover:underline" target="_blank" rel="noopener">Integration docs</a>';
@endphp
<x-layout.page
    title="{{ $tool->resolvedDisplayName() }}"
    subtitle="{{ $tool->product?->title ?? 'Owned website tool' }} · {{ $user->name }}"
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Users', route('admin.users')],
        [$user->name, route('admin.users.show', $user)],
        ['Tools', route('admin.users.tools', $user)],
        [$tool->resolvedDisplayName(), null],
    ]"
>
    <x-slot:actions>
        @if (! $isPendingSetup && $tool->site_url)
            <form method="POST" action="{{ route('admin.users.tools.check', [$user, $tool]) }}">
                @csrf
                <x-dashboard.button type="submit" variant="secondary" size="sm">Check connection</x-dashboard.button>
            </form>
        @endif
        @if (! $isPendingSetup && $integration)
            <form method="POST" action="{{ route('admin.users.tools.rotate', [$user, $tool]) }}" onsubmit="return confirm('Rotate API credentials? The merchant site env must be updated.');">
                @csrf
                <x-dashboard.button type="submit" variant="secondary" size="sm">Rotate keys</x-dashboard.button>
            </form>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
    @endif
    @if (session('warning'))
        <x-dashboard.alert type="warning" class="mb-4">{{ session('warning') }}</x-dashboard.alert>
    @endif
    @if (session('error'))
        <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
    @endif
    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">Please fix the highlighted fields below.</x-dashboard.alert>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
        <x-dashboard.badge :status="$tool->status->value" />
        @if ($tool->expires_at)
            <span class="text-text-muted">Expires {{ $tool->expires_at->format('j M Y') }}</span>
        @endif
        @if ($integration)
            <span class="text-text-muted">Connection: <span class="font-medium text-text-secondary">{{ $integration->connection_status ?? 'unchecked' }}</span></span>
        @endif
        <span class="font-mono text-xs text-text-muted">{{ $tool->public_id }}</span>
    </div>

    @if ($tool->domainConnection)
        @php $dc = $tool->domainConnection; @endphp
        <x-dashboard.card class="mb-6 space-y-3">
            <h3 class="text-sm font-semibold text-text-primary">Domain connection</h3>
            <dl class="grid gap-3 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-text-muted">Domain</dt>
                    <dd class="font-mono font-medium text-text-primary break-all">{{ $dc->fqdn }}</dd>
                </div>
                <div>
                    <dt class="text-text-muted">Status</dt>
                    <dd><x-dashboard.badge :status="$dc->verification_status" /></dd>
                </div>
                <div>
                    <dt class="text-text-muted">Verified at</dt>
                    <dd class="font-medium text-text-primary">{{ $dc->verified_at?->format('j M Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
            @if ($dc->verification_status !== 'verified')
                <div class="flex flex-col gap-3 rounded-lg border border-amber-300/40 bg-amber-50/50 px-4 py-3 sm:flex-row sm:items-center">
                    <p class="flex-1 text-xs text-text-secondary">
                        DNS hasn't been verified yet. If this customer uses external hosting and doesn't need to change nameservers, you can approve manually.
                    </p>
                    <form method="POST" action="{{ route('admin.users.domain-connections.approve', [$user, $dc]) }}">
                        @csrf
                        <x-dashboard.button type="submit" size="sm" variant="secondary">Approve domain</x-dashboard.button>
                    </form>
                </div>
            @endif
        </x-dashboard.card>
    @endif

    @if ($isPendingSetup)
        @php
            $prefillSiteUrl = old('site_url', $suggestedSiteUrl ?? $tool->suggestedSiteUrl());
        @endphp
        <x-dashboard.card class="mb-6">
            <h3 class="mb-4 text-sm font-semibold text-text-primary">Initial setup</h3>
            <p class="mb-4 text-sm text-text-secondary">Configure this purchased website tool and generate unique owned credentials (never demo credentials).</p>
            <form method="POST" action="{{ route('admin.users.tools.setup', [$user, $tool]) }}" class="space-y-4">
                @csrf
                <x-dashboard.input name="site_url" label="Website URL" type="url" :value="$prefillSiteUrl" required />
                @if ($tool->connectedDomainFqdn())
                    <p class="text-xs text-text-muted">Prefills from the domain connected at purchase (<span class="font-mono">{{ $tool->connectedDomainFqdn() }}</span>). You can clear or change it.</p>
                @endif
                <x-dashboard.input name="admin_login_url" label="Admin login URL" type="url" :value="old('admin_login_url', $tool->admin_login_url)" required />
                <x-dashboard.input name="admin_email" label="Admin email" type="email" :value="old('admin_email', $tool->admin_email)" required />
                <x-dashboard.input name="admin_password" label="Admin password" type="text" required autocomplete="off" />

                <div class="rounded-xl border border-border-default bg-muted/30 p-4 space-y-4">
                    <div>
                        <h4 class="text-sm font-semibold text-text-primary">Livechat logins</h4>
                        <p class="mt-1 text-xs text-text-muted">Optional. Customer can view these on My Tools and copy the livechat password.</p>
                    </div>
                    <x-dashboard.input name="livechat_name" label="Livechat name" type="text" :value="old('livechat_name', $tool->livechat_name)" />
                    <x-dashboard.input name="livechat_url" label="Livechat link" type="text" :value="old('livechat_url', $tool->livechat_url)" placeholder="https://…" />
                    <x-dashboard.input name="livechat_email" label="Livechat email" type="email" :value="old('livechat_email', $tool->livechat_email)" />
                    <x-dashboard.input name="livechat_password" label="Livechat password" type="text" autocomplete="off" />
                </div>

                <p class="text-xs text-text-muted">Saving starts the subscription clock and generates provisioning API keys for the merchant site.</p>
                <x-dashboard.button type="submit">Save & generate keys</x-dashboard.button>
            </form>
        </x-dashboard.card>
    @else
        @if (! $integration)
            <x-dashboard.alert type="warning" class="mb-6">
                This tool is active but provisioning credentials are missing. Re-run setup or contact engineering — the merchant cannot install integration keys until this is fixed.
            </x-dashboard.alert>
        @elseif ($credentialRows !== [])
            <x-dashboard.integration-credentials-card
                class="mb-6"
                title="Owned tool credentials"
                :subtitle="$ownedDocsSubtitle"
                :credential-rows="$credentialRows"
                :badge-status="$tool->status->value"
                :show-setup-steps="($integration?->connection_status ?? 'unchecked') !== 'ok'"
                :connection-status="$fresh['connection_status'] ?? $integration?->connection_status ?? 'unchecked'"
                :connection-message="$fresh['connection_message'] ?? $integration?->last_error"
                :connection-ok="$fresh['connection_ok'] ?? null"
            />
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <x-dashboard.card>
                <h3 class="mb-4 text-sm font-semibold text-text-primary">Tool settings</h3>
                <form method="POST" action="{{ route('admin.users.tools.reconfigure', [$user, $tool]) }}" class="space-y-4">
                    @csrf
                    <x-dashboard.input name="site_url" label="Website URL" type="url" :value="old('site_url', $tool->site_url)" required />
                    <x-dashboard.input name="admin_login_url" label="Admin login URL" type="url" :value="old('admin_login_url', $tool->admin_login_url)" required />
                    <x-dashboard.input name="admin_email" label="Admin email" type="email" :value="old('admin_email', $tool->admin_email)" required />
                    <x-dashboard.input name="admin_password" label="Admin password" type="text" autocomplete="off" />
                    <p class="text-xs text-text-muted">Reconfigure updates URLs and admin identity. It does <strong>not</strong> extend the paid subscription. Leave password blank to keep the current one.</p>
                    <x-dashboard.button type="submit">Save reconfiguration</x-dashboard.button>
                </form>
            </x-dashboard.card>

            <x-dashboard.card>
                <h3 class="mb-4 text-sm font-semibold text-text-primary">Subscription expiry</h3>
                <form method="POST" action="{{ route('admin.users.tools.expiry', [$user, $tool]) }}" class="space-y-4">
                    @csrf
                    <x-dashboard.input
                        name="expires_at"
                        label="Expires on"
                        type="date"
                        :value="old('expires_at', $tool->expires_at?->format('Y-m-d'))"
                        required
                    />
                    <p class="text-xs text-text-muted">Adjust the paid window for this tool. Updates merchant subscription sync when the site is connected.</p>
                    <x-dashboard.button type="submit" variant="secondary">Update expiry</x-dashboard.button>
                </form>

                @php
                    $siteLive = $tool->isSubscriptionLive();
                    $canResumeShutdown = $tool->canResumeShutdownWithStoredExpiry();
                @endphp
                <div class="mt-6 border-t border-border-default pt-4">
                    @if ($siteLive)
                        <form
                            method="POST"
                            action="{{ route('admin.users.tools.shutdown', [$user, $tool]) }}"
                            onsubmit="return confirm('This immediately deactivates the connected external website (same as subscription expiry for the merchant). Customers will see the site as expired/shutdown. The original expiry date is kept so Enable can restore it. Continue?');"
                        >
                            @csrf
                            <x-dashboard.button type="submit" variant="danger">Shutdown Site</x-dashboard.button>
                        </form>
                        <p class="mt-2 text-xs text-text-muted">Immediately deactivates the external website via subscription sync. Does not rotate API keys. The current expiry is saved — <strong>Enable</strong> restores that date if it is still in the future.</p>
                    @elseif ($canResumeShutdown)
                        <form
                            method="POST"
                            action="{{ route('admin.users.tools.enable', [$user, $tool]) }}"
                            onsubmit="return confirm('Reopen this website and restore the previous expiry ({{ $tool->shutdown_resume_expires_at->format('j M Y') }})?');"
                        >
                            @csrf
                            <x-dashboard.button type="submit" variant="success">Enable</x-dashboard.button>
                        </form>
                        <p class="mt-2 text-xs text-text-muted">Admin shutdown paused this site. Enabling restores the previous expiry <strong>{{ $tool->shutdown_resume_expires_at->format('j M Y') }}</strong> — no new date needed.</p>
                    @else
                        <form method="POST" action="{{ route('admin.users.tools.enable', [$user, $tool]) }}" class="space-y-3" onsubmit="return confirm('Reopen this external website as active with the new expiry date? The merchant will be notified via subscription sync.');">
                            @csrf
                            <x-dashboard.input
                                name="enable_expires_at"
                                label="New expiry date"
                                type="date"
                                :value="old('enable_expires_at')"
                                :min="now()->addDay()->format('Y-m-d')"
                                required
                            />
                            <x-dashboard.button type="submit" variant="success">Enable</x-dashboard.button>
                        </form>
                        <p class="mt-2 text-xs text-text-muted">This subscription ended on its own (or the saved expiry has already passed). Choose a new future expiry to reopen the site.</p>
                    @endif
                </div>
            </x-dashboard.card>

            <x-dashboard.card class="lg:col-span-2">
                <h3 class="mb-4 text-sm font-semibold text-text-primary">Livechat logins</h3>
                <p class="mb-4 text-xs text-text-muted">Visible on the customer’s My Tools page. Password is never shown in plain text — they can copy it like the site password.</p>
                <form method="POST" action="{{ route('admin.users.tools.livechat', [$user, $tool]) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-dashboard.input name="livechat_name" label="Livechat name" type="text" :value="old('livechat_name', $tool->livechat_name)" />
                    <x-dashboard.input name="livechat_url" label="Livechat link" type="text" :value="old('livechat_url', $tool->livechat_url)" placeholder="https://…" />
                    <x-dashboard.input name="livechat_email" label="Livechat email" type="email" :value="old('livechat_email', $tool->livechat_email)" />
                    <x-dashboard.input name="livechat_password" label="Livechat password" type="text" autocomplete="off" hint="Leave blank to keep the current password." />
                    <div class="sm:col-span-2">
                        <x-dashboard.button type="submit" variant="secondary">Save livechat logins</x-dashboard.button>
                    </div>
                </form>
            </x-dashboard.card>

            <x-dashboard.card class="lg:col-span-2">
                <h3 class="mb-3 text-sm font-semibold">Connection logs</h3>
                <div class="space-y-2">
                    @forelse ($logs as $log)
                        @php
                            $logIsPendingMerchant = ! $log->ok && (
                                str_contains($log->message, 'unknown_integration')
                                || (($log->payload_summary['error'] ?? null) === 'unknown_integration')
                            );
                        @endphp
                        <div class="rounded-lg border border-border-default px-3 py-2 text-xs">
                            <div class="flex justify-between gap-2">
                                <span class="{{ $log->ok ? 'text-success' : ($logIsPendingMerchant ? 'text-amber-600' : 'text-danger') }}">
                                    {{ $log->ok ? 'OK' : ($logIsPendingMerchant ? 'Pending merchant' : 'Fail') }}
                                </span>
                                <span class="text-text-muted">{{ $log->created_at->format('j M Y H:i') }}</span>
                            </div>
                            <p class="mt-1 break-words text-text-secondary">{{ $log->message }}</p>
                            @if ($log->http_status)
                                <p class="mt-1 text-[11px] text-text-muted">HTTP {{ $log->http_status }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-text-muted">No checks yet. Install credentials on the merchant site, then click <strong>Check connection</strong>.</p>
                    @endforelse
                </div>
            </x-dashboard.card>
        </div>
    @endif
</x-layout.page>
@endsection
