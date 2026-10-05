<?php

namespace App\Services\Branding;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Email clients (especially Gmail) mishandle WebP logos: the image proxy converts
 * WebP to JPEG and fills transparency with black. Prefer PNG/JPEG variants, or
 * materialize a cached PNG from WebP when that is all we have.
 */
class EmailLogoResolver
{
    private const EMAIL_SAFE_MIMES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
    ];

    private const VARIANT_PREFERENCE = [
        'original',
        'large',
        'medium',
        'small',
        'thumbnail',
        'thumb',
    ];

    public function absoluteUrl(?int $mediaId): ?string
    {
        if (! $mediaId) {
            return $this->staticFallbackUrl();
        }

        $asset = MediaAsset::query()->with('variants')->find($mediaId);
        if (! $asset) {
            return $this->staticFallbackUrl();
        }

        $safe = $this->firstEmailSafeVariant($asset);
        if ($safe) {
            return absolute_url($asset->url($safe->key)) ?? $this->staticFallbackUrl();
        }

        return $this->materializePngFromWebp($asset) ?? $this->staticFallbackUrl();
    }

    private function firstEmailSafeVariant(MediaAsset $asset): ?MediaVariant
    {
        $byKey = $asset->variants->keyBy('key');

        foreach (self::VARIANT_PREFERENCE as $key) {
            $variant = $byKey->get($key);
            if ($variant && $this->isEmailSafeMime((string) $variant->mime)) {
                return $variant;
            }
        }

        return $asset->variants->first(
            fn (MediaVariant $variant) => $this->isEmailSafeMime((string) $variant->mime)
        );
    }

    private function isEmailSafeMime(string $mime): bool
    {
        return in_array(strtolower(trim($mime)), self::EMAIL_SAFE_MIMES, true);
    }

    private function materializePngFromWebp(MediaAsset $asset): ?string
    {
        $webp = $asset->variants->first(
            fn (MediaVariant $variant) => str_contains(strtolower((string) $variant->mime), 'webp')
                || str_ends_with(strtolower((string) $variant->path), '.webp')
        );

        if (! $webp?->path) {
            return null;
        }

        $disk = Storage::disk($asset->disk);
        $cacheRel = 'media/email-safe/'.$asset->uuid.'.png';

        try {
            if (! $disk->exists($cacheRel)) {
                if (! $disk->exists($webp->path)) {
                    return null;
                }

                $png = $this->convertBinaryToPng($disk->get($webp->path));
                if ($png === null) {
                    return null;
                }

                $disk->put($cacheRel, $png);
            }

            if ($asset->disk === 'public') {
                return absolute_url('/storage/'.ltrim($cacheRel, '/'));
            }

            $url = $disk->url($cacheRel);

            return absolute_url(is_string($url) ? $url : null);
        } catch (Throwable $e) {
            Log::warning('email.logo_webp_to_png_failed', [
                'media_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function convertBinaryToPng(?string $binary): ?string
    {
        if (! extension_loaded('gd') || ! is_string($binary) || $binary === '') {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $tmp = tempnam(sys_get_temp_dir(), 'emlpng');
        if ($tmp === false) {
            imagedestroy($image);

            return null;
        }

        $ok = @imagepng($image, $tmp, 6);
        imagedestroy($image);
        if (! $ok) {
            @unlink($tmp);

            return null;
        }

        $png = @file_get_contents($tmp);
        @unlink($tmp);

        return is_string($png) && $png !== '' ? $png : null;
    }

    private function staticFallbackUrl(): ?string
    {
        if (is_file(public_path('logo.png'))) {
            return absolute_url('/logo.png');
        }

        return null;
    }
}
