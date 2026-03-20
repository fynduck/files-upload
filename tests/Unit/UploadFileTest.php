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

        $file = UploadedFile::fake()->create('test.txt', 1, 'text/plain');
        $upload = $this->getMockBuilder(UploadFile::class)
            ->setConstructorArgs([$file])
            ->onlyMethods(['isSupport'])
            ->getMock();
        $upload->method('isSupport')->willReturn(true);

        $result = $upload
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('legacy-extension')
            ->setExtension('jpg')
            ->setOptimize(false)
            ->save();

        $this->assertSame('legacy_extension.jpg', $result);
        Storage::disk('public')->assertExists('uploads/legacy_extension.jpg');
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

        $upload = $this->getMockBuilder(UploadFile::class)
            ->setConstructorArgs(['data:image/png;base64,not-valid-base64'])
            ->onlyMethods(['isSupport'])
            ->getMock();
        $upload->method('isSupport')->willReturn(true);

        $upload
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('bad-base64')
            ->setExtension('png')
            ->save();
    }
}
