<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Task screenshots must be stored under 1 MB. Files already at or under that
 * size are kept as uploaded. Files up to 2 MB are scaled and re-encoded,
 * starting at the highest quality that fits.
 */
class ScreenshotOptimizer
{
    public const MAX_BYTES = 1_048_576;

    public const ACCEPT_BYTES = 2_097_152;

    public function store(UploadedFile $file, string $directory): string
    {
        $bytes = (int) ($file->getSize() ?: filesize($file->getRealPath()) ?: 0);
        $directory = trim($directory, '/');

        if ($bytes > self::ACCEPT_BYTES) {
            throw new InvalidArgumentException('Screenshots must be 2 MB or smaller.');
        }

        if ($bytes > 0 && $bytes <= self::MAX_BYTES) {
            return $file->store($directory, 'public');
        }

        try {
            $binary = $this->fitUnderLimit($file->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('This screenshot could not be saved. Choose a JPG, PNG, or WEBP image.');
        }
        $path = $directory.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function fitUnderLimit(string $path): string
    {
        if (! extension_loaded('gd') || ! class_exists(ImageManager::class)) {
            throw new InvalidArgumentException('This screenshot is over 1 MB and could not be reduced on this server.');
        }

        $manager = new ImageManager(new GdDriver);
        $source = $manager->read($path);
        $width = max(1, $source->width());

        $best = null;
        foreach ([1.0, 0.9, 0.8, 0.7, 0.6, 0.5] as $scale) {
            foreach ([90, 86, 82] as $quality) {
                $image = $manager->read($path);
                $targetWidth = max(1, (int) round($width * $scale));
                if ($targetWidth < $width) {
                    $image->scale(width: $targetWidth);
                }
                $encoded = (string) $image->toJpeg($quality);
                if (strlen($encoded) <= self::MAX_BYTES) {
                    return $encoded;
                }
                $best = $encoded;
            }
        }

        if (is_string($best) && strlen($best) <= self::MAX_BYTES) {
            return $best;
        }

        throw new InvalidArgumentException('This screenshot could not be reduced under 1 MB. Choose a smaller image.');
    }
}
