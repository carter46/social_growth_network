<x-layouts.auth
    title="Join as an Agent | {{ $siteName ?? config('app.name') }}"
    :panel-image="asset('assets/images/about-agents.jpg')"
    panel-headline="Earn by completing YouTube tasks."
    panel-copy="As an Agent you earn rewards for watching, liking, subscribing to and commenting on YouTube videos, following each task's instructions."
>
    <div class="mb-6 hidden lg:block">
        <a href="{{ route('agents') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <span aria-hidden="true">←</span>
            Back to For Agents
        </a>
    </div>

    <header class="mb-6">
        <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Join as an Agent</h2>
        <p class="mt-2 text-sm sm:text-base text-slate-500">
            You'll earn rewards for watching, liking, subscribing to and commenting on YouTube videos when your work is approved. Joining needs an invite code from an existing Agent.
        </p>
        <p class="mt-2 text-xs text-slate-400">
            Rewards depend on task requirements and approval. Earnings and open tasks are not guaranteed.
        </p>
    </header>

    @include('auth._signup-wizard', [
        'action' => route('agent-onboarding.store'),
        'accountType' => 'agent',
        'submitLabel' => 'Create Agent account',
        'withInviteCode' => true,
        'inviteCode' => $inviteCode ?? null,
    ])

    <p class="mt-4 text-center text-sm text-slate-600">
        Want to promote your own videos?
        <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Create a Creator account</a>
    </p>
</x-layouts.auth>
