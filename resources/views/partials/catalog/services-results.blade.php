@php
    $youtubeCatalog = $youtubeCatalog ?? ['featured' => null, 'others' => collect()];
    $hasYouTube = is_array($youtubeCatalog['featured'] ?? null) || collect($youtubeCatalog['others'] ?? [])->isNotEmpty();
@endphp

@if($hasYouTube)
    @include('partials.catalog.services-youtube', ['youtubeCatalog' => $youtubeCatalog])
@else
    <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
        <p class="text-slate-600 mb-4">No YouTube packages match your filters.</p>
        <button
            type="button"
            data-services-action="reset"
            class="inline-flex text-sm font-semibold text-primary hover:underline"
        >
            Clear filters
        </button>
    </div>
@endif