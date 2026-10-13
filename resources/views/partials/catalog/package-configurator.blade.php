{{--
    Package form shared by the public product page and the dashboard product page.
    Params: $product, $theme ('marketing'|'dashboard').
--}}
@php
    $theme = $theme ?? 'marketing';
    $dash = $theme === 'dashboard';
    $profile = $product->taskProfile();
    $metric = $profile->legacyMetric();
    $variant = $product->pricingVariant();
    $layout = $product->isPurchasable() ? app(\App\Services\Catalog\PackageQuoteService::class)->layout($product) : null;
    $draft = app(\App\Services\Catalog\PurchaseDraftService::class)->get($product->slug);
    $user = auth()->user();
    $canBuy = ! $user || $user->isCreator();
    $needsTargetUrl = $product->requiresTargetUrl();
    $targetIsChannel = $needsTargetUrl && $profile->targetIsChannel();
    $needsChannel = $needsTargetUrl && $profile->needsChannelUrl();
    $needsComment = $needsTargetUrl && $profile->needsCommentInstructions();
    $queryQty = request()->query('qty');
    $initial = [
        'units' => old('units', request()->query('units', $draft['units'] ?? null)),
        'qty' => (array) old('qty', is_array($queryQty) ? $queryQty : ($draft['task_quantities'] ?? [])),
        'targetUrl' => (string) old('target_url', request()->query('target_url', $draft['target_url'] ?? '')),
        'channelUrl' => (string) old('channel_url', request()->query('channel_url', $draft['channel_url'] ?? '')),
        'commentInstructions' => (string) old('comment_instructions', request()->query('comment_instructions', $draft['comment_instructions'] ?? '')),
    ];
    $config = [
        'productSlug' => $product->slug,
        'csrf' => csrf_token(),
        'videoPreviewUrl' => route('services.youtube.video-preview'),
        'channelPreviewUrl' => route('services.youtube.channel-preview'),
        'unitPrice' => $layout['unit_price'] ?? 0,
        'rows' => $layout['rows'] ?? [],
        'initial' => $initial,
        'needsTargetUrl' => $needsTargetUrl,
        'targetIsChannel' => $targetIsChannel,
        'needsChannel' => $needsChannel,
        'needsComment' => $needsComment,
        'commentMax' => \App\Services\Campaigns\CampaignBriefService::COMMENT_INSTRUCTIONS_MAX,
        'revealed' => $errors->any(),
    ];

    $c = $dash ? [
        'card' => 'rounded-xl border border-border-default bg-elevated p-5 sm:p-6 space-y-4',
        'title' => 'text-sm font-semibold text-text-primary',
        'label' => 'block text-sm font-medium text-text-secondary',
        'muted' => 'text-xs text-text-muted',
        'body' => 'text-sm text-text-secondary',
        'strong' => 'text-text-primary',
        'input' => 'w-full rounded-lg border-border-default bg-elevated text-text-primary text-sm',
        'tile' => 'rounded-lg border border-border-default bg-muted/20',
        'error' => 'text-xs text-danger',
        'price' => 'text-3xl font-bold text-primary',
    ] : [
        'card' => 'rounded-xl border border-slate-200 bg-white p-5 space-y-4',
        'title' => 'text-sm font-semibold text-slate-900',
        'label' => 'block text-sm font-medium text-slate-700',
        'muted' => 'text-xs text-slate-500',
        'body' => 'text-sm text-slate-600',
        'strong' => 'text-slate-900',
        'input' => 'w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900',
        'tile' => 'rounded-lg border border-slate-200 bg-slate-50',
        'error' => 'text-xs text-red-600',
        'price' => 'text-2xl sm:text-3xl font-display font-bold text-primary',
    ];
@endphp

@if(! $layout)
    <p class="{{ $c['body'] }}">Pricing for this product is not available yet.</p>
@else
<form
    method="POST"
    action="{{ route('services.configure', $product->slug) }}"
    class="space-y-4"
    data-no-page-loader
    x-data="packageConfigurator(@js($config))"
    @submit="submit($event)"
>
    @csrf

    @if($needsTargetUrl)
        <div class="{{ $c['card'] }}">
            @if($targetIsChannel)
                <div class="space-y-1.5">
                    <label for="pkg-target-channel" class="{{ $c['label'] }}">Your YouTube channel link</label>
                    <input id="pkg-target-channel" type="url" name="target_url" class="{{ $c['input'] }}" placeholder="https://www.youtube.com/@yourchannel" x-model="targetUrl" @input="onTargetChannelInput()" autocomplete="off">
                    @error('target_url')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
                </div>
                @include('partials.catalog._package-channel-card', ['c' => $c])
            @else
                @if($needsChannel)
                    <div class="space-y-1.5">
                        <label for="pkg-channel" class="{{ $c['label'] }}">Your YouTube channel link</label>
                        <input id="pkg-channel" type="url" name="channel_url" class="{{ $c['input'] }}" placeholder="https://www.youtube.com/@yourchannel" x-model="channelUrl" @input="onChannelInput()" autocomplete="off">
                        <p class="{{ $c['muted'] }}">Agents subscribe to this channel. Paste it to see your latest videos, then pick one for the rest of the campaign.</p>
                        @error('channel_url')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
                    </div>
                    @include('partials.catalog._package-channel-card', ['c' => $c])

                    <fieldset class="space-y-2" x-show="channelVideos.length && !customVideo" x-cloak>
                        <legend class="{{ $c['title'] }}">Pick the video for this campaign</legend>
                        <div class="grid grid-cols-2 gap-3">
                            <template x-for="item in channelVideos" :key="item.id">
                                <label class="cursor-pointer {{ $c['tile'] }} overflow-hidden transition" :class="isPicked(item) ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-primary/40'">
                                    <img x-show="item.thumbnail" :src="item.thumbnail" alt="" class="block w-full aspect-video object-cover" loading="lazy" referrerpolicy="no-referrer">
                                    <span class="flex items-start gap-2 p-2">
                                        <input type="radio" name="video_pick" class="mt-0.5 shrink-0 text-primary focus:ring-primary" :value="item.id" :checked="isPicked(item)" @change="pickVideo(item)">
                                        <span class="min-w-0">
                                            <span class="block text-xs font-medium {{ $c['strong'] }} line-clamp-2" x-text="item.title"></span>
                                            <span class="block {{ $c['muted'] }}" x-show="item.duration" x-text="item.duration"></span>
                                        </span>
                                    </span>
                                </label>
                            </template>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 {{ $c['tile'] }} p-3">
                            <input type="radio" name="video_pick" value="other" class="shrink-0 text-primary focus:ring-primary" :checked="customVideo" @change="useDifferentVideo()">
                            <span class="text-sm font-medium {{ $c['strong'] }}">Other video</span>
                            <span class="{{ $c['muted'] }}">Paste a link to a different video</span>
                        </label>
                    </fieldset>
                @endif

                <div class="space-y-1.5" @if($needsChannel) x-show="customVideo" x-cloak @endif>
                    <label for="pkg-video" class="{{ $c['label'] }}">YouTube video link</label>
                    <input id="pkg-video" x-ref="videoInput" type="url" class="{{ $c['input'] }}" placeholder="https://www.youtube.com/watch?v=..." x-model="targetUrl" @input="onVideoInput()" autocomplete="off">
                    <p class="{{ $c['muted'] }}">Public and unlisted videos work. Live streams and premieres that have not started do not.</p>
                    @if($needsChannel)
                        <button type="button" class="text-sm font-medium text-primary hover:underline" x-show="channelVideos.length" x-cloak @click="backToChannelVideos()">Choose from your latest videos instead</button>
                    @endif
                </div>
                <input type="hidden" name="target_url" :value="targetUrl">
                @error('target_url')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror

                <p class="{{ $c['muted'] }}" x-show="videoLoading" x-cloak>Checking your video...</p>
                <div x-show="video && video.video" x-cloak class="flex gap-3 {{ $c['tile'] }} p-3">
                    <img x-show="video?.video?.thumbnail" :src="video?.video?.thumbnail" alt="" class="w-32 shrink-0 aspect-video rounded object-cover" referrerpolicy="no-referrer">
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-medium {{ $c['strong'] }} line-clamp-2" x-text="video?.video?.title"></p>
                        <p class="{{ $c['muted'] }}" x-text="video?.video?.channel_title"></p>
                        <p class="{{ $c['muted'] }}" x-show="video?.video?.duration" x-text="video?.video?.duration"></p>
                    </div>
                </div>
                <p x-show="video && video.message" x-cloak class="text-xs" :class="video?.allowed === false ? '{{ $dash ? 'text-danger' : 'text-red-600' }}' : '{{ $dash ? 'text-text-muted' : 'text-slate-500' }}'" x-text="video?.message"></p>
                <p x-show="channelMismatch" x-cloak class="{{ $c['error'] }}">That video is not on the channel above. Pick a video from the same channel.</p>

                @if($needsComment)
                    <div class="space-y-1.5" x-show="showDetails" x-cloak>
                        <label for="pkg-comment" class="{{ $c['label'] }}">What should agents comment about?</label>
                        <textarea id="pkg-comment" name="comment_instructions" rows="3" maxlength="{{ $config['commentMax'] }}" class="{{ $c['input'] }}" x-model="commentInstructions" placeholder="For example: say what you liked about the video."></textarea>
                        <p class="{{ $c['muted'] }}">Each agent writes their own comment. <span x-text="count(commentInstructions.length) + ' / ' + count(commentMax)"></span></p>
                        @error('comment_instructions')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
                    </div>
                @endif
            @endif
        </div>

        <p class="{{ $c['body'] }}" x-show="!showDetails" x-cloak>Paste your YouTube link above to choose quantities and see your total.</p>
    @endif

    {{-- Quantities --}}
    <div class="{{ $c['card'] }}" @if($needsTargetUrl) x-show="showDetails" x-cloak @endif>
        <div>
            <p class="text-lg font-semibold {{ $c['strong'] }}">{{ $variant->pricingLabel($metric) }}</p>
            @if($profile->isMultiTask())
                <p class="mt-1 {{ $c['muted'] }}">Package from {{ money((float) $layout['package_price'], 2) }}, which includes the minimum of every service below.</p>
            @endif
        </div>

        <template x-for="row in rows" :key="row.task">
            <div class="space-y-1.5">
                <label class="{{ $c['label'] }}" :for="'pkg-qty-' + row.task">
                    <span x-text="row.label"></span>
                    <span class="{{ $c['muted'] }}" x-show="row.primary && companionRows.length" x-cloak>(main service)</span>
                </label>
                <input
                    :id="'pkg-qty-' + row.task"
                    type="number"
                    step="1"
                    inputmode="numeric"
                    class="{{ $c['input'] }} max-w-xs"
                    :name="row.primary ? 'units' : 'qty[' + row.task + ']'"
                    :min="row.min"
                    :max="row.max"
                    x-model.number="quantities[row.task]"
                >
                <p class="{{ $c['muted'] }}">
                    <span x-text="'Minimum ' + count(row.min) + ' · Maximum ' + count(row.max)"></span>
                    <span x-show="!row.primary && extraFor(row) > 0" x-cloak x-text="' · ' + count(extraFor(row)) + ' extra at ' + money(unitPrice, 4) + ' each'"></span>
                </p>
                <p class="{{ $c['muted'] }}" x-show="row.hint" x-text="row.hint"></p>
                <p class="{{ $c['error'] }}" x-show="!rowValid(row)" x-cloak x-text="'Enter a whole number from ' + count(row.min) + ' to ' + count(row.max) + '.'"></p>
            </div>
        </template>
        @error('units')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
        @foreach($layout['rows'] as $row)
            @error('qty.'.$row['task'])<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
        @endforeach
        @error('qty')<p class="{{ $c['error'] }}">{{ $message }}</p>@enderror
    </div>

    {{-- Total and action --}}
    <div class="{{ $c['card'] }}" @if($needsTargetUrl) x-show="showDetails" x-cloak @endif>
        <div>
            <span class="text-[11px] font-medium uppercase tracking-widest {{ $dash ? 'text-text-muted' : 'text-slate-500' }} block">Total</span>
            <p class="mt-1 {{ $c['price'] }}" x-text="money(quantitiesValid ? total : 0)"></p>
            <template x-if="quantitiesValid">
                <p class="mt-1 {{ $c['muted'] }}">
                    <template x-for="row in rows" :key="'sum-' + row.task">
                        <span class="block" x-text="count(quantities[row.task]) + ' ' + row.label.toLowerCase()"></span>
                    </template>
                    <span class="block" x-show="extraUnits > 0" x-text="count(extraUnits) + ' extra units above the package minimums'"></span>
                </p>
            </template>
        </div>

        @if($canBuy)
            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                x-bind:disabled="!canSubmit"
            >
                <span x-show="!submitting">{{ $user ? 'Continue to checkout' : 'Log in to buy' }}</span>
                <span x-show="submitting" x-cloak>Please wait...</span>
            </button>
            <p class="{{ $c['muted'] }}" x-show="blockedReason" x-cloak x-text="blockedReason"></p>
            @guest
                <p class="{{ $c['muted'] }}">Your package is saved while you log in or create a Creator account.</p>
            @endguest
        @else
            <p class="{{ $c['body'] }}">You're signed in as {{ $user->isAgent() ? 'an Agent' : 'an admin' }}. Buying services needs a Creator account.</p>
        @endif
    </div>
</form>
@endif
