@extends('layouts.dashboard-admin')

@section('title', 'Edit Product')

@section('content')
@php
    $variantRows = old('variants', $product->relationLoaded('variants') && $product->variants->isNotEmpty()
        ? $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => $v->price,
            'description' => $v->description,
        ])->values()->all()
        : []);
    $heroId = old('hero_media_id', $product->hero_media_id);
    $heroPreview = $heroId
        ? \App\Models\MediaAsset::query()->with('variants')->find((int) $heroId)?->thumbnailUrl()
        : null;
@endphp
<x-layout.page
    title="Edit Product"
    subtitle="Fixed platform product — title, description, plan prices, image, featured, and status."
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
        <form method="POST" action="{{ route('admin.platform-products.update', $product) }}" class="w-full space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')

            <p class="text-xs text-text-muted">
                Slug frozen: <span class="font-mono">{{ $product->slug }}</span>
                @if($product->productType)
                    · Service (legacy/CMS): {{ $product->productType->name }}
                @endif
            </p>

            <x-dashboard.select label="Category" name="service_category_id" required>
                @foreach(($serviceCategories ?? []) as $category)
                    <option value="{{ $category->id }}" @selected((int) old('service_category_id', $product->service_category_id) === (int) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </x-dashboard.select>

            <x-dashboard.input label="Title" name="title" :value="old('title', $product->title)" required />
            <x-dashboard.input label="Short description" name="short_description" :value="old('short_description', $product->short_description)" />
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="6" class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm">{{ old('description', $product->description) }}</textarea>
            </div>

            <x-dashboard.select label="Status" name="status" required>
                <option value="published" @selected(old('status', $product->status?->value ?? $product->status) === 'published')>Published (active)</option>
                <option value="draft" @selected(old('status', $product->status?->value ?? $product->status) === 'draft')>Draft (deactivated)</option>
            </x-dashboard.select>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                Featured (show in featured sections on public / user pages)
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_campaign" value="1" @checked(old('is_campaign', $product->is_campaign ?? false))>
                Campaign package (creates a campaign agents can work on after purchase)
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.input
                    label="Agent reward per completion (NGN)"
                    name="agent_reward_per_completion"
                    type="number"
                    step="0.01"
                    min="0"
                    :value="old('agent_reward_per_completion', $product->agent_reward_per_completion)"
                />
                <x-dashboard.input
                    label="Estimated minutes per task"
                    name="estimated_minutes"
                    type="number"
                    min="1"
                    :value="old('estimated_minutes', $product->estimated_minutes)"
                />
            </div>

            <x-dashboard.input
                label="Sort position"
                name="sort_order"
                type="number"
                min="1"
                :max="$siblingMax ?? 1"
                :value="old('sort_order', $product->sort_order)"
                required
            />
            <p class="text-xs text-text-muted">Global position among all platform products (1–{{ $siblingMax ?? 1 }}). Each number is unique; neighbors shift automatically.</p>

            <x-dashboard.media-picker
                name="hero_media_id"
                label="Image"
                hint="Product hero image. Shown full-width without cropping on the product page."
                preview="wide"
                :value="$heroId"
                :preview-url="$heroPreview"
            />

            <div class="space-y-3 rounded-xl border border-border-subtle px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-text-primary">Tutorials</p>
                    <p class="mt-1 text-xs text-text-muted">Shown as a <strong>Watch tutorial</strong> button next to View Demo on the product page, and on My Tools after purchase. Set once here — not per user.</p>
                </div>
                <x-dashboard.input
                    label="Tutorial video URL"
                    name="tutorial_url"
                    type="url"
                    :value="old('tutorial_url', $product->tutorial_url)"
                    placeholder="https://www.youtube.com/watch?v=…"
                />
                <div>
                    <label for="tutorial_description" class="mb-1 block text-sm font-medium text-text-secondary">Tutorial description</label>
                    <textarea
                        id="tutorial_description"
                        name="tutorial_description"
                        rows="3"
                        class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                        placeholder="Short note about what this tutorial covers"
                    >{{ old('tutorial_description', $product->tutorial_description) }}</textarea>
                </div>
            </div>

            @if ($variantRows !== [])
                <div class="space-y-3 rounded-xl border border-border-subtle px-4 py-4">
                    <p class="text-sm font-medium text-text-primary">Plans / variants</p>
                    <p class="text-xs text-text-muted">Variant names are fixed. Set price and an optional description for each plan. The storefront shows the lowest price as “from”.</p>
                    @foreach ($variantRows as $i => $variant)
                        <div class="space-y-2 rounded-xl border border-border-default bg-muted/20 p-3">
                            <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $variant['id'] }}">
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 items-end">
                                <div>
                                    <label class="block text-xs text-text-muted mb-1">Variant</label>
                                    <p class="rounded-xl border border-border-default bg-muted/40 px-3 py-2.5 text-sm">{{ $variant['name'] }}</p>
                                </div>
                                <x-dashboard.input
                                    label="Price (NGN)"
                                    :name="'variants['.$i.'][price]'"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :value="old('variants.'.$i.'.price', $variant['price'])"
                                    required
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-text-muted">Plan description</label>
                                <textarea
                                    name="variants[{{ $i }}][description]"
                                    rows="2"
                                    class="w-full rounded-xl border border-border-default bg-elevated px-3 py-2.5 text-sm"
                                    placeholder="What this plan includes…"
                                >{{ old('variants.'.$i.'.description', $variant['description'] ?? '') }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-amber-600">This product has no variants. Pricing cannot be set until plans exist in the catalog seed.</p>
            @endif

            <div class="flex flex-wrap gap-2 pt-2">
                <x-dashboard.button type="submit" variant="primary" x-bind:disabled="submitting">Save</x-dashboard.button>
                <x-dashboard.button :href="route('admin.platform-products')" variant="secondary">Cancel</x-dashboard.button>
            </div>
        </form>
    </x-dashboard.card>
</x-layout.page>
@endsection
