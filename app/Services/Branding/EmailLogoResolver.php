<?php

namespace App\Services\Branding;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logo for email headers. Emails point at a dedicated route (/email-assets/logo) that
 * streams the bytes itself, so the image still loads when the public/storage symlink
 * is missing on shared hosting. Email clients (especially Gmail) mishandle WebP, so
 * only PNG/JPEG/GIF is served; WebP-only uploads are converted to a cached PNG.
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

    private const STATIC_FALLBACKS = [
        'logo.png',
        'assets/images/originla_logo.png',
    ];

    public function absoluteUrl(?int $mediaId): ?string
    {
        if ($this->logo($mediaId) === null) {
            return null;
        }

        $asset = $mediaId ? MediaAsset::query()->find($mediaId) : null;
        $version = $asset ? $asset->id.'-'.($asset->updated_at?->timestamp ?? 0) : 'static';

        return $this->baseUrl().route('email.logo', ['v' => $version], false);
    }

    /**
     * @return array{bytes: string, mime: string}|null
     */
    public function logo(?int $mediaId): ?array
    {
        $asset = $mediaId ? MediaAsset::query()->with('variants')->find($mediaId) : null;

        if ($asset) {
            $safe = $this->firstEmailSafeVariant($asset);
            if ($safe && ($bytes = $this->readFromDisk($asset->disk, (string) $safe->path)) !== null) {
                return ['bytes' => $bytes, 'mime' => $this->normalizeMime((string) $safe->mime)];
            }

            if (($png = $this->pngFromWebp($asset)) !== null) {
                return ['bytes' => $png, 'mime' => 'image/png'];
            }
        }

        foreach (self::STATIC_FALLBACKS as $relative) {
            $path = public_path($relative);
            if (is_file($path) && ($bytes = @file_get_contents($path)) !== false && $bytes !== '') {
                return ['bytes' => $bytes, 'mime' => 'image/png'];
            }
        }

        return null;
    }

    /**
     * APP_URL is used when it is a real public address; a local APP_URL (localhost, 127.0.0.1)
     * would give every inbox a broken image, so the current request host is used instead.
     */
    private function baseUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        $host = strtolower((string) parse_url($configured, PHP_URL_HOST));
        $isLocal = $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test');

        if ($isLocal && ! app()->runningInConsole() && request()->getHost() !== '') {
            $requestHost = strtolower(request()->getHost());
            if (! in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true)) {
                return rtrim(request()->getSchemeAndHttpHost(), '/');
            }
        }

        return $configured;
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

    private function normalizeMime(string $mime): string
    {
        $mime = strtolower(trim($mime));

        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }

    private function readFromDisk(string $disk, string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            $storage = Storage::disk($disk);
            if (! $storage->exists($path)) {
                return null;
            }
            $bytes = $storage->get($path);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function pngFromWebp(MediaAsset $asset): ?string
    {
        $webp = $asset->variants->first(
            fn (MediaVariant $variant) => str_contains(strtolower((string) $variant->mime), 'webp')
                || str_ends_with(strtolower((string) $variant->path), '.webp')
        );

        if (! $webp?->path) {
            return null;
        }

        $cacheRel = 'media/email-safe/'.$asset->uuid.'.png';

        try {
            if (($cached = $this->readFromDisk($asset->disk, $cacheRel)) !== null) {
                return $cached;
            }

            $source = $this->readFromDisk($asset->disk, (string) $webp->path);
            $png = $source !== null ? $this->convertBinaryToPng($source) : null;
            if ($png === null) {
                return null;
            }

            Storage::disk($asset->disk)->put($cacheRel, $png);

            return $png;
        } catch (Throwable $e) {
            Log::warning('email.logo_webp_to_png_failed', [
                'media_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function convertBinaryToPng(string $binary): ?string
    {
        if (! extension_loaded('gd')) {
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
}
