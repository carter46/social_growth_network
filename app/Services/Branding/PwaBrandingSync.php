<?php

namespace App\Services\Branding;

use App\Models\MediaAsset;
use EragLaravelPwa\Services\PWAService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PwaBrandingSync
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Write favicon + PWA icon files from uploaded branding and refresh manifest.json.
     *
     * @param  array<string, mixed>|null  $branding
     */
    public function sync(?array $branding = null): bool
    {
        $branding ??= app(SiteBrandingRepository::class)->all();
        $this->lastError = null;

        try {
            $this->syncIcons($branding);
            $this->writeManifest($branding);

            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('pwa.branding_sync_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * URLs for &lt;link rel="icon"&gt; / apple-touch / manifest references.
     * Prefer live branding media when set — public/*.png can be overwritten by
     * git pull with the letter-7 fallback and cause tab-icon flicker.
     *
     * @return array{
     *     version: string,
     *     favicon: string,
     *     favicon16: string,
     *     apple: string,
     *     icon192: string,
     *     icon512: string,
     *     og: string,
     *     manifest: string
     * }
     */
    public function headIconUrls(?array $branding = null): array
    {
        $branding ??= app(SiteBrandingRepository::class)->all();
        $published = $this->publishedUrls($branding);
        $mediaUrl = $this->primaryBrandingMediaUrl($branding);

        if ($mediaUrl === null) {
            return $published;
        }

        $versioned = $this->appendVersion($mediaUrl, $published['version']);

        return [
            'version' => $published['version'],
            // Browser tab / apple icons: always the uploaded mark when available.
            'favicon' => $versioned,
            'favicon16' => $versioned,
            'apple' => $versioned,
            // PWA install icons still come from generated public files when present.
            'icon192' => $published['icon192'],
            'icon512' => $published['icon512'],
            'og' => $this->socialImageUrl($branding) ?? $published['og'],
            'manifest' => $published['manifest'],
        ];
    }

    /**
     * Absolute OG/Twitter image URL — prefer branding media over letter-7 og-image.png.
     */
    public function socialImageUrl(?array $branding = null): ?string
    {
        $branding ??= app(SiteBrandingRepository::class)->all();
        $mediaUrl = $this->primaryBrandingMediaUrl($branding, preferLogo: true);
        if ($mediaUrl !== null) {
            return $this->appendVersion($mediaUrl, $this->assetVersion($branding));
        }

        if (is_file(public_path('icons/og-image.png'))) {
            return $this->publishedUrls($branding)['og'];
        }

        return null;
    }

    /**
     * @return array{
     *     version: string,
     *     favicon: string,
     *     favicon16: string,
     *     apple: string,
     *     icon192: string,
     *     icon512: string,
     *     og: string,
     *     manifest: string
     * }
     */
    public function publishedUrls(?array $branding = null): array
    {
        $version = $this->assetVersion($branding);

        return [
            'version' => $version,
            'favicon' => asset('favicon-32x32.png').'?v='.$version,
            'favicon16' => asset('favicon-16x16.png').'?v='.$version,
            'apple' => asset('apple-touch-icon.png').'?v='.$version,
            'icon192' => asset('icons/icon-192x192.png').'?v='.$version,
            'icon512' => asset('icons/icon-512x512.png').'?v='.$version,
            'og' => asset('icons/og-image.png').'?v='.$version,
            'manifest' => asset('manifest.json').'?v='.$version,
        ];
    }

    public function iconsExist(): bool
    {
        return is_file(public_path('icons/icon-512x512.png'))
            && is_file(public_path('icons/icon-192x192.png'))
            && is_file(public_path('icons/icon-512x512-maskable.png'))
            && is_file(public_path('icons/icon-192x192-maskable.png'))
            && is_file(public_path('icons/og-image.png'))
            && is_file(public_path('apple-touch-icon.png'))
            && is_file(public_path('favicon-32x32.png'))
            && is_file(public_path('favicon.ico'));
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    public function shouldRegenerateIcons(array $branding): bool
    {
        if (! $this->iconsExist()) {
            return true;
        }

        $mediaId = (int) ($branding['favicon_media_id']
            ?? $branding['logo_light_media_id']
            ?? $branding['logo_dark_media_id']
            ?? 0);

        if ($mediaId <= 0) {
            return false;
        }

        $iconMtime = @filemtime(public_path('icons/icon-512x512.png'));
        if (! $iconMtime) {
            return true;
        }

        $asset = MediaAsset::query()->find($mediaId);

        return $asset?->updated_at?->getTimestamp() > $iconMtime;
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function syncIcons(array $branding): void
    {
        $iconsDir = public_path('icons');
        if (! File::isDirectory($iconsDir)) {
            File::makeDirectory($iconsDir, 0755, true);
        }

        $themeBg = $this->backgroundRgb();
        $mediaIds = $this->brandingMediaIds($branding);
        $sourcePath = $this->resolveBrandingMediaPath($branding);

        if ($sourcePath) {
            // Tight favicon / purpose:any — logo fills the canvas (not a white postage stamp).
            $this->writeTightPng($sourcePath, 512, $iconsDir.DIRECTORY_SEPARATOR.'icon-512x512.png');
            $this->writeTightPng($sourcePath, 192, $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192.png');
            $this->writeTightPng($sourcePath, 180, public_path('apple-touch-icon.png'));
            $this->writeTightPng($sourcePath, 32, public_path('favicon-32x32.png'));
            $this->writeTightPng($sourcePath, 16, public_path('favicon-16x16.png'));

            // Maskable: white background + W3C safe zone (~80% center circle).
            $this->writeMaskablePng($sourcePath, 512, $iconsDir.DIRECTORY_SEPARATOR.'icon-512x512-maskable.png');
            $this->writeMaskablePng($sourcePath, 192, $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192-maskable.png');

            $this->writeOgPng($sourcePath, $iconsDir.DIRECTORY_SEPARATOR.'og-image.png');
            $this->writeIco([
                public_path('favicon-16x16.png'),
                public_path('favicon-32x32.png'),
            ], public_path('favicon.ico'));

            File::copy(
                $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192.png',
                public_path('logo.png')
            );

            Log::info('pwa.branding_sync_icons_from_media', [
                'media_ids' => $mediaIds,
                'source' => basename($sourcePath),
            ]);

            return;
        }

        if ($mediaIds !== []) {
            throw new \RuntimeException(
                'Branding favicon/logo media is configured but the image file could not be read from storage. '.
                'Check storage/app/public (or the public/storage symlink), media disk paths, and PHP allow_url_fopen. '.
                'Icons were NOT overwritten with the letter-7 fallback.'
            );
        }

        // No branding media: never clobber existing icons with letter-7 (git pull + sync
        // used to wipe a good logo). Only seed fallbacks on a virgin public/ tree.
        if ($this->iconsExist()) {
            Log::info('pwa.branding_sync_icons_preserved', [
                'reason' => 'no_branding_media_configured',
            ]);

            return;
        }

        $this->writeFallbackSquare(512, $iconsDir.DIRECTORY_SEPARATOR.'icon-512x512.png', $themeBg);
        $this->writeFallbackSquare(192, $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192.png', $themeBg);
        $this->writeFallbackSquare(180, public_path('apple-touch-icon.png'), $themeBg);
        $this->writeFallbackSquare(32, public_path('favicon-32x32.png'), $themeBg);
        $this->writeFallbackSquare(16, public_path('favicon-16x16.png'), $themeBg);
        $this->writeFallbackMaskable(512, $iconsDir.DIRECTORY_SEPARATOR.'icon-512x512-maskable.png');
        $this->writeFallbackMaskable(192, $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192-maskable.png');
        $this->writeFallbackOg($iconsDir.DIRECTORY_SEPARATOR.'og-image.png', $themeBg);
        $this->writeIco([
            public_path('favicon-16x16.png'),
            public_path('favicon-32x32.png'),
        ], public_path('favicon.ico'));

        File::copy(
            $iconsDir.DIRECTORY_SEPARATOR.'icon-192x192.png',
            public_path('logo.png')
        );

        Log::warning('pwa.branding_sync_icons_letter_fallback', [
            'reason' => 'no_branding_media_and_missing_icons',
        ]);
    }

    /**
     * Resolve a readable path for uploaded branding media only (never static letter-7 assets).
     *
     * @param  array<string, mixed>  $branding
     */
    private function resolveBrandingMediaPath(array $branding): ?string
    {
        foreach ($this->brandingMediaIds($branding) as $mediaId) {
            $asset = MediaAsset::query()->with('variants')->find($mediaId);
            if (! $asset) {
                Log::warning('pwa.branding_media_missing', ['media_id' => $mediaId]);

                continue;
            }

            $sourcePath = $this->absolutePath($asset);
            if ($sourcePath && is_readable($sourcePath)) {
                return $sourcePath;
            }

            $fetched = $this->sourcePathFromMediaUrl($mediaId);
            if ($fetched) {
                return $fetched;
            }

            Log::warning('pwa.branding_media_unreadable', [
                'media_id' => $mediaId,
                'disk' => $asset->disk,
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function primaryBrandingMediaUrl(array $branding, bool $preferLogo = false): ?string
    {
        $keys = $preferLogo
            ? ['logo_light_media_id', 'favicon_media_id', 'logo_dark_media_id']
            : ['favicon_media_id', 'logo_light_media_id', 'logo_dark_media_id'];

        foreach ($keys as $key) {
            $id = (int) ($branding[$key] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $url = media_url_from_id($id, null, 'original');
            if (is_string($url) && $url !== '') {
                $absolute = absolute_url($url) ?? $url;

                return is_string($absolute) && $absolute !== '' ? $absolute : $url;
            }
        }

        return null;
    }

    private function appendVersion(string $url, string $version): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
    }

    /**
     * @param  array<string, mixed>  $branding
     * @return list<int>
     */
    private function brandingMediaIds(array $branding): array
    {
        $ids = [];

        foreach (['favicon_media_id', 'logo_light_media_id', 'logo_dark_media_id'] as $key) {
            $id = (int) ($branding[$key] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function sourcePathFromMediaUrl(int $mediaId): ?string
    {
        $url = media_url_from_id($mediaId, null, 'original');
        if (! is_string($url) || $url === '') {
            return null;
        }

        $local = $this->localPathFromPublicUrl($url);
        if ($local && is_readable($local)) {
            return $local;
        }

        $absolute = str_starts_with($url, 'http') ? $url : url($url);
        $bytes = @file_get_contents($absolute);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $destination = tempnam(sys_get_temp_dir(), 'pwa_icon_');
        if ($destination === false) {
            return null;
        }

        $path = $destination.'.png';
        @unlink($destination);

        if (@file_put_contents($path, $bytes) === false) {
            return null;
        }

        return is_readable($path) ? $path : null;
    }

    private function localPathFromPublicUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $relative = ltrim(substr($path, strlen('/storage/')), '/');
            $storagePath = Storage::disk('public')->path($relative);
            if (is_readable($storagePath)) {
                return $storagePath;
            }

            $publicCopy = public_path('storage/'.$relative);
            if (is_readable($publicCopy)) {
                return $publicCopy;
            }
        }

        $publicPath = public_path(ltrim($path, '/'));
        if (is_readable($publicPath)) {
            return $publicPath;
        }

        return null;
    }

    private function absolutePath(MediaAsset $asset): ?string
    {
        $asset->loadMissing('variants');

        foreach (['original', 'large', 'medium', 'small', 'thumbnail'] as $key) {
            $relative = $asset->variants->firstWhere('key', $key)?->path;
            if (! is_string($relative) || $relative === '') {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if (str_starts_with($relative, 'storage/')) {
                $relative = substr($relative, strlen('storage/'));
            }

            $disk = Storage::disk($asset->disk);
            if ($disk->exists($relative)) {
                try {
                    $local = $disk->path($relative);
                    if (is_readable($local)) {
                        return $local;
                    }
                } catch (Throwable) {
                    // Remote disks may not support path(); fall through to temp extract.
                }

                try {
                    $bytes = $disk->get($relative);
                    if (is_string($bytes) && $bytes !== '') {
                        $tmp = tempnam(sys_get_temp_dir(), 'pwa_media_');
                        if ($tmp !== false) {
                            $ext = pathinfo($relative, PATHINFO_EXTENSION) ?: 'png';
                            $path = $tmp.'.'.$ext;
                            @unlink($tmp);
                            if (@file_put_contents($path, $bytes) !== false && is_readable($path)) {
                                return $path;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('pwa.branding_media_disk_read_failed', [
                        'media_id' => $asset->id,
                        'path' => $relative,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $publicCopy = public_path('storage/'.$relative);
            if (is_readable($publicCopy)) {
                return $publicCopy;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function backgroundRgb(): array
    {
        $hex = ltrim((string) config('pwa.manifest.theme_color', '#004AC6'), '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [11, 106, 57];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @return \GdImage
     */
    private function openSource(string $sourcePath): mixed
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException('Unable to read branding image for PWA icons.');
        }

        $mime = $info['mime'] ?? null;
        $source = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => @imagecreatefromstring((string) file_get_contents($sourcePath)),
        };

        if ($source === false) {
            throw new \RuntimeException('Unable to open branding image for PWA icons.');
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        return $source;
    }

    /**
     * Tight square for favicon / purpose:any — logo fills most of the canvas.
     */
    private function writeTightPng(string $sourcePath, int $size, string $destination): void
    {
        $source = $this->openSource($sourcePath);
        $origW = imagesx($source);
        $origH = imagesy($source);

        // Fill ~94% of the canvas so the tab icon is the mark, not empty padding.
        $inner = (int) max(1, round($size * 0.94));
        $scale = min($inner / max(1, $origW), $inner / max(1, $origH));
        $dstW = max(1, (int) round($origW * $scale));
        $dstH = max(1, (int) round($origH * $scale));
        $dstX = (int) max(0, ($size - $dstW) / 2);
        $dstY = (int) max(0, ($size - $dstH) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $origW, $origH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write branding icon: '.$destination);
        }

        imagedestroy($source);
        imagedestroy($canvas);
    }

    /**
     * Maskable icon: white full-bleed background; logo inside ~80% safe zone.
     */
    private function writeMaskablePng(string $sourcePath, int $size, string $destination): void
    {
        $source = $this->openSource($sourcePath);
        $origW = imagesx($source);
        $origH = imagesy($source);

        // Safe zone ≈ circle of diameter 80% of canvas (W3C maskable icons).
        $safe = (int) max(1, round($size * 0.80));
        $scale = min($safe / max(1, $origW), $safe / max(1, $origH));
        $dstW = max(1, (int) round($origW * $scale));
        $dstH = max(1, (int) round($origH * $scale));
        $dstX = (int) max(0, ($size - $dstW) / 2);
        $dstY = (int) max(0, ($size - $dstH) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $white);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $origW, $origH);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write maskable branding icon: '.$destination);
        }

        imagedestroy($source);
        imagedestroy($canvas);
    }

    /**
     * Social OG image (1200×630) — branding mark on white, not the letter-7 tile.
     */
    private function writeOgPng(string $sourcePath, string $destination): void
    {
        $source = $this->openSource($sourcePath);
        $origW = imagesx($source);
        $origH = imagesy($source);

        $width = 1200;
        $height = 630;
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);

        $maxW = (int) round($width * 0.45);
        $maxH = (int) round($height * 0.55);
        $scale = min($maxW / max(1, $origW), $maxH / max(1, $origH));
        $dstW = max(1, (int) round($origW * $scale));
        $dstH = max(1, (int) round($origH * $scale));
        $dstX = (int) (($width - $dstW) / 2);
        $dstY = (int) (($height - $dstH) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $origW, $origH);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write OG image: '.$destination);
        }

        imagedestroy($source);
        imagedestroy($canvas);
    }

    /**
     * Write a multi-size ICO containing PNG images (supported by modern browsers).
     *
     * @param  list<string>  $pngPaths
     */
    private function writeIco(array $pngPaths, string $destination): void
    {
        $entries = [];
        foreach ($pngPaths as $path) {
            if (! is_readable($path)) {
                continue;
            }
            $bytes = file_get_contents($path);
            $info = @getimagesize($path);
            if ($bytes === false || $info === false) {
                continue;
            }
            $entries[] = [
                'width' => min(255, (int) ($info[0] ?? 32)),
                'height' => min(255, (int) ($info[1] ?? 32)),
                'bytes' => $bytes,
            ];
        }

        if ($entries === []) {
            throw new \RuntimeException('Unable to build favicon.ico — no PNG sources.');
        }

        $count = count($entries);
        $offset = 6 + ($count * 16);
        $dir = pack('vvv', 0, 1, $count);
        $imageData = '';

        foreach ($entries as $entry) {
            $size = strlen($entry['bytes']);
            $dir .= pack(
                'CCCCvvVV',
                $entry['width'] >= 256 ? 0 : $entry['width'],
                $entry['height'] >= 256 ? 0 : $entry['height'],
                0,
                0,
                1,
                32,
                $size,
                $offset
            );
            $imageData .= $entry['bytes'];
            $offset += $size;
        }

        if (@file_put_contents($destination, $dir.$imageData) === false) {
            throw new \RuntimeException('Unable to write favicon.ico');
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $bg
     */
    private function writeFallbackSquare(int $size, string $destination, array $bg): void
    {
        $canvas = imagecreatetruecolor($size, $size);
        $fill = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $fill);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $font = 5;
        $text = '7';
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        imagestring($canvas, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $white);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write fallback branding icon: '.$destination);
        }

        imagedestroy($canvas);
    }

    private function writeFallbackMaskable(int $size, string $destination): void
    {
        $canvas = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $white);

        $bg = $this->backgroundRgb();
        $green = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
        $safe = (int) round($size * 0.80);
        $pad = (int) (($size - $safe) / 2);
        imagefilledrectangle($canvas, $pad, $pad, $pad + $safe - 1, $pad + $safe - 1, $green);

        $ink = imagecolorallocate($canvas, 255, 255, 255);
        $font = 5;
        $text = '7';
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        imagestring($canvas, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $ink);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write fallback maskable icon: '.$destination);
        }

        imagedestroy($canvas);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $bg
     */
    private function writeFallbackOg(string $destination, array $bg): void
    {
        $width = 1200;
        $height = 630;
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);

        $mark = 220;
        $dx = (int) (($width - $mark) / 2);
        $dy = (int) (($height - $mark) / 2);
        $fill = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
        imagefilledrectangle($canvas, $dx, $dy, $dx + $mark - 1, $dy + $mark - 1, $fill);

        $ink = imagecolorallocate($canvas, 255, 255, 255);
        $font = 5;
        $text = '7';
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        imagestring($canvas, $font, (int) (($width - $tw) / 2), (int) (($height - $th) / 2), $text, $ink);

        if (! imagepng($canvas, $destination, 6)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Unable to write fallback OG image: '.$destination);
        }

        imagedestroy($canvas);
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function writeManifest(array $branding): void
    {
        $name = (string) ($branding['site_name'] ?: config('pwa.manifest.name'));
        $short = (string) (($branding['site_short_name'] ?? '') !== ''
            ? $branding['site_short_name']
            : $name);
        $description = (string) (($branding['meta_description'] ?? '') !== ''
            ? $branding['meta_description']
            : config('pwa.manifest.description'));

        $version = $this->assetVersion($branding);

        $manifest = array_merge(config('pwa.manifest', []), [
            'name' => $name,
            'short_name' => $short,
            'description' => $description,
            'background_color' => '#FFFFFF',
            'icons' => [
                [
                    'src' => '/icons/icon-512x512.png?v='.$version,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-192x192.png?v='.$version,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512x512-maskable.png?v='.$version,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/icons/icon-192x192-maskable.png?v='.$version,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/apple-touch-icon.png?v='.$version,
                    'sizes' => '180x180',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ]);

        config(['pwa.manifest' => $manifest]);
        app(PWAService::class)->createOrUpdate($manifest);
    }

    private function assetVersion(?array $branding = null): string
    {
        $times = [];

        $path = public_path('icons/icon-512x512.png');
        if (is_file($path)) {
            $times[] = (int) filemtime($path);
        }

        $branding ??= app(SiteBrandingRepository::class)->all();
        foreach ($this->brandingMediaIds($branding) as $mediaId) {
            $updated = MediaAsset::query()->whereKey($mediaId)->value('updated_at');
            if ($updated) {
                $times[] = \Illuminate\Support\Carbon::parse($updated)->getTimestamp();
            }
        }

        return (string) ($times !== [] ? max($times) : time());
    }
}
