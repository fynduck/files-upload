<?php

namespace Fynduck\FilesUpload\Tests\Unit;

use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFileTest extends TestCase
{
    public function test_it_uploads_file_using_legacy_set_extension_alias(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.png', 20, 20);

        $result = UploadFile::file($file)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('legacy-extension')
            ->setExtension('jpg')
            ->setOptimize(false)
            ->save();

        $this->assertSame('legacy_extension.jpg', $result);
        Storage::disk('public')->assertExists('uploads/legacy_extension.jpg');
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType('uploads/legacy_extension.jpg'));
    }

    public function test_it_requires_filename_before_save(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename is required.');

        UploadFile::file('invalid-payload')
            ->setDisk('public')
            ->setFolder('uploads')
            ->save();
    }

    public function test_it_rejects_invalid_file_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file format.');

        UploadFile::file('invalid-payload')
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('invalid')
            ->save();
    }

    public function test_it_rejects_invalid_base64_payload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base64 payload.');

        UploadFile::file('data:image/png;base64,not-valid-base64')
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('bad-base64')
            ->setExtension('png')
            ->save();
    }
}
