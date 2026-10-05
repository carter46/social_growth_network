<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\MediaUploadService;
use App\Services\Media\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MediaLibraryController extends Controller
{
    public function __construct(
        private MediaUploadService $uploads,
        private MediaUsageService $usages,
    ) {}

    public function index(Request $request): View
    {
        $assets = $this->filteredQuery($request)
            ->with(['variants', 'uploader'])
            ->withCount('usages')
            ->paginate(24)
            ->withQueryString();

        return view('dashboard.admin.media.index', [
            'assets' => $assets,
            'types' => MediaType::cases(),
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
        ]);
    }

    public function jsonIndex(Request $request): JsonResponse
    {
        $assets = $this->filteredQuery($request)
            ->with('variants')
            ->paginate(min(48, max(1, (int) $request->integer('per_page', 24))))
            ->withQueryString();

        return response()->json([
            'data' => $assets->getCollection()->map(fn (MediaAsset $asset) => $this->serializeAsset($asset))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $maxKb = (int) config('media.max_upload_size_kb', 2048);
        $mimes = implode(',', (array) config('media.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp', 'gif']));

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
            'keep_original' => ['sometimes', 'boolean'],
        ]);

        $keepOriginal = $request->has('keep_original')
            ? $request->boolean('keep_original')
            : null;

        $created = [];
        $errors = [];

        foreach ($request->file('files', []) as $index => $file) {
            try {
                $created[] = $this->serializeAsset(
                    $this->uploads->storeImage($file, $request->user(), $keepOriginal)
                );
            } catch (ValidationException $e) {
                $messages = collect($e->errors())->flatten()->all();
                $errors["files.{$index}"] = $messages;
            }
        }

        if ($errors !== [] && $created === []) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json([
                'assets' => $created,
                'data' => $created,
                'errors' => $errors === [] ? null : $errors,
            ], $created === [] ? 422 : 201);
        }

        return redirect()
            ->route('admin.media')
            ->with('status', __(':count media file(s) uploaded.', ['count' => count($created)]))
            ->with('upload_errors', $errors === [] ? null : $errors);
    }

    public function update(Request $request, MediaAsset $mediaAsset): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:500'],
            'original_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('alt', $data)) {
            $mediaAsset->alt = $data['alt'] ?: null;
        }
        if (! empty($data['original_name'])) {
            $mediaAsset->original_name = $data['original_name'];
        }
        $mediaAsset->save();

        Log::info('media.update', [
            'media_asset_id' => $mediaAsset->id,
            'fields' => array_keys($data),
        ]);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('Media updated.'),
                'asset' => $this->serializeAsset($mediaAsset->fresh('variants')),
            ]);
        }

        return redirect()
            ->route('admin.media')
            ->with('status', __('Media updated.'));
    }

    public function destroy(Request $request, MediaAsset $mediaAsset): JsonResponse|RedirectResponse
    {
        $count = $this->usages->usageCount($mediaAsset->id);

        if ($count > 0 && ! $request->boolean('force')) {
            $message = __('This media is used in :count place(s). Confirm the in-use warning to delete it.', ['count' => $count]);

            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'usage_count' => $count,
                ], 422);
            }

            return redirect()
                ->route('admin.media')
                ->withErrors(['media' => $message]);
        }

        $mediaId = (int) $mediaAsset->id;
        if ($count > 0) {
            $this->usages->detachAsset($mediaAsset);
        }
        $mediaAsset->purgeFiles();
        $mediaAsset->forceDelete();

        Log::info('media.delete', ['media_asset_id' => $mediaId, 'usages_removed' => $count]);

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => __('Media deleted.')]);
        }

        return redirect()
            ->route('admin.media')
            ->with('status', __('Media deleted.'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:media_assets,id'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $force = $request->boolean('force');
        $deleted = 0;
        $skipped = 0;
        $detached = 0;

        foreach ($data['ids'] as $id) {
            $asset = MediaAsset::query()->find($id);
            if (! $asset) {
                continue;
            }

            if ($this->usages->usageCount($asset->id) > 0) {
                if (! $force) {
                    $skipped++;
                    continue;
                }
                $this->usages->detachAsset($asset);
                $detached++;
            }

            $asset->purgeFiles();
            $asset->forceDelete();
            $deleted++;
        }

        Log::info('media.bulk_delete', ['deleted' => $deleted, 'detached' => $detached, 'skipped' => $skipped]);

        $status = __('Deleted :deleted media file(s).', ['deleted' => $deleted]);
        if ($detached > 0) {
            $status .= ' '.__(':detached were in use and have been removed from where they were used.', ['detached' => $detached]);
        }
        if ($skipped > 0) {
            $status .= ' '.__(':skipped skipped because they are in use.', ['skipped' => $skipped]);
        }

        return redirect()
            ->route('admin.media')
            ->with('status', $status);
    }

    public function replace(Request $request, MediaAsset $mediaAsset): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'new_media_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($mediaAsset): void {
                    if ((int) $value === (int) $mediaAsset->id) {
                        $fail(__('New media must be different from the current asset.'));

                        return;
                    }

                    if (! MediaAsset::query()->whereKey($value)->whereNull('deleted_at')->exists()) {
                        $fail(__('The selected media does not exist.'));
                    }
                },
            ],
        ]);

        $updated = $this->usages->replaceAsset($mediaAsset->id, (int) $data['new_media_id']);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => __('Replaced :count usage(s).', ['count' => $updated]),
                'updated' => $updated,
            ]);
        }

        return redirect()
            ->route('admin.media')
            ->with('status', __('Replaced :count usage(s).', ['count' => $updated]));
    }

    public function usages(MediaAsset $mediaAsset): JsonResponse
    {
        $rows = $mediaAsset->usages()
            ->orderBy('field')
            ->get()
            ->map(fn ($usage) => [
                'id' => $usage->id,
                'usable_type' => $usage->usable_type,
                'usable_id' => $usage->usable_id,
                'field' => $usage->field,
                'created_at' => optional($usage->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $rows,
            'count' => $rows->count(),
        ]);
    }

    protected function filteredQuery(Request $request)
    {
        $query = MediaAsset::query()->latest('id');

        $q = $request->string('q')->trim()->toString();
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('original_name', 'like', '%'.$q.'%')
                    ->orWhere('alt', 'like', '%'.$q.'%')
                    ->orWhere('uuid', 'like', '%'.$q.'%');
            });
        }

        $type = $request->string('type')->trim()->toString();
        if ($type !== '' && MediaType::tryFrom($type)) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeAsset(MediaAsset $asset): array
    {
        $asset->loadMissing('variants');

        return [
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'type' => $asset->type?->value ?? $asset->type,
            'original_name' => $asset->original_name,
            'mime' => $asset->mime,
            'width' => $asset->width,
            'height' => $asset->height,
            'alt' => $asset->alt,
            'folder' => $asset->folder,
            'url' => $asset->url('medium'),
            'thumbnail_url' => $asset->thumbnailUrl(),
            'usage_count' => $asset->usages_count ?? $asset->usages()->count(),
            'variants' => $asset->variants->mapWithKeys(fn ($variant) => [
                $variant->key => [
                    'path' => $variant->path,
                    'width' => $variant->width,
                    'height' => $variant->height,
                    'url' => $asset->url($variant->key),
                ],
            ]),
        ];
    }
}
