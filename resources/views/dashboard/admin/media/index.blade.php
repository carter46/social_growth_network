@extends('layouts.dashboard-admin')

@section('title', 'Media Library')

@section('content')
<div
    x-data="{
        selected: [],
        usageMap: @js($assets->mapWithKeys(fn ($a) => [$a->id => (int) $a->usages_count])),
        get selectedInUse() {
            return this.selected.filter((id) => (this.usageMap[id] || 0) > 0).length;
        },
        usagesOpen: false,
        usagesLoading: false,
        usagesError: null,
        usages: [],
        usagesCount: 0,
        usagesTitle: '',
        toggleAll(event, ids) {
            this.selected = event.target.checked ? [...ids] : [];
        },
        async showUsages(id, name, url) {
            this.usagesOpen = true;
            this.usagesLoading = true;
            this.usagesError = null;
            this.usages = [];
            this.usagesCount = 0;
            this.usagesTitle = name || ('Media #' + id);
            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const payload = await res.json();
                if (!res.ok) {
                    throw new Error(payload.message || 'Failed to load usages');
                }
                this.usages = payload.data || [];
                this.usagesCount = payload.count ?? this.usages.length;
            } catch (e) {
                this.usagesError = e.message || 'Failed to load usages';
            } finally {
                this.usagesLoading = false;
            }
        },
    }"
>
<x-layout.page
    title="Media Library"
    subtitle="Upload and manage platform images used across the catalog and content."
    width="full"
    :breadcrumb="[
        ['Admin', route('admin')],
        ['Media Library', null],
    ]"
>
    <x-slot:actions>
        <form
            method="POST"
            action="{{ route('admin.media.store') }}"
            enctype="multipart/form-data"
            class="flex flex-wrap items-center gap-2"
        >
            @csrf
            <input
                type="file"
                name="files[]"
                accept="image/jpeg,image/png,image/webp,image/gif"
                multiple
                required
                class="block w-full max-w-xs text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-white"
            >
            <x-dashboard.button type="submit" size="sm" icon="plus">Upload</x-dashboard.button>
        </form>
    </x-slot:actions>

    @if ($errors->any())
        <x-dashboard.alert type="danger" class="mb-4">
            {{ $errors->first() }}
        </x-dashboard.alert>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2" x-show="selected.length" x-cloak>
        <form
            x-ref="bulkDestroyForm"
            method="POST"
            action="{{ route('admin.media.bulk-destroy') }}"
            class="hidden"
        >
            @csrf
            @method('DELETE')
            <input type="hidden" name="force" value="1">
            <template x-for="id in selected" :key="'bulk-' + id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>
        <x-dashboard.button
            type="button"
            variant="danger"
            size="sm"
            @click="$dispatch('open-modal', 'bulk-delete-media')"
        >
            Delete selected (<span x-text="selected.length"></span>)
        </x-dashboard.button>
        <span class="text-xs text-warning" x-show="selectedInUse > 0" x-cloak>
            <span x-text="selectedInUse"></span> selected <span x-text="selectedInUse === 1 ? 'image is' : 'images are'"></span> in use.
        </span>
    </div>

    <div @modal-confirmed.window="if ($event.detail === 'bulk-delete-media') $refs.bulkDestroyForm?.submit()">
        <x-dashboard.modal
            name="bulk-delete-media"
            title="Delete selected media?"
            variant="danger"
            confirm-label="Delete selected"
        >
            <p>This permanently deletes <span x-text="selected.length"></span> selected file(s). This cannot be undone.</p>
            <p class="mt-2 font-medium text-danger" x-show="selectedInUse > 0" x-cloak>
                Warning: <span x-text="selectedInUse"></span> of them <span x-text="selectedInUse === 1 ? 'is' : 'are'"></span> in use on products, categories, pages or branding.
                They will be removed from those places, which will show no image until you pick a new one.
            </p>
        </x-dashboard.modal>
    </div>

    <x-dashboard.table
        :empty="$assets->isEmpty()"
        empty-title="No media yet"
        empty-description="Upload images to use across products, categories, and pages."
        empty-icon="listings"
        striped
    >
        <x-slot:filters>
            <x-dashboard.filter-bar>
                <form method="GET" class="contents">
                    <div class="min-w-[12rem] flex-1">
                        <x-dashboard.input name="q" type="search" :value="$q" placeholder="Search by name or UUID..." />
                    </div>
                    <div class="min-w-[10rem]">
                        <x-dashboard.select name="type">
                            <option value="">All types</option>
                            @foreach ($types as $mediaType)
                                <option value="{{ $mediaType->value }}" @selected($type === $mediaType->value)>
                                    {{ $mediaType->label() }}
                                </option>
                            @endforeach
                        </x-dashboard.select>
                    </div>
                    <x-dashboard.button type="submit" variant="secondary" size="md">Filter</x-dashboard.button>
                </form>
            </x-dashboard.filter-bar>
        </x-slot:filters>

        <x-slot:head>
            <x-dashboard.th>
                <input
                    type="checkbox"
                    class="rounded border-border-default"
                    @change="toggleAll($event, @js($assets->pluck('id')->values()))"
                    :checked="selected.length && selected.length === {{ $assets->count() }}"
                >
            </x-dashboard.th>
            <x-dashboard.th>Preview</x-dashboard.th>
            <x-dashboard.th>Name</x-dashboard.th>
            <x-dashboard.th>Type</x-dashboard.th>
            <x-dashboard.th>Size</x-dashboard.th>
            <x-dashboard.th>Usages</x-dashboard.th>
            <x-dashboard.th>Actions</x-dashboard.th>
        </x-slot:head>

        @foreach ($assets as $asset)
            <tr class="hover:bg-muted/50">
                <x-dashboard.td>
                    <input
                        type="checkbox"
                        class="rounded border-border-default"
                        value="{{ $asset->id }}"
                        x-model.number="selected"
                    >
                </x-dashboard.td>
                <x-dashboard.td>
                    @if ($asset->thumbnailUrl())
                        <img
                            src="{{ $asset->thumbnailUrl() }}"
                            alt="{{ $asset->alt ?: $asset->original_name }}"
                            class="h-12 w-12 rounded-lg object-cover"
                        >
                    @else
                        <span class="text-text-muted text-xs">—</span>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <div class="font-medium text-text-primary">{{ $asset->original_name }}</div>
                    <div class="text-xs text-text-muted font-mono">{{ $asset->uuid }}</div>
                    @if ($asset->alt)
                        <div class="text-xs text-text-muted mt-0.5">Alt: {{ $asset->alt }}</div>
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>{{ $asset->type?->label() ?? $asset->type }}</x-dashboard.td>
                <x-dashboard.td class="text-xs text-text-muted">
                    @if ($asset->width && $asset->height)
                        {{ $asset->width }}×{{ $asset->height }}
                    @else
                        —
                    @endif
                </x-dashboard.td>
                <x-dashboard.td>
                    <button
                        type="button"
                        class="text-sm text-primary hover:underline focus-ring rounded"
                        @click="showUsages({{ $asset->id }}, @js($asset->original_name), @js(route('admin.media.usages', $asset)))"
                    >
                        {{ number_format($asset->usages_count) }}
                    </button>
                </x-dashboard.td>
                <x-dashboard.td>
                    <x-dashboard.row-actions>
                        <x-dashboard.menu-item
                            type="button"
                            @click="showUsages({{ $asset->id }}, @js($asset->original_name), @js(route('admin.media.usages', $asset)))"
                        >
                            View usages
                        </x-dashboard.menu-item>
                        <x-dashboard.menu-item type="button" @click="$dispatch('open-modal', 'edit-alt-{{ $asset->id }}')">
                            Edit alt
                        </x-dashboard.menu-item>
                        @if ($asset->usages_count > 0)
                            <x-dashboard.menu-item type="button" @click="$dispatch('open-modal', 'replace-media-{{ $asset->id }}')">
                                Replace
                            </x-dashboard.menu-item>
                        @endif
                        <x-dashboard.menu-item type="button" variant="danger" @click="$dispatch('open-modal', 'delete-media-{{ $asset->id }}')">
                            Delete
                        </x-dashboard.menu-item>
                    </x-dashboard.row-actions>

                    <x-dashboard.modal
                        name="edit-alt-{{ $asset->id }}"
                        title="Edit alt text"
                        confirm-label="Save"
                        :form-action="route('admin.media.update', $asset)"
                        method="PATCH"
                    >
                        Update the accessible alt text for this media asset.
                        <x-slot:form>
                            <x-dashboard.input
                                label="Alt text"
                                name="alt"
                                :value="old('alt', $asset->alt)"
                                placeholder="Describe the image"
                            />
                        </x-slot:form>
                    </x-dashboard.modal>

                    @if ($asset->usages_count > 0)
                        <x-dashboard.modal
                            name="replace-media-{{ $asset->id }}"
                            title="Replace media?"
                            confirm-label="Replace"
                            :form-action="route('admin.media.replace', $asset)"
                            method="POST"
                        >
                            Replace all usages of this asset with another media ID.
                            <x-slot:form>
                                <x-dashboard.input
                                    label="New media ID"
                                    name="new_media_id"
                                    type="number"
                                    min="1"
                                    required
                                    placeholder="e.g. 42"
                                />
                            </x-slot:form>
                        </x-dashboard.modal>
                    @endif
                    <x-dashboard.modal
                        name="delete-media-{{ $asset->id }}"
                        title="{{ $asset->usages_count > 0 ? 'This image is in use' : 'Delete this media?' }}"
                        variant="danger"
                        confirm-label="{{ $asset->usages_count > 0 ? 'Delete anyway' : 'Delete' }}"
                        :form-action="route('admin.media.destroy', $asset)"
                        method="DELETE"
                    >
                        This permanently deletes {{ $asset->original_name }}. This cannot be undone.
                        @if ($asset->usages_count > 0)
                            <p class="mt-2 font-medium text-danger">
                                Warning: it is used in {{ number_format($asset->usages_count) }} {{ \Illuminate\Support\Str::plural('place', $asset->usages_count) }}.
                                It will be removed from those places, which will show no image until you pick a new one.
                            </p>
                        @endif
                        <x-slot:form>
                            @if ($asset->usages_count > 0)
                                <input type="hidden" name="force" value="1">
                            @endif
                        </x-slot:form>
                    </x-dashboard.modal>
                </x-dashboard.td>
            </tr>
        @endforeach
    </x-dashboard.table>

    <x-slot:pagination>
        <x-dashboard.pagination :paginator="$assets" />
    </x-slot:pagination>
</x-layout.page>

    <div
        x-show="usagesOpen"
        x-cloak
        class="fixed inset-0 z-[90] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="media-usages-title"
    >
        <div class="absolute inset-0 bg-overlay backdrop-blur-[2px]" @click="usagesOpen = false"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-border-default bg-elevated p-6 shadow-panel" @click.stop>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="media-usages-title" class="text-lg font-semibold text-text-primary">Usages</h2>
                    <p class="mt-1 text-sm text-text-secondary" x-text="usagesTitle"></p>
                </div>
                <button type="button" class="rounded-lg p-2 text-text-muted hover:bg-muted focus-ring" @click="usagesOpen = false" aria-label="Close">
                    <x-ui.icon name="x" class="h-4 w-4" />
                </button>
            </div>
            <div class="mt-4 max-h-80 overflow-y-auto text-sm">
                <p x-show="usagesLoading" class="text-text-muted" x-cloak>Loading…</p>
                <p x-show="usagesError" class="text-danger" x-text="usagesError" x-cloak></p>
                <p x-show="!usagesLoading && !usagesError && usages.length === 0" class="text-text-muted" x-cloak>Not used anywhere.</p>
                <ul class="space-y-2" x-show="!usagesLoading && usages.length" x-cloak>
                    <template x-for="row in usages" :key="row.id">
                        <li class="rounded-lg border border-border-default bg-surface px-3 py-2">
                            <div class="font-medium text-text-primary" x-text="row.field"></div>
                            <div class="text-xs text-text-muted" x-text="(row.usable_type || '') + ' #' + row.usable_id"></div>
                        </li>
                    </template>
                </ul>
            </div>
            <p class="mt-3 text-xs text-text-muted" x-show="usagesCount" x-cloak>
                <span x-text="usagesCount"></span> usage(s)
            </p>
        </div>
    </div>
</div>
@endsection
