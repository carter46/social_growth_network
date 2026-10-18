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
                                @if ($identity?->isPending())
                                    <p class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-slate-700">
                                        Your channel check is in progress. You can finish it from the YouTube channel page.
                                    </p>
                                @endif
                                <p class="text-sm text-slate-600">
                                    Connect it now or later. You'll need a connected channel before you can apply for a YouTube task.
                                </p>
                                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                    <x-ui.button :href="route('agent.youtube-channel.show', ['return' => $returnPath])" size="lg">Connect YouTube channel</x-ui.button>
                                    <x-ui.button :href="route('agent')" variant="ghost" size="lg">Skip for now</x-ui.button>
                                </div>
                            @else
                                <p class="text-sm text-slate-600">
                                    Connected: <span class="font-semibold text-slate-900">{{ $identity->title ?: $identity->handle }}</span>
                                </p>
                                <div class="mt-4">
                                    <x-ui.button :href="route('agent.marketplace')" size="lg">Browse open tasks</x-ui.button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</x-layouts.onboarding>
