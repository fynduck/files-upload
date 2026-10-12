<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Enums\ImageAction;
use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class BackgroundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_pad_fits_the_image_on_an_exact_canvas_filled_with_the_background(): void
    {
        UploadFile::file($this->redImage(200, 100))
            ->setName('padded')
            ->setSizes(['square' => ['width' => 100, 'height' => 100]])
            ->setBackground('#00ff00')
            ->setOptimize(false)
            ->save(ImageAction::Pad);

        $image = $this->decode('square/padded.png');

        $this->assertSame([100, 100], [$image->width(), $image->height()]);
        $this->assertSame('00ff00', $image->colorAt(50, 5)->toHex());
        $this->assertSame('ff0000', $image->colorAt(50, 50)->toHex());
    }

    public function test_pad_without_upscaling_keeps_a_small_source_at_its_size_on_the_canvas(): void
    {
        UploadFile::file($this->redImage(40, 20))
            ->setName('small')
            ->setSizes(['box' => ['width' => 100, 'height' => 100]])
            ->setBackground('#0000ff')
            ->setUpscale(false)
            ->setOptimize(false)
            ->save(ImageAction::Pad);

        $image = $this->decode('box/small.png');

        $this->assertSame([100, 100], [$image->width(), $image->height()]);
        $this->assertSame('ff0000', $image->colorAt(50, 50)->toHex());
        $this->assertSame('0000ff', $image->colorAt(5, 50)->toHex());
    }

    public function test_the_background_fills_transparency_when_encoding_to_jpg_and_does_not_leak(): void
    {
        UploadFile::file($this->transparentImage())
            ->setName('flattened')
            ->setSizes(['xs' => ['width' => 50, 'height' => 50]])
            ->setBackground('#ff0000')
            ->setEncodeFormat('jpg')
            ->setOptimize(false)
            ->save();

        $this->assertColorNear('ff0000', $this->decode('xs/flattened.jpg')->colorAt(25, 25)->toHex());
        $this->assertColorNear('ff0000', $this->decode('flattened.jpg')->colorAt(25, 25)->toHex());

        UploadFile::file($this->transparentImage())
            ->setName('default')
            ->setSizes(['xs' => ['width' => 50, 'height' => 50]])
            ->setEncodeFormat('jpg')
            ->setOptimize(false)
            ->save();

        $this->assertColorNear('ffffff', $this->decode('xs/default.jpg')->colorAt(25, 25)->toHex());
    }

    /**
     * JPEG is lossy: each channel may drift a few steps from the colour it was given.
     */
    private function assertColorNear(string $expectedHex, string $actualHex): void
    {
        foreach ([0, 2, 4] as $offset) {
            $this->assertEqualsWithDelta(
                hexdec(substr($expectedHex, $offset, 2)),
                hexdec(substr($actualHex, $offset, 2)),
                4,
                "Colour #{$actualHex} is not close to #{$expectedHex}.",
            );
        }
    }

    private function redImage(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 255, 0, 0));

        return $this->pngUpload($image, 'red.png');
    }

    /**
     * A fully transparent 50×50 PNG.
     */
    private function transparentImage(): UploadedFile
    {
        $image = imagecreatetruecolor(50, 50);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $this->pngUpload($image, 'transparent.png');
    }

    private function pngUpload(\GdImage $image, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'background') . '.png';
        imagepng($image, $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function decode(string $path): \Intervention\Image\Interfaces\ImageInterface
    {
        return Image::decodeBinary(Storage::disk('public')->get($path));
    }
}
