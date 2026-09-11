@extends('layouts.dashboard-user')

@section('title', $tool->resolvedDisplayName())

@section('content')
@php
    use App\Enums\UserToolStatus;

    $product = $tool->product;
    $isPending = $tool->status === UserToolStatus::PendingSetup;
    $isExpired = $tool->status === UserToolStatus::Expired;
    $isActive = $tool->status === UserToolStatus::Active;
    $heroUrl = $product
        ? (media_url($product->heroMedia, $product->hero_image, 'large')
            ?? media_url($product->heroMedia, $product->hero_image, 'medium'))
        : null;
    $paidAmount = $tool->orderItem?->line_total
        ?? $tool->order?->total_amount
        ?? $tool->variant?->price;
    $planLabel = $tool->variant?->displayLabel() ?? ($tool->duration_months ? $tool->duration_months.' months' : '—');
    $pendingLabel = 'Pending';
@endphp
<x-layout.page
    title="{{ $tool->resolvedDisplayName() }}"
    width="full"
    :breadcrumb="[
        ['Dashboard', route('dashboard')],
        ['My Tools', route('dashboard.my-tools')],
        [$tool->resolvedDisplayName(), null],
    ]"
>
    <x-slot:actions>
        @if ($isActive && $tool->isExpiringSoon())
            <x-dashboard.button :href="route('dashboard.services.checkout', ['slug' => $tool->product->slug, 'renew' => $tool->public_id])" size="sm">Renew</x-dashboard.button>
        @elseif ($isExpired && $tool->product)
            <x-dashboard.button :href="route('dashboard.services.checkout', ['slug' => $tool->product->slug, 'renew' => $tool->public_id])" size="sm">Renew</x-dashboard.button>
        @endif
    </x-slot:actions>

    <div class="min-w-0 space-y-6 overflow-x-hidden">
        <x-dashboard.card>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,240px)_1fr] lg:gap-6">
                <div class="min-w-0 overflow-hidden rounded-xl border border-border-subtle bg-muted/40 p-3 sm:p-4">
                    @if ($heroUrl)
                        <img
                            src="{{ $heroUrl }}"
                            alt="{{ $product?->title ?? $tool->resolvedDisplayName() }}"
                            class="block h-auto max-h-48 w-full max-w-full rounded-lg object-cover lg:max-h-56"
                        >
                    @else
                        <div class="flex min-h-[180px] items-center justify-center rounded-lg bg-gradient-to-br from-primary/20 via-muted to-elevated">
                            <x-ui.icon name="monitor" class="h-12 w-12 text-primary/50" />
                        </div>
                    @endif
                </div>
                <div class="min-w-0 space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-primary">Your service</p>
                        <h2 class="mt-1 text-2xl font-bold text-text-primary">{{ $product?->title ?? $tool->resolvedDisplayName() }}</h2>
                        @if (filled($product?->description))
                            <p class="mt-2 text-sm text-text-secondary">{{ \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 200) }}</p>
                        @endif
                    </div>
                    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div>
                            <dt class="text-text-muted">Plan</dt>
                            <dd class="font-medium text-text-primary">{{ $planLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Amount paid</dt>
                            <dd class="font-medium text-text-primary">
                                @if ($paidAmount !== null)
                                    ₦{{ number_format((float) $paidAmount, 2) }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Purchased</dt>
                            <dd class="font-medium text-text-primary">{{ $tool->purchased_at?->format('j M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Status</dt>
                            <dd><x-dashboard.badge :status="$tool->status->value" /></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-dashboard.card>

        @if ($isPending)
            <p class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-text-secondary">
                Our team is configuring your service. Your admin login details will appear once done.
            </p>
        @endif

        <div class="grid min-w-0 gap-6 lg:grid-cols-2">
            {{-- Website access --}}
            <x-dashboard.card class="min-w-0 space-y-4">
                <h2 class="text-lg font-semibold text-text-primary">Website access</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-text-muted">Site URL</dt>
                        <dd class="mt-1 space-y-2">
                            @if ($tool->site_url)
                                <p class="truncate font-mono text-sm text-text-primary" title="{{ $tool->site_url }}">{{ \Illuminate\Support\Str::limit($tool->site_url, 48) }}</p>
                                <x-dashboard.button :href="$tool->site_url" size="sm" variant="secondary" target="_blank" rel="noopener">
                                    Open Site
                                </x-dashboard.button>
                            @else
                                <span class="text-text-muted">{{ $pendingLabel }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($tool->admin_login_url)
                        <div>
                            <dt class="text-text-muted">Admin login URL</dt>
                            <dd class="mt-1 space-y-2">
                                <p class="truncate font-mono text-sm text-text-primary" title="{{ $tool->admin_login_url }}">{{ \Illuminate\Support\Str::limit($tool->admin_login_url, 48) }}</p>
                                <x-dashboard.button :href="$tool->admin_login_url" size="sm" variant="secondary" target="_blank" rel="noopener">
                                    Open admin login link
                                </x-dashboard.button>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-text-muted">Admin login email</dt>
                        <dd class="font-medium text-text-primary">
                            @if ($tool->admin_email)
                                {{ $tool->admin_email }}
                            @else
                                <span class="text-text-muted">{{ $pendingLabel }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Password</dt>
                        <dd class="font-medium text-text-primary">
                            @if ($tool->admin_password)
                                <span class="text-text-secondary">Saved securely — use Copy password below</span>
                            @else
                                <span class="text-text-muted">{{ $pendingLabel }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! $isPending)
                    <div class="flex flex-wrap gap-2 pt-2">
                        @if ($tool->admin_password)
                            <x-dashboard.button type="button" variant="secondary" size="sm" id="copy-tool-password" data-url="{{ route('dashboard.my-tools.password', $tool) }}">
                                Copy password
                            </x-dashboard.button>
                        @endif
                        @if ($tool->canLaunchAdmin())
                            <form method="POST" action="{{ route('dashboard.my-tools.launch-admin', $tool) }}" target="_blank" rel="noopener" data-no-page-loader>
                                @csrf
                                <x-dashboard.button type="submit" size="sm">Admin Auto Login</x-dashboard.button>
                            </form>
                        @endif
                    </div>
                    <p class="text-xs text-text-muted">Password is never shown on this page. Admin Auto Login opens your site in a new tab and succeeds only after the merchant has installed your owned credentials and your admin account exists on that site.</p>
                @endif
            </x-dashboard.card>

            {{-- Livechat logins --}}
            @if ($tool->hasLivechatDetails())
                <x-dashboard.card class="min-w-0 space-y-4">
                    <h2 class="text-lg font-semibold text-text-primary">Livechat logins</h2>
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-text-muted">Livechat name</dt>
                            <dd class="font-medium text-text-primary">{{ $tool->livechat_name ?: $pendingLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Livechat email</dt>
                            <dd class="font-medium text-text-primary">{{ $tool->livechat_email ?: $pendingLabel }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-text-muted">Livechat</dt>
                            <dd class="mt-1">
                                @if ($tool->livechat_url)
                                    <x-dashboard.button :href="$tool->livechat_url" size="sm" variant="secondary" target="_blank" rel="noopener">
                                        Open livechat
                                    </x-dashboard.button>
                                @else
                                    <span class="text-text-muted">{{ $pendingLabel }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-text-muted">Password</dt>
                            <dd class="font-medium text-text-primary">
                                @if ($tool->livechat_password)
                                    <span class="text-text-secondary">Saved securely — use Copy password below</span>
                                @else
                                    <span class="text-text-muted">{{ $pendingLabel }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($tool->canRevealLivechatPassword())
                        <div class="pt-1">
                            <x-dashboard.button type="button" variant="secondary" size="sm" id="copy-livechat-password" data-url="{{ route('dashboard.my-tools.livechat-password', $tool) }}">
                                Copy livechat password
                            </x-dashboard.button>
                        </div>
                    @endif
                </x-dashboard.card>
            @endif

            {{-- Tutorials --}}
            @if ($tool->hasTutorialDetails())
                @php $productTutorial = $tool->product; @endphp
                <x-dashboard.card class="min-w-0 space-y-4 lg:col-span-2">
                    <h2 class="text-lg font-semibold text-text-primary">Tutorials</h2>
                    @if (filled($productTutorial?->tutorial_description))
                        <p class="text-sm text-text-secondary whitespace-pre-line">{{ $productTutorial->tutorial_description }}</p>
                    @endif
                    @if (filled($productTutorial?->tutorial_url))
                        <x-dashboard.button :href="$productTutorial->tutorial_url" size="sm" variant="secondary" target="_blank" rel="noopener">
                            Watch tutorial
                        </x-dashboard.button>
                    @endif
                </x-dashboard.card>
            @endif

            {{-- Subscription (last) --}}
            <x-dashboard.card class="min-w-0 space-y-3 lg:col-span-2">
                <h2 class="text-lg font-semibold text-text-primary">Subscription</h2>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                    <div>
                        <dt class="text-text-muted">Plan</dt>
                        <dd class="font-medium text-text-primary">{{ $planLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Purchased</dt>
                        <dd class="font-medium text-text-primary">{{ $tool->purchased_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Expires</dt>
                        <dd class="font-medium text-text-primary">
                            @if ($tool->expires_at)
                                {{ $tool->expires_at->format('j M Y') }}
                                @if ($tool->isExpiringSoon())
                                    <span class="ml-1 text-amber-600">Expiring soon</span>
                                @endif
                            @elseif ($isPending)
                                <span class="text-text-muted">Starts after setup</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    @if ($tool->order?->reference)
                        <div>
                            <dt class="text-text-muted">Order reference</dt>
                            <dd class="font-mono text-xs text-text-primary">{{ $tool->order->reference }}</dd>
                        </div>
                    @endif
                </dl>
            </x-dashboard.card>
        </div>
    </div>
</x-layout.page>

@push('scripts')
<script>
(() => {
  async function copySecret(btn, defaultLabel) {
    if (!btn) return;
    const url = btn.getAttribute('data-url');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const fail = window.copyFailedMessage?.() || 'Unable to copy. Try again, or long-press and copy if your browser blocked it.';
    try {
      const loadPassword = async () => {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': token || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Unable to copy');
        return data.password || '';
      };
      // Call copyFromAsync in the click turn so Safari can copy after fetch.
      const copyAsync = window.copyFromAsync;
      const copyFn = window.copyToClipboard;
      const ok = typeof copyAsync === 'function'
        ? await copyAsync(loadPassword)
        : (typeof copyFn === 'function' ? await copyFn(await loadPassword()) : false);
      if (!ok) throw new Error(fail);
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = defaultLabel; }, 2000);
    } catch (e) {
      const msg = e.message || fail;
      alert(msg.includes('not allowed by the user agent') ? fail : msg);
    }
  }

  const siteBtn = document.getElementById('copy-tool-password');
  if (siteBtn) {
    siteBtn.addEventListener('click', () => copySecret(siteBtn, 'Copy password'));
  }

  const livechatBtn = document.getElementById('copy-livechat-password');
  if (livechatBtn) {
    livechatBtn.addEventListener('click', () => copySecret(livechatBtn, 'Copy livechat password'));
  }
})();
</script>
@endpush
@endsection
