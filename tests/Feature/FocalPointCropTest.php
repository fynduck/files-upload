<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

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
