@props([
    'credentialRows' => [],
    'title' => 'API credentials',
    'subtitle' => null,
    'connectionStatus' => null,
    'connectionMessage' => null,
    'connectionOk' => null,
    'badgeStatus' => null,
    'showSetupSteps' => false,
])

<x-dashboard.card {{ $attributes->class(['border border-primary/20 bg-gradient-to-br from-primary/10 via-elevated to-muted/40']) }}>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-text-primary">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-xs text-text-secondary">{!! $subtitle !!}</p>
            @endif
            @if ($showSetupSteps)
                <ol class="mt-3 list-decimal space-y-1 pl-4 text-xs text-text-secondary">
                    <li>Copy credentials below and give them to the merchant developer.</li>
                    <li>Merchant installs the owned row with <code class="rounded bg-muted px-1">context=owned_tool</code>.</li>
                    <li>Click <strong>Check connection</strong> on this tool.</li>
                    <li>Only then expect <strong>Admin Auto Login</strong> to succeed on the merchant site.</li>
                </ol>
            @endif
        </div>
        @if ($badgeStatus)
            <x-dashboard.badge :status="$badgeStatus" />
        @endif
    </div>

    <div class="space-y-3" x-data="{
        async copy(text, key) {
            const copyFn = window.copyToClipboard;
            const ok = typeof copyFn === 'function' ? await copyFn(text || '') : false;
            if (ok) {
                this.copied = key;
                setTimeout(() => { if (this.copied === key) this.copied = null; }, 1600);
                return;
            }
            alert(window.copyFailedMessage?.() || 'Unable to copy.');
        },
        copied: null,
        reveal: {},
    }">
        @foreach ($credentialRows as $i => $row)
            @php $value = (string) ($row['value'] ?? ''); @endphp
            <div class="rounded-xl border border-border-default/80 bg-elevated/80 px-3 py-3 sm:px-4">
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ $row['label'] }}</p>
                    <div class="flex items-center gap-1.5">
                        @if ($row['secret'] ?? false)
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-xs font-medium text-text-secondary hover:bg-muted hover:text-text-primary"
                                x-on:click="reveal[{{ $i }}] = !reveal[{{ $i }}]"
                                x-text="reveal[{{ $i }}] ? 'Hide' : 'Show'"
                            ></button>
                        @endif
                        <button
                            type="button"
                            class="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary hover:bg-primary/15"
                            x-on:click="copy(@js($value), {{ $i }})"
                        >
                            <span x-show="copied !== {{ $i }}">Copy</span>
                            <span x-cloak x-show="copied === {{ $i }}">Copied</span>
                        </button>
                    </div>
                </div>
                @if ($row['secret'] ?? false)
                    <p class="break-all font-mono text-xs text-text-primary" x-text="reveal[{{ $i }}] ? @js($value) : @js(str_repeat('•', min(28, max(8, strlen($value)))))"></p>
                @else
                    <p class="break-all font-mono text-xs text-text-primary">{{ $value }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($connectionStatus || $connectionMessage)
        @php
            $statusClass = match (true) {
                $connectionOk === true => 'text-success',
                $connectionStatus === 'pending_merchant' => 'text-amber-600',
                $connectionOk === false => 'text-danger',
                default => 'text-text-muted',
            };
        @endphp
        <p class="mt-4 text-xs {{ $statusClass }}">
            @if ($connectionStatus)
                Connection: <span class="font-medium">{{ $connectionStatus }}</span>
            @endif
            @if ($connectionMessage)
                @if ($connectionStatus) — @endif
                {{ $connectionMessage }}
            @endif
        </p>
    @endif
</x-dashboard.card>
