@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name ?? 'Agent')->before(' ')->toString() ?: 'Agent';
    $steps = [
        ['title' => 'Create your account', 'body' => 'Your Agent account is ready.', 'state' => 'done'],
        ['title' => 'Verify your email', 'body' => 'Your email address is confirmed.', 'state' => 'done'],
        [
            'title' => 'Connect your YouTube channel',
            'body' => 'YouTube tasks are completed from your own channel.',
            'state' => $channelVerified ? 'done' : 'current',
        ],
        [
            'title' => 'Pick your first task',
            'body' => 'Browse open tasks and apply for the ones that suit you.',
            'state' => $channelVerified ? 'current' : 'upcoming',
        ],
    ];
@endphp

<x-layouts.onboarding :title="'Get started | '.($siteName ?? config('app.name'))">
    <div class="text-center">
        <p class="text-xs font-bold uppercase tracking-wider text-primary">Getting started</p>
        <h1 class="mt-2 font-display text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">Welcome, {{ $firstName }}</h1>
        <p class="mx-auto mt-3 max-w-md text-base text-slate-600">A couple of quick steps and you're ready to take on tasks.</p>
    </div>

    <ol class="mt-10 space-y-0" aria-label="Onboarding steps">
        @foreach ($steps as $step)
            @php $isLast = $loop->last; @endphp
            <li class="relative flex gap-4 {{ $isLast ? '' : 'pb-8' }}">
                @unless ($isLast)
                    <span class="absolute left-[15px] top-9 bottom-0 w-0.5 {{ $step['state'] === 'done' ? 'bg-emerald-400' : 'bg-slate-200' }}" aria-hidden="true"></span>
                @endunless

                <span @class([
                    'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                    'bg-emerald-500 text-white' => $step['state'] === 'done',
                    'bg-primary text-white ring-4 ring-primary/20' => $step['state'] === 'current',
                    'bg-white text-slate-400 border-2 border-slate-200' => $step['state'] === 'upcoming',
                ])>
                    @if ($step['state'] === 'done')
                        <x-ui.icon name="check" class="h-4 w-4" />
                        <span class="sr-only">Done:</span>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>

                <div class="min-w-0 flex-1 pt-0.5">
                    <p @class([
                        'font-semibold',
                        'text-slate-500' => $step['state'] === 'done',
                        'text-slate-900' => $step['state'] === 'current',
                        'text-slate-400' => $step['state'] === 'upcoming',
                    ])>{{ $step['title'] }}</p>
                    <p class="mt-0.5 text-sm {{ $step['state'] === 'upcoming' ? 'text-slate-400' : 'text-slate-500' }}">{{ $step['body'] }}</p>

                    @if ($step['state'] === 'current')
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            @if (! $channelVerified)
                                @php
                                    $isPending = (bool) $identity?->isPending();
                                    $openLinkModal = ($isPending && session('status')) || $errors->any() || old('channel_url') || $channelMissing === 'link';
                                @endphp
                                @if (session('error'))
                                    <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" data-onboarding-error>{{ session('error') }}</p>
                                @elseif (session('status'))
                                    <p class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-slate-700">{{ session('status') }}</p>
                                @elseif ($isPending)
                                    <p class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-slate-700">
                                        Your channel check is in progress. Open it to see your code and finish.
                                    </p>
                                @endif
                                @if ($channelMissing === 'oauth')
                                    <div class="mb-4">
                                        @include('partials.youtube-channel.missing-channel', ['prefix' => 'agent', 'oauthEnabled' => $oauthEnabled, 'returnTo' => $returnPath, 'source' => 'oauth'])
                                    </div>
                                @endif
                                <p class="text-sm text-slate-600">
                                    Connect it now or later. You'll need a connected channel before you can apply for a YouTube task.
                                </p>
                                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                    @if ($isPending)
                                        <x-ui.button type="button" size="lg" x-data x-on:click="$dispatch('open-modal', 'connect-youtube')" data-onboarding-finish>Finish connecting</x-ui.button>
                                    @elseif ($oauthEnabled)
                                        <x-ui.button :href="route('agent.youtube-channel.oauth', ['return' => $returnPath])" size="lg" data-onboarding-connect>Connect YouTube channel</x-ui.button>
                                    @else
                                        <x-ui.button type="button" size="lg" x-data x-on:click="$dispatch('open-modal', 'connect-youtube')" data-onboarding-connect>Connect YouTube channel</x-ui.button>
                                    @endif
                                    <x-ui.button :href="route('agent')" variant="ghost" size="lg">Skip for now</x-ui.button>
                                </div>
                                @if ($oauthEnabled && ! $isPending)
                                    <p class="mt-3 text-xs text-slate-500">
                                        You'll sign in with Google and pick the account that owns your channel. We can only view it, never post or change anything.
                                        <button type="button" class="font-semibold text-primary hover:underline" x-data x-on:click="$dispatch('open-modal', 'connect-youtube')" data-onboarding-manual>Use your channel link instead</button>
                                    </p>
                                @endif

                                <x-ui.modal name="connect-youtube" title="Connect your YouTube channel" data-onboarding-modal>
                                    @include('partials.youtube-channel.link', ['identity' => $identity, 'prefix' => 'agent', 'oauthEnabled' => false, 'returnTo' => $returnPath, 'channelMissing' => $channelMissing === 'link' ? 'link' : false])
                                    <x-slot:footer>
                                        <x-ui.button type="button" variant="secondary" x-on:click="closeModal()">Close</x-ui.button>
                                    </x-slot:footer>
                                </x-ui.modal>
                                @if ($openLinkModal)
                                    <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'connect-youtube'))"></div>
                                @endif
                            @else
                                @if ($justConnected)
                                    <p class="mb-4 text-sm font-semibold text-emerald-700" data-onboarding-just-connected>Nice work, your channel is connected. You're ready for your first task.</p>
                                @endif
                                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3" data-onboarding-channel>
                                    @if ($identity->thumbnail_url)
                                        <img src="{{ $identity->thumbnail_url }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer">
                                    @else
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                                            <x-ui.icon name="check" class="h-5 w-5" />
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Channel connected</p>
                                        <p class="truncate font-semibold text-slate-900">{{ $identity->title ?: 'Your channel' }}</p>
                                        @if ($identity->handle)
                                            <p class="truncate text-sm text-slate-500">{{ $identity->handle }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                    <x-ui.button :href="route('agent.marketplace')" size="lg">Browse open tasks</x-ui.button>
                                    <x-ui.button :href="route('agent.youtube-channel.show')" variant="ghost" size="lg">Manage channel</x-ui.button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</x-layouts.onboarding>
