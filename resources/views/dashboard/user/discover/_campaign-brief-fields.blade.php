@php
    $briefProfile = $product->taskProfile();
@endphp
<div class="space-y-4">
    <div>
        <p class="text-sm font-medium text-text-primary">What each agent does</p>
        <ol class="mt-2 space-y-1 text-sm text-text-secondary list-decimal list-inside">
            @foreach($briefProfile->tasks() as $task)
                <li>{{ $task->label() }}</li>
            @endforeach
        </ol>
        <p class="mt-2 text-xs text-text-muted">Each unit is one new agent completing all tasks, in this order.</p>
    </div>

    @if($briefProfile->needsChannelUrl())
        <div>
            <label for="campaign-channel-url" class="block text-sm font-medium text-text-primary">
                Channel URL <span class="text-danger">*</span>
            </label>
            <input
                id="campaign-channel-url"
                type="url"
                @if($bindAlpine ?? true) x-model="channelUrl" @endif
                @unless($bindAlpine ?? true) name="channel_url" @endunless
                value="{{ $initialChannelUrl ?? '' }}"
                placeholder="https://www.youtube.com/@yourchannel"
                class="mt-2 w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm"
            >
            <p class="mt-1 text-xs text-text-muted">The channel agents will subscribe to. The video above must be on this channel.</p>
            @error('channel_url')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if($briefProfile->needsCommentInstructions())
        <div>
            <label for="campaign-comment-instructions" class="block text-sm font-medium text-text-primary">
                Comment instructions <span class="text-danger">*</span>
            </label>
            <textarea
                id="campaign-comment-instructions"
                rows="4"
                maxlength="{{ \App\Services\Campaigns\CampaignBriefService::COMMENT_INSTRUCTIONS_MAX }}"
                @if($bindAlpine ?? true) x-model="commentInstructions" @endif
                @unless($bindAlpine ?? true) name="comment_instructions" @endunless
                placeholder="For example: share what you liked most about the video."
                class="mt-2 w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm"
            >{{ $initialCommentInstructions ?? '' }}</textarea>
            <p class="mt-1 text-xs text-text-muted">
                Tell agents what their comment should be about. Each agent writes their own comment in their own words.
                @if($bindAlpine ?? true)
                    <span x-text="'(' + String(commentInstructions || '').length + ' / {{ \App\Services\Campaigns\CampaignBriefService::COMMENT_INSTRUCTIONS_MAX }})'"></span>
                @endif
            </p>
            @error('comment_instructions')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
