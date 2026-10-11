<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EncodeFormatTest extends TestCase
{
    private const array SIZES = ['sm' => ['width' => 40, 'height' => 40]];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_an_encode_format_re_encodes_the_original_and_every_size(): void
    {
        $name = UploadFile::file(UploadedFile::fake()->image('photo.png', 120, 80))
            ->setFolder('articles')
            ->setName('photo')
            ->setSizes(self::SIZES)
            ->setEncodeFormat('webp')
            ->setOptimize(false)
            ->save('crop');

        $this->assertSame('photo.webp', $name);
        $this->assertSame('image/webp', Storage::disk('public')->mimeType('articles/photo.webp'));
        $this->assertSame('image/webp', Storage::disk('public')->mimeType('articles/sm/photo.webp'));
    }

    public function test_without_an_encode_format_the_original_is_kept_as_uploaded(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 120, 80);

        $name = UploadFile::file($file)
            ->setFolder('articles')
            ->setName('photo')
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->save('crop');

        $this->assertSame('photo.png', $name);
        $this->assertSame(file_get_contents($file->getRealPath()), Storage::disk('public')->get('articles/photo.png'));
        $this->assertSame('image/png', Storage::disk('public')->mimeType('articles/sm/photo.png'));
    }

    public function test_a_base64_image_is_re_encoded_to_the_requested_format(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 30, 30);
        $dataUri = 'data:image/png;base64,' . base64_encode(file_get_contents($file->getRealPath()));

        $name = UploadFile::file($dataUri)
            ->setName('inline')
            ->setEncodeFormat('jpg')
            ->setOptimize(false)
            ->save();

        $this->assertSame('inline.jpg', $name);
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType('inline.jpg'));
    }

    public function test_files_that_are_not_images_are_stored_as_uploaded_and_get_no_sizes(): void
    {
        $name = UploadFile::file(UploadedFile::fake()->create('clip.mp4', 20, 'video/mp4'))
            ->setFolder('banners')
            ->setName('clip')
            ->setSizes(self::SIZES)
            ->setEncodeFormat('webp')
            ->save();

        $this->assertSame('clip.mp4', $name);
        Storage::disk('public')->assertExists('banners/clip.mp4');
        Storage::disk('public')->assertMissing('banners/sm/clip.mp4');
    }
}
