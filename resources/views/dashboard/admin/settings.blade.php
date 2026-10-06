@extends('layouts.dashboard-admin')

@section('title', 'Site Settings')

@section('content')
@php
    $chatProvider = old('live_chat_provider', $liveChat['provider'] ?? 'none');
    $smartsuppStoredKey = (string) (\App\Models\IntegrationProvider::forProvider(\App\Models\IntegrationProvider::SMARTSUPP)->credential('key') ?? '');
    $jivoId = old('jivo_widget_id', ($liveChat['provider'] ?? '') === 'jivo' ? ($liveChat['credentials']['widget_id'] ?? '') : '');
    $chatwayId = old('chatway_widget_id', ($liveChat['provider'] ?? '') === 'chatway' ? ($liveChat['credentials']['widget_id'] ?? '') : '');
@endphp
<x-layout.page
    title="Site Settings"
    subtitle="Site information, contact, social links, and Google Sign-In."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Site Settings', null],
    ]"
>
    <div class="space-y-6">
        {{-- Site Information --}}
        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Site information</h2>
            <p class="text-sm text-text-secondary mb-4">Name, heading, favicon, and light/dark logos used across the site and themes.</p>
            <form method="POST" action="{{ route('admin.settings.branding') }}" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                    <div class="w-full"><x-dashboard.input name="site_name" label="Site name" :value="old('site_name', $branding['site_name'])" required /></div>
                    <div class="w-full"><x-dashboard.input name="site_short_name" label="Short name" :value="old('site_short_name', $branding['site_short_name'])" /></div>
                    <div class="w-full md:col-span-2"><x-dashboard.input name="heading" label="Heading" :value="old('heading', $branding['heading'])" /></div>
                    <div class="w-full md:col-span-2"><x-dashboard.input name="tagline" label="Tagline" :value="old('tagline', $branding['tagline'])" /></div>
                    <div class="w-full md:col-span-2"><x-dashboard.input name="meta_description" label="Meta description" :value="old('meta_description', $branding['meta_description'])" /></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full items-start">
                    <div class="w-full">
                        <x-dashboard.media-picker name="favicon_media_id" label="Favicon" :value="old('favicon_media_id', $branding['favicon_media_id'])" hint="Square icon for browser tabs." />
                    </div>
                    <div class="w-full">
                        <x-dashboard.media-picker name="logo_light_media_id" label="Light theme logo" :value="old('logo_light_media_id', $branding['logo_light_media_id'])" preview="wide" />
                    </div>
                    <div class="w-full">
                        <x-dashboard.media-picker name="logo_dark_media_id" label="Dark theme logo" :value="old('logo_dark_media_id', $branding['logo_dark_media_id'])" preview="wide" />
                    </div>
                </div>
                <x-dashboard.button type="submit" variant="primary">Save site information</x-dashboard.button>
            </form>
        </x-dashboard.card>

        {{-- Contact & live chat --}}
        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Platform contact</h2>
            <p class="text-sm text-text-secondary mb-4">Public phones and address for Contact Us and the footer contact block. Public emails are managed under Email identities.</p>
            <form method="POST" action="{{ route('admin.settings.contact') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-dashboard.input name="phone_support" label="Support phone" :value="old('phone_support', $contact['phone_support'])" />
                    <x-dashboard.input name="phone_general" label="General phone" :value="old('phone_general', $contact['phone_general'])" />
                    <x-dashboard.input name="phone_whatsapp" label="WhatsApp number" :value="old('phone_whatsapp', $contact['phone_whatsapp'])" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-dashboard.input name="address_street" label="Street" :value="old('address_street', $contact['address_street'])" />
                    <x-dashboard.input name="address_city" label="City" :value="old('address_city', $contact['address_city'])" />
                    <x-dashboard.input name="address_state" label="State" :value="old('address_state', $contact['address_state'])" />
                    <x-dashboard.input name="address_country" label="Country" :value="old('address_country', $contact['address_country'])" />
                    <x-dashboard.input name="address_postal" label="Postal code" :value="old('address_postal', $contact['address_postal'])" />
                    <x-dashboard.input name="timezone" label="Timezone" :value="old('timezone', $contact['timezone'])" />
                    <x-dashboard.input name="latitude" label="Latitude" :value="old('latitude', $contact['latitude'])" />
                    <x-dashboard.input name="longitude" label="Longitude" :value="old('longitude', $contact['longitude'])" />
                    <x-dashboard.input name="maps_url" label="Google Maps URL" :value="old('maps_url', $contact['maps_url'])" class="md:col-span-2" />
                    <x-dashboard.input name="maps_embed_url" label="Maps embed URL" :value="old('maps_embed_url', $contact['maps_embed_url'])" class="md:col-span-2" hint="Paste the iframe src URL for an embedded map." />
                    <x-dashboard.input name="support_hours" label="Support hours" :value="old('support_hours', $contact['support_hours'])" />
                    <x-dashboard.input name="business_hours" label="Business hours" :value="old('business_hours', $contact['business_hours'])" />
                    <x-dashboard.input name="registration_number" label="Registration number" :value="old('registration_number', $contact['registration_number'])" />
                    <x-dashboard.input name="vat_number" label="VAT" :value="old('vat_number', $contact['vat_number'])" />
                    <x-dashboard.input name="company_number" label="Company number" :value="old('company_number', $contact['company_number'])" />
                </div>

                <hr class="border-border-subtle my-4">
                <div class="space-y-4" x-data="{ provider: @js($chatProvider) }">
                    <h3 class="text-base font-semibold text-text-primary">Live chat</h3>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-2" for="live_chat_provider">Provider</label>
                        <select
                            id="live_chat_provider"
                            name="live_chat_provider"
                            x-model="provider"
                            class="w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm"
                        >
                            <option value="none">Off</option>
                            <option value="smartsupp">Smartsupp</option>
                            <option value="jivo">JivoChat</option>
                            <option value="chatway">Chatway</option>
                        </select>
                    </div>
                    <div x-show="provider === 'smartsupp'" x-cloak>
                        <x-dashboard.secret-input
                            name="smartsupp_key"
                            label="Smartsupp key"
                            :stored="$smartsuppStoredKey"
                        />
                    </div>
                    <div x-show="provider === 'jivo'" x-cloak>
                        <x-dashboard.input name="jivo_widget_id" label="Jivo widget ID" :value="$jivoId" hint="Widget ID only, or paste the full Jivo script URL." />
                    </div>
                    <div x-show="provider === 'chatway'" x-cloak>
                        <x-dashboard.input name="chatway_widget_id" label="Chatway widget ID" :value="$chatwayId" hint="Widget ID from Chatway install snippet." />
                    </div>
                    <p x-show="provider === 'none'" x-cloak class="text-sm text-text-muted">Live chat is off. Choose a provider to configure credentials.</p>
                </div>
                <x-dashboard.button type="submit" variant="primary">Save contact & live chat</x-dashboard.button>
            </form>
        </x-dashboard.card>

        {{-- Social links --}}
        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Social links</h2>
            <p class="text-sm text-text-secondary mb-4">Add platforms with optional custom logos. Without a logo, the footer uses built-in brand icons (or a letter fallback).</p>
            @php
                $socialRows = $socialLinks->values();
                $blankSlots = 5;
                $baseIndex = $socialRows->count();
            @endphp
            <form
                method="POST"
                action="{{ route('admin.settings.social') }}"
                class="space-y-4"
                x-data="{ visibleNew: {{ old('links.'.$baseIndex.'.platform') || old('links.'.$baseIndex.'.url') ? 1 : 0 }} }"
            >
                @csrf
                @foreach($socialRows as $i => $link)
                    <div
                        class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border border-border-subtle rounded-lg p-4"
                        x-data="{ removed: false }"
                        x-show="!removed"
                    >
                        <input type="hidden" name="links[{{ $i }}][id]" value="{{ $link->id }}">
                        <input type="hidden" name="links[{{ $i }}][delete]" :value="removed ? '1' : '0'">
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.input name="links[{{ $i }}][platform]" label="Platform" :value="old('links.'.$i.'.platform', $link->platform)" />
                        </div>
                        <div class="md:col-span-3 w-full">
                            <x-dashboard.input name="links[{{ $i }}][url]" label="URL" :value="old('links.'.$i.'.url', $link->url)" />
                        </div>
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.input name="links[{{ $i }}][icon]" label="Icon key" :value="old('links.'.$i.'.icon', $link->icon)" hint="e.g. facebook" />
                        </div>
                        <div class="md:col-span-1 w-full">
                            <x-dashboard.input name="links[{{ $i }}][sort_order]" type="number" label="Sort" :value="old('links.'.$i.'.sort_order', $link->sort_order)" />
                        </div>
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.media-picker name="links[{{ $i }}][icon_media_id]" label="Logo" :value="old('links.'.$i.'.icon_media_id', $link->icon_media_id)" />
                        </div>
                        <div class="md:col-span-2 flex flex-wrap items-center gap-3 pb-2">
                            <label class="inline-flex items-center gap-2 text-sm text-text-secondary">
                                <input type="hidden" name="links[{{ $i }}][enabled]" value="0">
                                <input type="checkbox" name="links[{{ $i }}][enabled]" value="1" @checked(old('links.'.$i.'.enabled', $link->enabled))>
                                Enabled
                            </label>
                            <button type="button" class="text-sm text-danger hover:underline" @click="removed = true">Remove</button>
                        </div>
                    </div>
                @endforeach

                @for($s = 0; $s < $blankSlots; $s++)
                    @php $idx = $baseIndex + $s; @endphp
                    <div
                        class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border border-dashed border-border-subtle rounded-lg p-4"
                        x-show="visibleNew > {{ $s }}"
                        x-cloak
                    >
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.input name="links[{{ $idx }}][platform]" label="Platform" :value="old('links.'.$idx.'.platform')" placeholder="e.g. Instagram" />
                        </div>
                        <div class="md:col-span-3 w-full">
                            <x-dashboard.input name="links[{{ $idx }}][url]" label="URL" :value="old('links.'.$idx.'.url')" />
                        </div>
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.input name="links[{{ $idx }}][icon]" label="Icon key" :value="old('links.'.$idx.'.icon')" hint="e.g. instagram" />
                        </div>
                        <div class="md:col-span-1 w-full">
                            <x-dashboard.input name="links[{{ $idx }}][sort_order]" type="number" label="Sort" :value="old('links.'.$idx.'.sort_order', $idx)" />
                        </div>
                        <div class="md:col-span-2 w-full">
                            <x-dashboard.media-picker name="links[{{ $idx }}][icon_media_id]" label="Logo" :value="old('links.'.$idx.'.icon_media_id')" />
                        </div>
                        <div class="md:col-span-2 flex items-center pb-2">
                            <input type="hidden" name="links[{{ $idx }}][enabled]" value="1">
                            <span class="text-xs text-text-muted">New link</span>
                        </div>
                    </div>
                @endfor

                <div class="flex flex-wrap items-center gap-3">
                    <x-dashboard.button
                        type="button"
                        variant="secondary"
                        x-on:click="if (visibleNew < {{ $blankSlots }}) visibleNew++"
                        x-bind:disabled="visibleNew >= {{ $blankSlots }}"
                    >
                        Add social link
                    </x-dashboard.button>
                    <x-dashboard.button type="submit" variant="primary">Save social links</x-dashboard.button>
                </div>
            </form>
        </x-dashboard.card>

        {{-- Google Identity --}}
        @php
            $giMeta = $googleIdentity->meta ?? [];
            $giCooldown = (int) ($giMeta['one_tap_prompt_cooldown_hours'] ?? 24);
        @endphp
        <x-dashboard.card variant="solid">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Google Identity</h2>
            <p class="text-sm text-text-secondary mb-4">
                Sign in with Google and One Tap via Google Identity Services. Configure the OAuth client in Google Cloud, then paste the Client ID here.
                A Client Secret is not required for Sign-In / One Tap.
            </p>

            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-border-subtle p-3">
                    <p class="text-xs text-text-secondary">Status</p>
                    <p class="mt-1 text-sm font-medium text-text-primary capitalize">{{ $googleIdentity->status ?: 'idle' }}</p>
                </div>
                <div class="rounded-xl border border-border-subtle p-3">
                    <p class="text-xs text-text-secondary">Last success</p>
                    <p class="mt-1 text-sm text-text-primary">{{ $googleIdentity->last_success_at?->diffForHumans() ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-border-subtle p-3">
                    <p class="text-xs text-text-secondary">Last error</p>
                    <p class="mt-1 text-sm text-text-primary break-words">{{ $googleIdentity->last_error ? \Illuminate\Support\Str::limit($googleIdentity->last_error, 120) : '—' }}</p>
                </div>
            </div>

            @error('google_identity_test')
                <p class="mb-4 text-sm text-danger">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.settings.google-identity') }}" class="space-y-6">
                @csrf
                <div class="rounded-xl border border-border-subtle p-4 space-y-4">
                    <h3 class="text-base font-semibold text-text-primary">Credentials</h3>
                    <input type="hidden" name="google_identity_enabled" value="0">
                    <x-dashboard.toggle name="google_identity_enabled" label="Enable Google Sign-In" :checked="old('google_identity_enabled', $googleIdentity->enabled)" value="1" />
                    <x-dashboard.input name="google_identity_client_id" label="Google Client ID" :value="old('google_identity_client_id', $googleIdentity->credential('client_id'))" hint="Required when Google Sign-In is enabled." />
                    <x-dashboard.secret-input
                        name="google_identity_client_secret"
                        label="Client Secret (optional)"
                        :stored="$googleIdentity->credential('client_secret')"
                        hint="Only needed for future Google API integrations (Gmail, Calendar, Drive). Not required for Sign-In / One Tap. Leave blank to keep."
                    />
                    <div>
                        <label class="block text-sm font-medium text-text-primary mb-1">Authorized JavaScript Origin</label>
                        <input type="text" readonly value="{{ $googleIdentityJsOrigin }}" class="w-full rounded-lg border border-border-subtle bg-surface-muted px-3 py-2 text-sm text-text-secondary" />
                        <p class="mt-1 text-xs text-text-secondary">Add this exact origin in Google Cloud Console → Credentials.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-border-subtle p-4 space-y-4">
                    <h3 class="text-base font-semibold text-text-primary">One Tap</h3>
                    <p class="text-sm text-text-secondary">Control where One Tap appears for guests. Sign-In buttons on login/register follow Enable Google Sign-In above.</p>
                    <input type="hidden" name="google_identity_one_tap_enabled" value="0">
                    <x-dashboard.toggle name="google_identity_one_tap_enabled" label="Enable One Tap" :checked="old('google_identity_one_tap_enabled', $giMeta['one_tap_enabled'] ?? false)" value="1" />
                    <input type="hidden" name="google_identity_auto_select_enabled" value="0">
                    <x-dashboard.toggle name="google_identity_auto_select_enabled" label="Automatic sign-in" :checked="old('google_identity_auto_select_enabled', $giMeta['auto_select_enabled'] ?? false)" value="1" />
                    <input type="hidden" name="google_identity_one_tap_show_home" value="0">
                    <x-dashboard.toggle name="google_identity_one_tap_show_home" label="Show on Home" :checked="old('google_identity_one_tap_show_home', $giMeta['one_tap_show_home'] ?? true)" value="1" />
                    <input type="hidden" name="google_identity_one_tap_show_login" value="0">
                    <x-dashboard.toggle name="google_identity_one_tap_show_login" label="Show on Login" :checked="old('google_identity_one_tap_show_login', $giMeta['one_tap_show_login'] ?? false)" value="1" />
                    <input type="hidden" name="google_identity_one_tap_show_register" value="0">
                    <x-dashboard.toggle name="google_identity_one_tap_show_register" label="Show on Register" :checked="old('google_identity_one_tap_show_register', $giMeta['one_tap_show_register'] ?? false)" value="1" />
                    <input type="hidden" name="google_identity_one_tap_disable_after_dismiss" value="0">
                    <x-dashboard.toggle name="google_identity_one_tap_disable_after_dismiss" label="Disable after dismiss" :checked="old('google_identity_one_tap_disable_after_dismiss', $giMeta['one_tap_disable_after_dismiss'] ?? true)" value="1" />
                    <x-dashboard.input name="google_identity_one_tap_prompt_cooldown_hours" type="number" min="1" max="8760" label="Prompt cooldown (hours)" :value="old('google_identity_one_tap_prompt_cooldown_hours', $giCooldown)" hint="Hours to wait after dismiss before showing One Tap again." />
                </div>

                <details class="rounded-xl border border-border-subtle p-4 text-sm text-text-secondary">
                    <summary class="cursor-pointer font-medium text-text-primary">Google Cloud setup checklist</summary>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        <li>Create an OAuth client (Web application) in Google Cloud Console.</li>
                        <li>Configure the OAuth consent screen and authorized domains.</li>
                        <li>Add the Authorized JavaScript Origin shown above.</li>
                        <li>Publish Privacy Policy and Terms of Service URLs on the consent screen.</li>
                        <li>Only basic scopes are used: openid, email, profile.</li>
                    </ul>
                </details>

                <x-dashboard.button type="submit" variant="primary">Save Google Identity settings</x-dashboard.button>
            </form>

            <form
                method="POST"
                action="{{ route('admin.settings.google-identity.test') }}"
                class="mt-4 space-y-2"
                x-data="{
                    testing: false,
                    status: '',
                    ok: null,
                    async submit(e) {
                        e.preventDefault();
                        if (this.testing) return;
                        this.testing = true;
                        this.status = '';
                        this.ok = null;
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
                            this.ok = !!data.ok;
                            this.status = data.message || data.errors?.google_identity_test?.[0] || (this.ok ? 'OK' : 'Failed');
                        } catch (err) {
                            this.ok = false;
                            this.status = err?.message || 'Failed';
                        } finally {
                            this.testing = false;
                        }
                    }
                }"
                @submit="submit"
            >
                @csrf
                <input type="hidden" name="google_identity_client_id" value="{{ old('google_identity_client_id', $googleIdentity->credential('client_id')) }}">
                <x-dashboard.button type="submit" variant="secondary" x-bind:disabled="testing">
                    <span x-text="testing ? 'Testing…' : 'Test configuration'">Test configuration</span>
                </x-dashboard.button>
                <p class="text-sm break-words" x-show="status" x-text="status" x-cloak :class="ok === true ? 'text-success' : 'text-danger'"></p>
            </form>
        </x-dashboard.card>

        {{-- Referral program --}}
        <x-dashboard.card variant="solid" id="referral-program">
            <h2 class="text-lg font-semibold text-text-primary mb-1">Referral program</h2>
            <p class="text-sm text-text-secondary mb-4">
                Only agents can refer. They earn a lifetime percentage of what the people they refer earn (agents) or spend (creators).
                Each commission keeps the percentage in force when it was earned, so changes here only affect new earnings.
            </p>

            <form method="POST" action="{{ route('admin.settings.referrals') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="referral_enabled" value="0">
                <x-dashboard.toggle name="referral_enabled" label="Enable referral program" :checked="old('referral_enabled', $referral['enabled'])" value="1" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-dashboard.input
                        name="referral_agent_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        label="When an agent refers an agent (%)"
                        :value="old('referral_agent_percent', $referral['agent_percent'])"
                        hint="Percentage of every task reward the referred agent earns. Example: 50% of a ₦4 reward is ₦2."
                    />
                    <x-dashboard.input
                        name="referral_creator_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        label="When an agent refers a creator (%)"
                        :value="old('referral_creator_percent', $referral['creator_percent'])"
                        hint="Percentage of every paid order the referred creator places. This is paid out of the platform's margin."
                    />
                </div>

                <x-dashboard.button type="submit" variant="primary">Save referral settings</x-dashboard.button>
            </form>
        </x-dashboard.card>

    </div>
</x-layout.page>
@endsection
