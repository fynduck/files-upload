<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Exceptions\NotEnoughMemoryException;
use Fynduck\FilesUpload\ManipulationImage;
use Fynduck\FilesUpload\Modifiers\Gd\BoxCoverModifier;
use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Point;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Size;

class FocalPointCropTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_the_crop_follows_the_focal_point(): void
    {
        $this->uploadHalves('left', 0.0);
        $this->uploadHalves('right', 1.0);

        $this->assertSame('ff0000', $this->colorAtCentre('square/left.png'));
        $this->assertSame('0000ff', $this->colorAtCentre('square/right.png'));
    }

    public function test_every_size_gets_its_exact_ratio(): void
    {
        UploadFile::file($this->halvesImage())
            ->setName('ratios')
            ->setSizes([
                'wide' => ['width' => 120, 'height' => 60],
                'tall' => ['width' => 30, 'height' => 60],
            ])
            ->setFocalPoint(0.3, 0.5)
            ->setOptimize(false)
            ->save('crop');

        $this->assertSame([120, 60], $this->dimensions('wide/ratios.png'));
        $this->assertSame([30, 60], $this->dimensions('tall/ratios.png'));
    }

    public function test_without_upscaling_a_small_source_keeps_its_size_and_the_ratio(): void
    {
        UploadFile::file($this->halvesImage())
            ->setName('small')
            ->setSizes(['huge' => ['width' => 800, 'height' => 400]])
            ->setUpscale(false)
            ->setOptimize(false)
            ->save('crop');

        $this->assertSame([200, 100], $this->dimensions('huge/small.png'));
    }

    public function test_the_one_step_gd_cut_has_the_pixels_of_a_crop_then_resize(): void
    {
        $source = ImageManager::usingDriver(GdDriver::class)->decodePath($this->gradientImage());

        $expected = (clone $source)->crop(240, 120, 35, 18)->resize(100, 50);
        $actual = (clone $source)->modify(new BoxCoverModifier(100, 50, new Size(240, 120, new Point(35, 18))));

        $this->assertSame($this->pixels($expected), $this->pixels($actual));
    }

    public function test_gd_refuses_an_image_that_cannot_fit_in_the_memory_limit_before_decoding_it(): void
    {
        if (Image::getFacadeRoot()->driver->id() !== 'GD') {
            $this->markTestSkipped('Only GD decodes within the memory limit.');
        }

        $path = $this->blankImage(3000, 3000);

        $memoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 20 * 1048576));

        try {
            $this->expectException(NotEnoughMemoryException::class);
            $this->expectExceptionMessageMatches('/^Decoding the 3000x3000 image with GD needs about 43 MB, but only \d+ MB of the \d+ memory_limit is free\./');

            ManipulationImage::load($path)
                ->setFolder('large')
                ->setName('large')
                ->setSizes(['sm' => ['width' => 100, 'height' => 100]])
                ->save('crop');
        } finally {
            ini_set('memory_limit', $memoryLimit);
        }
    }

    public function test_gd_counts_the_free_memory_from_the_heap_php_has_reserved(): void
    {
        if (Image::getFacadeRoot()->driver->id() !== 'GD') {
            $this->markTestSkipped('Only GD decodes within the memory limit.');
        }

        $path = $this->blankImage(2000, 2000);

        // A long-running worker's heap: freed blocks stay reserved around the
        // few that are still in use, far above what memory_get_usage() reports.
        $strings = [];
        for ($index = 0; $index < 300000; $index++) {
            $strings[] = str_repeat('x', 100);
        }
        $pinned = array_filter($strings, static fn (int $index): bool => $index % 200 === 0, ARRAY_FILTER_USE_KEY);
        $strings = null;
        $this->assertGreaterThan(30 * 1048576, memory_get_usage(true) - memory_get_usage());

        $memoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1048576));

        try {
            $this->expectException(NotEnoughMemoryException::class);
            $this->expectExceptionMessageMatches('/^Decoding the 2000x2000 image with GD needs about 20 MB, but only [78] MB of the \d+ memory_limit is free\./');

            ManipulationImage::load($path)
                ->setFolder('large')
                ->setName('large')
                ->setSizes(['sm' => ['width' => 100, 'height' => 100]])
                ->save('crop');
        } finally {
            ini_set('memory_limit', $memoryLimit);
            unset($pinned);
        }
    }

    private function uploadHalves(string $name, float $focalX): void
    {
        UploadFile::file($this->halvesImage())
            ->setName($name)
            ->setSizes(['square' => ['width' => 50, 'height' => 50]])
            ->setFocalPoint($focalX, 0.5)
            ->setOptimize(false)
            ->save('crop');
    }

    /**
     * A black PNG of the given size, as a path.
     */
    private function blankImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $path = tempnam(sys_get_temp_dir(), 'large') . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * A 200×100 PNG: left half red, right half blue.
     */
    private function halvesImage(): UploadedFile
    {
        $image = imagecreatetruecolor(200, 100);
        imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 0, 255));

        $path = tempnam(sys_get_temp_dir(), 'halves') . '.png';
        imagepng($image, $path);

        return new UploadedFile($path, 'halves.png', 'image/png', null, true);
    }

    /**
     * A 300×200 PNG whose every pixel differs, so any shift or resampling shows.
     */
    private function gradientImage(): string
    {
        $image = imagecreatetruecolor(300, 200);
        for ($x = 0; $x < 300; $x++) {
            for ($y = 0; $y < 200; $y++) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $x % 256, $y, ($x * $y) % 256));
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'gradient') . '.png';
        imagepng($image, $path);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function pixels(ImageInterface $image): array
    {
        $pixels = [];
        for ($y = 0; $y < $image->height(); $y++) {
            for ($x = 0; $x < $image->width(); $x++) {
                $pixels[] = $image->colorAt($x, $y)->toHex();
            }
        }

        return $pixels;
    }

    private function colorAtCentre(string $path): string
    {
        $image = Image::decodeBinary(Storage::disk('public')->get($path));

        return $image->colorAt(intdiv($image->width(), 2), intdiv($image->height(), 2))->toHex();
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $path): array
    {
        $image = Image::decodeBinary(Storage::disk('public')->get($path));

        return [$image->width(), $image->height()];
    }
}
