<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Modifiers\Gd\BoxCoverModifier;
use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Geometry\Point;
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
        $source = Image::decodePath($this->gradientImage());

        $expected = (clone $source)->crop(240, 120, 35, 18)->resize(100, 50);
        $actual = (clone $source)->modify(new BoxCoverModifier(100, 50, new Size(240, 120, new Point(35, 18))));

        $this->assertSame($this->pixels($expected), $this->pixels($actual));
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
