<?php

namespace Tests\Unit;

use App\Services\Media\ScreenshotOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ScreenshotOptimizerTest extends TestCase
{
    public function test_screenshots_already_under_1mb_are_stored_unchanged(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required.');
        }

        Storage::fake('public');
        $source = tempnam(sys_get_temp_dir(), 'shot');
        $image = imagecreatetruecolor(80, 80);
        imagejpeg($image, $source, 85);
        imagedestroy($image);
        $original = (string) file_get_contents($source);

        $path = app(ScreenshotOptimizer::class)->store(
            new UploadedFile($source, 'small.jpg', 'image/jpeg', null, true),
            'task-proofs/test',
        );

        Storage::disk('public')->assertExists($path);
        $this->assertSame($original, Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(ScreenshotOptimizer::MAX_BYTES, Storage::disk('public')->size($path));
    }

    public function test_screenshots_between_1mb_and_2mb_are_reduced_under_1mb(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! class_exists(\Intervention\Image\ImageManager::class)) {
            $this->markTestSkipped('GD and Intervention Image are required.');
        }

        Storage::fake('public');
        $source = $this->jpegOfSize(ScreenshotOptimizer::MAX_BYTES + 1, ScreenshotOptimizer::ACCEPT_BYTES);
        $this->assertGreaterThan(ScreenshotOptimizer::MAX_BYTES, filesize($source));
        $this->assertLessThanOrEqual(ScreenshotOptimizer::ACCEPT_BYTES, filesize($source));

        $path = app(ScreenshotOptimizer::class)->store(
            new UploadedFile($source, 'proof.jpg', 'image/jpeg', null, true),
            'task-proofs/test',
        );

        $this->assertLessThanOrEqual(ScreenshotOptimizer::MAX_BYTES, Storage::disk('public')->size($path));
        $this->assertStringEndsWith('.jpg', $path);
    }

    public function test_screenshots_over_2mb_are_refused(): void
    {
        Storage::fake('public');
        $source = tempnam(sys_get_temp_dir(), 'shot');
        file_put_contents($source, random_bytes(ScreenshotOptimizer::ACCEPT_BYTES + 1000));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('2 MB');

        app(ScreenshotOptimizer::class)->store(
            new UploadedFile($source, 'huge.jpg', 'image/jpeg', null, true),
            'task-proofs/test',
        );
    }

    private function jpegOfSize(int $minBytes, int $maxBytes): string
    {
        $side = 1100;
        $raw = random_bytes($side * $side * 3);
        $image = imagecreatetruecolor($side, $side);
        $offset = 0;
        for ($y = 0; $y < $side; $y++) {
            for ($x = 0; $x < $side; $x++) {
                $color = (ord($raw[$offset++]) << 16) | (ord($raw[$offset++]) << 8) | ord($raw[$offset++]);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'shot');
        foreach ([95, 93, 90] as $quality) {
            imagejpeg($image, $path, $quality);
            $size = (int) filesize($path);
            if ($size >= $minBytes && $size <= $maxBytes) {
                imagedestroy($image);

                return $path;
            }
        }
        imagedestroy($image);

        $this->fail('Could not build a JPEG between '.$minBytes.' and '.$maxBytes.' bytes.');
    }
}
