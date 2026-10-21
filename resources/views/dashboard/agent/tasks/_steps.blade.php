@use('App\Enums\CampaignTask')
@use('App\Models\CampaignParticipationTask', 'Step')
@php
    $isStarted = $participation->status === 'started';
    $activeStep = $steps->firstWhere('status', Step::STATUS_ACTIVE);
    $hasLocked = $steps->contains('status', Step::STATUS_LOCKED);
    $allDone = $steps->isNotEmpty() && $steps->every(fn ($s) => $s->status === Step::STATUS_DONE);
    $hasEmbed = ($embed['mode'] ?? '') === 'embed' && ! empty($embed['html']);
    $videoUrl = $campaign?->target_url;
    $channelUrl = $campaign?->channel_url;
    $statusBadge = [
        Step::STATUS_LOCKED => 'neutral',
        Step::STATUS_ACTIVE => 'info',
        Step::STATUS_DONE => 'info',
        Step::STATUS_CHECKING => 'verifying',
        Step::STATUS_VERIFIED => 'success',
        Step::STATUS_NEEDS_REVIEW => 'pending',
        Step::STATUS_ACCEPTED => 'success',
        Step::STATUS_REJECTED => 'danger',
        Step::STATUS_EXPIRED => 'expired',
    ];
@endphp

<div class="space-y-4">
    @if ($isStarted && $steps->isNotEmpty())
        <ol class="flex flex-wrap items-center gap-2" aria-label="Steps">
            @foreach ($steps as $timelineStep)
                @php $timelineTask = $timelineStep->taskEnum(); @endphp
                <li class="flex items-center gap-2 text-sm">
                    @if (! $loop->first)
                        <span class="text-text-muted" aria-hidden="true">→</span>
                    @endif
                    <span @class([
                        'rounded-full px-3 py-1 font-medium',
                        'bg-primary/10 text-primary' => $timelineStep->status === Step::STATUS_ACTIVE,
                        'text-text-primary' => $timelineStep->status === Step::STATUS_DONE,
                        'text-text-muted' => ! in_array($timelineStep->status, [Step::STATUS_ACTIVE, Step::STATUS_DONE], true),
                    ])>{{ $timelineTask?->shortLabel() ?? $timelineStep->task }}</span>
                </li>
            @endforeach
        </ol>
    @elseif (in_array($participation->status, ['submitted', 'verifying', 'under_review'], true))
        <x-dashboard.alert type="info">Task submitted. We are checking it now.</x-dashboard.alert>
        <x-dashboard.button :href="route('agent.tasks.active')">Done</x-dashboard.button>
    @endif

    <ol class="space-y-3">
        @foreach ($steps as $step)
            @php $task = $step->taskEnum(); @endphp
            <li class="rounded-xl border border-border-default px-4 py-3 space-y-3 {{ $step->status === Step::STATUS_ACTIVE ? 'bg-elevated' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-text-primary">{{ $task?->shortLabel() ?? $step->task }}</p>
                    <x-dashboard.badge :status="$statusBadge[$step->status] ?? 'neutral'">{{ $step->statusLabel() }}</x-dashboard.badge>
                </div>

                @if ($step->rejection_reason)
                    <p class="text-sm text-danger">{{ $step->rejection_reason }}</p>
                @endif

                @if ($isStarted && $step->status === Step::STATUS_ACTIVE)
                    @if ($task === CampaignTask::Subscribe)
                        @php $subscribeProofRequired = app(\App\Services\Campaigns\CampaignTaskFlowService::class)->subscribeScreenshotRequired(); @endphp
                        <div class="space-y-3">
                            <p class="text-sm text-text-secondary">Subscribe with the YouTube channel you linked, then upload a screenshot that shows you are subscribed.</p>
                            @if ($channelUrl)
                                <a href="{{ $channelUrl }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-primary hover:underline">Open the channel on YouTube</a>
                            @endif
                            <form method="POST" action="{{ route('agent.tasks.steps.save', [$participation, 'subscribe']) }}" enctype="multipart/form-data" class="space-y-3" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)">
                                @csrf
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium text-text-secondary" for="subscribe-proof">{{ $subscribeProofRequired ? 'Screenshot' : 'Screenshot (optional)' }}</label>
                                    <input id="subscribe-proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" @if ($subscribeProofRequired) required @endif class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-elevated file:px-3 file:py-2 file:text-sm file:text-text-primary" />
                                    <p class="text-xs text-text-muted">JPG, PNG, or WEBP, up to 2 MB. Images over 1 MB are reduced so they stay clear and under 1 MB.</p>
                                </div>
                                <x-dashboard.button type="submit" x-bind:disabled="busy">Mark step done</x-dashboard.button>
                                <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                            </form>
                        </div>
                    @elseif ($task === CampaignTask::Watch)
                        <div class="space-y-3" x-data="watchTaskModal({
                            requiredSeconds: {{ (int) $requiredSeconds }},
                            token: @js($watchToken),
                            startUrl: @js(route('agent.tasks.start-watch', $participation)),
                            claimUrl: @js(route('agent.tasks.claim-watch', $participation)),
                            csrf: @js(csrf_token()),
                        })">
                            <p class="text-sm text-text-secondary" x-show="! running && ! done">Press play on the video. The timer starts when it plays.</p>
                            @if ($hasEmbed)
                                <div class="overflow-hidden rounded-xl border border-border-default">{!! $embed['html'] !!}</div>
                            @elseif (! empty($embed['open_url']))
                                <a href="{{ $embed['open_url'] }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-primary hover:underline" x-on:click="start()">Open the video</a>
                            @endif
                            <div class="flex flex-wrap items-center gap-3">
                                <template x-if="token && ! done">
                                    <p class="rounded-lg bg-muted/40 px-3 py-2 text-sm font-semibold text-text-primary" aria-live="polite">
                                        Watch time left: <span x-text="display"></span>
                                    </p>
                                </template>
                                <template x-if="done">
                                    <form method="POST" :action="claimUrl" data-ajax-form @submit.prevent="claim($event)">
                                        <input type="hidden" name="_token" :value="csrf">
                                        <input type="hidden" name="token" :value="token">
                                        <x-dashboard.button type="submit" x-bind:disabled="claiming">Finish watch step</x-dashboard.button>
                                        <p class="mt-2 text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                                    </form>
                                </template>
                            </div>
                            <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                            <p class="text-xs text-text-muted" x-show="videoEnded && token && ! done" x-cloak>The video has ended. The timer will finish on its own. You don't need to press play again.</p>
                            <p class="text-xs text-text-muted" x-show="needsReplay && ! done" x-cloak>The video ended. Press play again so the timer can finish.</p>
                            <p class="text-xs text-text-muted" x-show="token && ! done && ! videoEnded && ! needsReplay" x-cloak>Keep this page open while the video plays.</p>
                        </div>
                    @elseif ($task === CampaignTask::Comment)
                        <div class="space-y-3">
                            @if (filled($campaign?->comment_instructions))
                                <div class="rounded-lg border border-border-default bg-muted/20 px-3 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">What to comment about</p>
                                    <p class="mt-1 text-sm text-text-primary whitespace-pre-line break-words">{{ $campaign->comment_instructions }}</p>
                                </div>
                            @endif
                            <p class="text-sm text-text-secondary">Write your own comment on the video in your own words, using the YouTube channel you linked.</p>
                            @if ($videoUrl)
                                <a href="{{ $videoUrl }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-primary hover:underline">Open the video on YouTube</a>
                            @endif
                            <details class="text-sm text-text-secondary">
                                <summary class="cursor-pointer font-medium text-text-primary">How to copy your comment link</summary>
                                <ol class="mt-2 list-decimal space-y-1 pl-5">
                                    <li>On a computer, click the time under your name on your comment (for example "1 minute ago"). The page address now ends with <span class="font-mono">lc=...</span>. Copy it from the address bar.</li>
                                    <li>On the YouTube app, tap the three dots next to your comment, then <strong>Share</strong> (or <strong>Copy link</strong>) if you see it.</li>
                                    <li>Paste the link below. It must contain <span class="font-mono">lc=</span>.</li>
                                </ol>
                            </details>
                            <form method="POST" action="{{ route('agent.tasks.steps.save', [$participation, 'comment']) }}" class="space-y-3" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)">
                                @csrf
                                <x-dashboard.input name="proof_url" label="Link to your comment" :value="old('proof_url')" placeholder="https://www.youtube.com/watch?v=...&lc=..." required />
                                <x-dashboard.button type="submit" x-bind:disabled="busy">Mark step done</x-dashboard.button>
                                <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
                            </form>
                        </div>
                    @endif
                @endif

                @if ($step->proof_url && ! in_array($step->status, [Step::STATUS_LOCKED, Step::STATUS_ACTIVE], true))
                    @if (preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $step->proof_url))
                        <a href="{{ $step->proof_url }}" target="_blank" rel="noopener" class="inline-block">
                            <img src="{{ $step->proof_url }}" alt="Screenshot" class="max-h-40 rounded-lg border border-border-default" />
                        </a>
                    @else
                        <p class="text-xs text-text-muted break-all">Your link: <a href="{{ $step->proof_url }}" target="_blank" rel="noopener" class="underline">{{ $step->proof_url }}</a></p>
                    @endif
                @endif
                @if ($step->evidence_method === 'timer' && ! in_array($step->status, [Step::STATUS_LOCKED, Step::STATUS_ACTIVE], true))
                    <p class="text-xs text-text-muted">Watch time finished.</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($isStarted && ! $activeStep && $hasLocked)
        <form method="POST" action="{{ route('agent.tasks.proceed', $participation) }}" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)" class="space-y-2">
            @csrf
            <x-dashboard.button type="submit" x-bind:disabled="busy">Proceed to next task</x-dashboard.button>
            <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
        </form>
    @elseif ($isStarted && $allDone)
        <form method="POST" action="{{ route('agent.tasks.submit-task', $participation) }}" data-ajax-form x-data="taskAction" @submit.prevent="submit($event)" class="space-y-2">
            @csrf
            <p class="text-sm text-text-secondary">Every step is done. Submit the task to start the checks.</p>
            <x-dashboard.button type="submit" icon="verified" x-bind:disabled="busy">Submit task</x-dashboard.button>
            <p class="text-sm text-danger" x-show="error" x-text="error" x-cloak></p>
        </form>
    @endif
</div>
