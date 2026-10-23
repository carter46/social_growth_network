@extends('layouts.dashboard-admin')

@section('title', 'Email Settings')

@section('content')
<x-layout.page
    title="Email Settings"
    subtitle="Brevo API, Laravel Mail fallback, identities, and delivery health."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Site Settings', route('admin.settings')],
        ['Email', null],
    ]"
>
    <div class="space-y-6">
        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Email providers</h2>
            <p class="text-sm text-text-secondary mb-4">Brevo API is primary. Laravel Mail is the fallback. Identities control From addresses.</p>

            @if($brevo->last_error)
                <x-dashboard.alert type="danger" class="mb-4" title="Last Brevo error">
                    <p class="text-sm break-words">{{ $brevo->last_error }}</p>
                    @if(!empty($brevo->meta['last_fallback_reason']))
                        <p class="text-sm mt-2">Fallback was used after: {{ $brevo->meta['last_fallback_reason'] }}</p>
                    @endif
                </x-dashboard.alert>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @foreach([
                    ['label' => 'Brevo', 'row' => $brevo],
                    ['label' => 'Laravel Mail', 'row' => $laravelMail],
                ] as $health)
                    <div class="rounded-xl border border-border-subtle p-4 text-sm">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="font-semibold text-text-primary">{{ $health['label'] }}</span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full {{ $health['row']->status === 'connected' || $health['row']->status === 'configured' ? 'bg-success' : ($health['row']->status === 'error' ? 'bg-danger' : 'bg-text-muted') }}"></span>
                                {{ ucfirst($health['row']->status ?: 'idle') }}
                            </span>
                        </div>
                        <dl class="space-y-1 text-text-secondary">
                            <div class="flex justify-between gap-2"><dt>Last success</dt><dd>{{ $health['row']->last_success_at?->diffForHumans() ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt>Last error</dt><dd>{{ $health['row']->last_error_at?->diffForHumans() ?? '—' }}</dd></div>
                            @if($health['row']->last_error)
                                <p class="text-danger text-xs mt-2 break-words">{{ $health['row']->last_error }}</p>
                            @endif
                        </dl>
                    </div>
                @endforeach
            </div>

            @if(isset($recentEmailFailures) && $recentEmailFailures->isNotEmpty())
                <div class="rounded-xl border border-border-subtle p-4 mb-6 space-y-2">
                    <h3 class="font-semibold text-text-primary">Recent send failures</h3>
                    <p class="text-sm text-text-secondary">For notification delivery attempts, see <a href="{{ route('admin.audit-logs') }}" class="text-primary hover:underline">Audit Logs</a>.</p>
                    @foreach($recentEmailFailures as $attempt)
                        <div class="text-sm text-text-secondary border-t border-border-subtle pt-2 first:border-0 first:pt-0">
                            <p class="text-text-primary">{{ $attempt->created_at?->diffForHumans() }} · {{ $attempt->provider }} · {{ $attempt->recipient ?? '—' }}@if($attempt->http_status) · HTTP {{ $attempt->http_status }}@endif</p>
                            <p class="text-danger break-words">{{ $attempt->error_message ?: 'Unknown error' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.email') }}" class="space-y-6">
                @csrf
                <div class="rounded-xl border border-border-subtle p-4 space-y-4">
                    <h3 class="font-semibold text-text-primary">Brevo (primary)</h3>
                    <input type="hidden" name="brevo_enabled" value="0">
                    <x-dashboard.toggle name="brevo_enabled" label="Enable Brevo API" :checked="old('brevo_enabled', $brevo->enabled)" value="1" />
                    <x-dashboard.secret-input
                        name="brevo_api_key"
                        label="API key"
                        :stored="$brevo->credential('api_key')"
                    />
                </div>
                <div class="rounded-xl border border-border-subtle p-4 space-y-4">
                    <h3 class="font-semibold text-text-primary">Laravel Mail (fallback)</h3>
                    <input type="hidden" name="laravel_mail_enabled" value="0">
                    <x-dashboard.toggle name="laravel_mail_enabled" label="Enable Laravel Mail fallback" :checked="old('laravel_mail_enabled', $laravelMail->enabled)" value="1" />
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-2">Fallback mailer</label>
                        <select name="fallback_mailer" class="w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm">
                            <option value="smtp" @selected(old('fallback_mailer', $laravelMail->credential('mailer', 'smtp')) === 'smtp')>SMTP</option>
                            <option value="sendmail" @selected(old('fallback_mailer', $laravelMail->credential('mailer')) === 'sendmail')>Sendmail</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-dashboard.input name="smtp_host" label="SMTP host" :value="old('smtp_host', $laravelMail->credential('host', config('mail.mailers.smtp.host')))" />
                        <x-dashboard.input name="smtp_port" type="number" label="Port" :value="old('smtp_port', $laravelMail->credential('port', config('mail.mailers.smtp.port')))" />
                        <x-dashboard.input name="smtp_encryption" label="Encryption" :value="old('smtp_encryption', $laravelMail->credential('encryption', config('mail.mailers.smtp.encryption')))" />
                        <x-dashboard.input name="smtp_username" label="Username" :value="old('smtp_username', $laravelMail->credential('username', config('mail.mailers.smtp.username')))" />
                        <x-dashboard.secret-input
                            name="smtp_password"
                            label="Password"
                            :stored="$laravelMail->credential('password')"
                        />
                        <x-dashboard.input name="sendmail_path" label="Sendmail path" :value="old('sendmail_path', $laravelMail->credential('sendmail_path'))" />
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="font-semibold text-text-primary">Email identities</h3>
                    @foreach($emailIdentities as $identity)
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 border border-border-subtle rounded-lg p-3">
                            <div class="md:col-span-4 text-sm font-medium text-text-secondary uppercase tracking-wide">{{ $identity->profile }}</div>
                            <x-dashboard.input name="identities[{{ $identity->profile }}][from_name]" label="From name" :value="old('identities.'.$identity->profile.'.from_name', $identity->from_name)" />
                            <x-dashboard.input name="identities[{{ $identity->profile }}][from_email]" type="email" label="From email" :value="old('identities.'.$identity->profile.'.from_email', $identity->from_email)" />
                            <x-dashboard.input name="identities[{{ $identity->profile }}][reply_to_email]" type="email" label="Reply-To" :value="old('identities.'.$identity->profile.'.reply_to_email', $identity->reply_to_email)" />
                            @if(\Illuminate\Support\Facades\Schema::hasColumn('email_identities', 'notify_to_email'))
                                <x-dashboard.input name="identities[{{ $identity->profile }}][notify_to_email]" type="email" label="Notify inbox (admin alerts)" :value="old('identities.'.$identity->profile.'.notify_to_email', $identity->notify_to_email)" />
                            @endif
                            <label class="flex items-center gap-2 text-sm text-text-secondary pb-2">
                                <input type="hidden" name="identities[{{ $identity->profile }}][enabled]" value="0">
                                <input type="checkbox" name="identities[{{ $identity->profile }}][enabled]" value="1" @checked(old('identities.'.$identity->profile.'.enabled', $identity->enabled))>
                                Enabled
                            </label>
                        </div>
                    @endforeach
                </div>

                <x-dashboard.button type="submit" variant="primary">Save email settings</x-dashboard.button>
            </form>

            <form
                method="POST"
                action="{{ route('admin.settings.test-mail') }}"
                class="mt-6 space-y-4 border-t border-border-subtle pt-6"
                x-data="{
                    sending: false,
                    status: '',
                    outcome: null,
                    fromEmail: '',
                    attempts: [],
                    async submit(e) {
                        e.preventDefault();
                        if (this.sending) return;
                        this.sending = true;
                        this.status = '';
                        this.outcome = null;
                        this.fromEmail = '';
                        this.attempts = [];
                        try {
                            const res = await fetch(e.target.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': e.target.querySelector('[name=_token]')?.value || '',
                                },
                                body: new FormData(e.target),
                            });
                            const data = await res.json().catch(() => ({}));
                            this.outcome = data.outcome || (data.ok ? 'brevo' : 'failed');
                            this.fromEmail = data.from_email || '';
                            this.attempts = Array.isArray(data.attempts) ? data.attempts : [];
                            if (data.errors?.test_email?.[0]) {
                                this.status = data.errors.test_email[0];
                                this.outcome = 'failed';
                            } else {
                                this.status = data.message || data.error || 'Mail send failed.';
                            }
                        } catch (err) {
                            this.outcome = 'failed';
                            this.status = err?.message || 'Mail send failed.';
                        } finally {
                            this.sending = false;
                        }
                    }
                }"
                @submit="submit"
            >
                @csrf
                <h3 class="font-semibold text-text-primary">Send test email</h3>
                <p class="text-sm text-text-secondary">Tries Brevo first, then SMTP if Brevo fails. The report below shows each step — a fallback is not shown as a clean success.</p>
                <x-dashboard.input name="test_email" type="email" label="Send test to" :value="old('test_email', auth()->user()->email)" required />
                <x-dashboard.input name="test_subject" label="Subject (optional)" :value="old('test_subject', $siteName.' — test email')" />
                <div
                    x-show="status"
                    x-cloak
                    class="rounded-xl border p-4 space-y-3 text-sm"
                    :class="{
                        'border-success/40 bg-success/5': outcome === 'brevo',
                        'border-warning/40 bg-warning/5': outcome === 'fallback',
                        'border-danger/40 bg-danger/5': outcome === 'failed'
                    }"
                >
                    <p
                        class="break-words font-medium"
                        :class="{
                            'text-success': outcome === 'brevo',
                            'text-warning': outcome === 'fallback',
                            'text-danger': outcome === 'failed'
                        }"
                        x-text="status"
                    ></p>
                    <p class="text-text-muted" x-show="fromEmail" x-cloak>
                        From: <span x-text="fromEmail"></span>
                    </p>
                    <ul class="space-y-2" x-show="attempts.length" x-cloak>
                        <template x-for="row in attempts" :key="row.step + '-' + row.label">
                            <li class="rounded-lg border border-border-subtle bg-elevated px-3 py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium text-text-primary" x-text="'Step ' + row.step + ': ' + row.label"></span>
                                    <span
                                        class="text-xs font-semibold uppercase"
                                        :class="row.success ? 'text-success' : 'text-danger'"
                                        x-text="row.success ? 'OK' : 'Failed'"
                                    ></span>
                                </div>
                                <p class="mt-1 text-danger break-words" x-show="row.error" x-text="row.error"></p>
                                <p class="mt-1 text-text-muted" x-show="row.message_id" x-cloak>
                                    Message ID: <span x-text="row.message_id"></span>
                                </p>
                                <p class="mt-1 text-text-muted" x-show="row.http_status" x-cloak>
                                    HTTP <span x-text="row.http_status"></span>
                                    <span x-show="row.latency_ms" x-text="' · ' + row.latency_ms + ' ms'"></span>
                                </p>
                            </li>
                        </template>
                    </ul>
                </div>
                @error('test_email')
                    <p class="text-sm text-danger" x-show="!status">{{ $message }}</p>
                @enderror
                <x-dashboard.button type="submit" variant="secondary" x-bind:disabled="sending">
                    <span x-text="sending ? 'Sending…' : 'Send test email'">Send test email</span>
                </x-dashboard.button>
            </form>
        </x-dashboard.card>
    </div>
</x-layout.page>
@endsection
