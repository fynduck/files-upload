<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OptimizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_optimized_jpegs_are_progressive(): void
    {
        $this->uploadPhoto(optimize: true);

        $this->assertTrue($this->isProgressiveJpeg(Storage::disk('public')->get('photo.jpg')));
        $this->assertTrue($this->isProgressiveJpeg(Storage::disk('public')->get('sm/photo.jpg')));
    }

    public function test_without_optimizing_jpegs_are_baseline(): void
    {
        $this->uploadPhoto(optimize: false);

        $this->assertFalse($this->isProgressiveJpeg(Storage::disk('public')->get('sm/photo.jpg')));
    }

    public function test_optimizing_strips_metadata(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Writing a JPEG comment needs Imagick.');
        }
        config(['intervention-image.driver' => \Intervention\Image\Drivers\Imagick\Driver::class]);

        $imagick = new \Imagick();
        $imagick->newImage(120, 80, 'orange');
        $imagick->setImageFormat('png');
        $imagick->setImageProperty('comment', 'secret-location');
        $path = tempnam(sys_get_temp_dir(), 'meta') . '.png';
        $imagick->writeImage($path);

        UploadFile::file(new UploadedFile($path, 'meta.png', 'image/png', null, true))
            ->setName('meta')
            ->setSizes(['sm' => ['width' => 60, 'height' => 40]])
            ->setEncodeFormat('jpg')
            ->save('crop');

        $this->assertStringNotContainsString('secret-location', Storage::disk('public')->get('meta.jpg'));
        $this->assertStringNotContainsString('secret-location', Storage::disk('public')->get('sm/meta.jpg'));
    }

    public function test_a_size_the_source_already_fits_never_grows_past_the_source(): void
    {
        $image = imagecreatetruecolor(100, 50);
        imagefilledrectangle($image, 0, 0, 49, 49, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 50, 0, 99, 49, imagecolorallocate($image, 0, 0, 255));
        $path = tempnam(sys_get_temp_dir(), 'small') . '.png';
        imagepng($image, $path, 9);

        UploadFile::file(new UploadedFile($path, 'small.png', 'image/png', null, true))
            ->setName('small')
            ->setSizes(['large' => ['width' => 400, 'height' => 200]])
            ->setUpscale(false)
            ->save('crop');

        $original = Storage::disk('public')->get('small.png');
        $variant = Storage::disk('public')->get('large/small.png');
        $this->assertLessThanOrEqual(strlen($original), strlen($variant));
    }

    private function uploadPhoto(bool $optimize): void
    {
        UploadFile::file(UploadedFile::fake()->image('photo.png', 300, 200))
            ->setName('photo')
            ->setSizes(['sm' => ['width' => 120, 'height' => 80]])
            ->setEncodeFormat('jpg')
            ->setOptimize($optimize)
            ->save('crop');
    }

    /**
     * A progressive JPEG has a SOF2 (0xFFC2) frame header; baseline ones use SOF0.
     */
    private function isProgressiveJpeg(string $bytes): bool
    {
        return str_contains($bytes, "\xFF\xC2");
    }
}
