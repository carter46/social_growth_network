@extends('layouts.dashboard-admin')

@section('title', 'Edit Product')

@section('content')
@php
    $taskProfile = $product->taskProfile();
    $engagementMetric = $taskProfile->legacyMetric();
    $savedOrder = \App\Support\ProductTaskProfile::normalizeOrder(
        \App\Support\ProductTaskProfile::allowedTasks($product->slug),
        old('task_order', $product->task_order),
    ) ?? $taskProfile->tasks();
    $taskOrderPayload = array_map(fn ($task) => ['value' => $task->value, 'label' => $task->label()], $savedOrder);
    $pricingVariant = $product->pricingVariant();
    $labelVariant = $pricingVariant ?? new \App\Models\PlatformProductVariant();
    $unitSingular = $labelVariant->resolveUnitLabel($engagementMetric);
    $unitPlural = $labelVariant->resolveUnitLabelPlural($engagementMetric);
    $defaultUnits = \App\Models\PlatformProductVariant::REFERENCE_UNITS;
    $pricing = [
        'min_units' => old('pricing.min_units', $pricingVariant?->min_units ?? $defaultUnits),
        'max_units' => old('pricing.max_units', $pricingVariant?->max_units ?? \App\Models\PlatformProductVariant::DEFAULT_MAX_UNITS),
        'pricing_units' => old('pricing.pricing_units', $pricingVariant?->pricing_units ?? $defaultUnits),
        'price' => old('pricing.price', $pricingVariant?->price ?? ''),
        'reward_percent' => old('agent_reward_percent', $product->agent_reward_percent),
    ];
    $heroId = old('hero_media_id', $product->hero_media_id);
    $heroPreview = $heroId
        ? \App\Models\MediaAsset::query()->with('variants')->find((int) $heroId)?->thumbnailUrl()
        : null;
    $minutesLabel = $taskProfile->needsWatchMinutes()
        ? 'Watch time (minutes)'
        : 'Estimated minutes per task';
    $minutesHint = $taskProfile->needsWatchMinutes()
        ? 'Minutes the agent must keep the watch timer running. Not a guarantee of platform watch hours or views.'
        : 'Shown to agents as estimated time, not a retake lock.';
    $inputClass = 'w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm';
@endphp
<x-layout.page
    title="Edit Product"
    subtitle="Platform product: title, description, pricing, agent reward, image, featured and status."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Products', route('admin.platform-products')],
        ['Edit', null],
    ]"
>
    <x-dashboard.card>
        @if (session('status'))
            <x-dashboard.alert type="success" class="mb-4">{{ session('status') }}</x-dashboard.alert>
        @endif
        @if (session('error'))
            <x-dashboard.alert type="danger" class="mb-4">{{ session('error') }}</x-dashboard.alert>
        @endif
        <form
            method="POST"
            action="{{ route('admin.platform-products.update', $product) }}"
            class="w-full space-y-4"
            x-data="{
                submitting: false,
                pricing: @js($pricing),
                unitSingular: @js($unitSingular),
                unitPlural: @js($unitPlural),
                unitPrice() {
                    const price = Number(this.pricing.price);
                    const units = Number(this.pricing.pricing_units);
                    if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(units) || units < 1) return null;
                    return Math.floor((price / units) * 10000) / 10000;
                },
                agentReward() {
                    const unit = this.unitPrice();
                    const pct = Number(this.pricing.reward_percent);
                    if (unit === null || !Number.isFinite(pct) || pct <= 0) return null;
                    return Math.floor(unit * Math.min(100, pct)) / 100;
                },
                exampleUnits() {
                    return Math.max(1, Math.round(Number(this.pricing.min_units) || 1));
                },
                formatMoney(n) {
                    if (n === null || n === undefined || !Number.isFinite(n)) return '-';
                    return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 4 });
                }
            }"
            @submit="submitting = true"
        >
            @csrf
            @method('PUT')

            <p class="text-xs text-text-muted">
                Slug frozen: <span class="font-mono">{{ $product->slug }}</span>
                · Category frozen:
                <span class="font-medium text-text-secondary">{{ $product->serviceCategory?->name ?? '-' }}</span>
                @if($product->productType)
                    · Service (legacy/CMS): {{ $product->productType->name }}
                @endif
                · Sort #{{ max(1, (int) $product->sort_order) }} (auto-managed)
            </p>

            <x-dashboard.input label="Title" name="title" :value="old('title', $product->title)" required />
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="6" class="{{ $inputClass }}">{{ old('description', $product->description) }}</textarea>
            </div>

            <x-dashboard.select label="Status" name="status" required>
                <option value="published" @selected(old('status', $product->status?->value ?? $product->status) === 'published')>Published (active)</option>
                <option value="draft" @selected(old('status', $product->status?->value ?? $product->status) === 'draft')>Draft (deactivated)</option>
            </x-dashboard.select>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                Featured (show in featured sections on public / user pages)
            </label>

            <div class="space-y-4 rounded-xl border border-border-subtle px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-text-primary">Pricing</p>
                    <p class="text-xs text-text-muted">
                        Creators enter any quantity between the minimum and maximum purchase. They pay the unit price for every {{ $unitSingular }}.
                    </p>
                </div>

                @error('pricing')
                    <x-dashboard.alert type="danger">{{ $message }}</x-dashboard.alert>
                @enderror

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Minimum purchase ({{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[min_units]" x-model="pricing.min_units" class="{{ $inputClass }}" required>
                        @error('pricing.min_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Maximum purchase ({{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[max_units]" x-model="pricing.max_units" class="{{ $inputClass }}" required>
                        @error('pricing.max_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Pricing unit (price is per this many {{ $unitPlural }})</label>
                        <input type="number" min="1" step="1" name="pricing[pricing_units]" x-model="pricing.pricing_units" class="{{ $inputClass }}" required>
                        @error('pricing.pricing_units')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">
                            Price per <span x-text="Number(pricing.pricing_units || 0).toLocaleString()"></span> {{ $unitPlural }} (NGN)
                        </label>
                        <input type="number" min="0.01" step="0.01" name="pricing[price]" x-model="pricing.price" class="{{ $inputClass }}" required>
                        @error('pricing.price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Agent reward (% of the unit price)</label>
                        <input type="number" min="0.01" max="100" step="0.01" name="agent_reward_percent" x-model="pricing.reward_percent" class="{{ $inputClass }}" required>
                        @error('agent_reward_percent')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-text-muted">Each agent completes one {{ $unitSingular }} per campaign. The reward is locked into each campaign at purchase.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-border-default bg-elevated/60 px-3 py-2 text-xs text-text-secondary" x-show="unitPrice() !== null" x-cloak>
                    <p>
                        Unit price: <span class="font-semibold text-text-primary" x-text="formatMoney(unitPrice())"></span> per {{ $unitSingular }}
                        · Agent earns <span class="font-semibold text-text-primary" x-text="formatMoney(agentReward())"></span> per completed {{ $unitSingular }}
                    </p>
                    <p class="mt-1">
                        Example: <span x-text="exampleUnits().toLocaleString()"></span> {{ $unitPlural }} =
                        <span class="font-semibold text-text-primary" x-text="formatMoney(Math.round(exampleUnits() * unitPrice() * 100) / 100)"></span>
                    </p>
                </div>
            </div>

            <div
                class="space-y-4 rounded-xl border border-border-subtle px-4 py-4"
                x-data="{
                    order: @js($taskOrderPayload),
                    dragIndex: null,
                    watch: @js((string) old('estimated_minutes', $product->estimated_minutes)),
                    complete: @js((string) old('completion_minutes', $product->completion_minutes)),
                    needsWatch: @js($taskProfile->needsWatchMinutes()),
                    move(from, to) {
                        if (from === null || to < 0 || to >= this.order.length || from === to) return;
                        const [task] = this.order.splice(from, 1);
                        this.order.splice(to, 0, task);
                    },
                    get completionTooShort() {
                        const w = Number(this.watch), c = Number(this.complete);
                        return this.needsWatch && w > 0 && c > 0 && c <= w;
                    },
                }"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium text-text-primary">Tasks and timing</p>
                    @foreach($product->taskSetupIssues() as $issue)
                        <x-dashboard.badge status="danger">{{ $issue }}</x-dashboard.badge>
                    @endforeach
                </div>

                <div>
                    <p class="text-xs text-text-muted">
                        Tasks for this product are fixed{{ count($taskOrderPayload) > 1 ? '. Drag them, or use the arrows, to set the order agents follow.' : '.' }}
                        New orders use the saved order. Existing campaigns keep the order they were bought with.
                    </p>
                    <ol class="mt-2 space-y-2">
                        <template x-for="(task, index) in order" :key="task.value">
                            <li
                                class="flex items-center justify-between gap-3 rounded-lg border border-border-default bg-elevated px-3 py-2 text-sm"
                                :class="dragIndex === index ? 'opacity-60' : ''"
                                @if(count($taskOrderPayload) > 1)
                                    draggable="true"
                                    @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'"
                                    @dragover.prevent
                                    @drop.prevent="move(dragIndex, index); dragIndex = null"
                                    @dragend="dragIndex = null"
                                @endif
                            >
                                <span class="flex items-center gap-2">
                                    @if(count($taskOrderPayload) > 1)
                                        <span class="cursor-grab select-none text-text-muted" aria-hidden="true">⋮⋮</span>
                                    @endif
                                    <span class="font-medium text-text-primary" x-text="(index + 1) + '. ' + task.label"></span>
                                </span>
                                @if(count($taskOrderPayload) > 1)
                                    <span class="flex items-center gap-1">
                                        <button type="button" class="rounded border border-border-default px-2 py-0.5 text-xs disabled:opacity-40" :disabled="index === 0" @click="move(index, index - 1)" aria-label="Move up">↑</button>
                                        <button type="button" class="rounded border border-border-default px-2 py-0.5 text-xs disabled:opacity-40" :disabled="index === order.length - 1" @click="move(index, index + 1)" aria-label="Move down">↓</button>
                                    </span>
                                    <input type="hidden" name="task_order[]" :value="task.value">
                                @endif
                            </li>
                        </template>
                    </ol>
                    @error('task_order')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">{{ $minutesLabel }}@if($taskProfile->needsWatchMinutes()) <span class="text-danger">*</span>@endif</label>
                        <input type="number" min="1" max="10080" step="1" name="estimated_minutes" x-model="watch" class="{{ $inputClass }}" @required($taskProfile->needsWatchMinutes())>
                        <p class="mt-1 text-xs text-text-muted">{{ $minutesHint }}</p>
                        @error('estimated_minutes')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-text-muted">Time to complete (minutes)@if($taskProfile->requiresCompletionTime()) <span class="text-danger">*</span>@endif</label>
                        <input type="number" min="1" max="10080" step="1" name="completion_minutes" x-model="complete" class="{{ $inputClass }}" @required($taskProfile->requiresCompletionTime())>
                        <p class="mt-1 text-xs text-text-muted">
                            How long an agent has to finish every step after starting. If time runs out, the task is cancelled for that agent and the slot opens again.
                            @if($taskProfile->needsWatchMinutes()) Must be longer than the watch time. @endif
                            @unless($taskProfile->requiresCompletionTime()) Optional for this product. @endunless
                        </p>
                        <p class="mt-1 text-xs text-danger" x-show="completionTooShort" x-cloak>Time to complete must be longer than the watch time.</p>
                        @error('completion_minutes')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <x-dashboard.media-picker
                name="hero_media_id"
                label="Image"
                hint="Product image shown on the product page (single image, not a gallery)."
                preview="wide"
                :value="$heroId"
                :preview-url="$heroPreview"
            />

            <div class="flex flex-wrap gap-2 pt-2">
                <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save</x-dashboard.button>
                <x-dashboard.button :href="route('admin.platform-products')" variant="secondary">Cancel</x-dashboard.button>
            </div>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
