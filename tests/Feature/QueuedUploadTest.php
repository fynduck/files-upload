<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Jobs\GenerateImageSizesJob;
use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Bus\PendingBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class QueuedUploadTest extends TestCase
{
    private function uploader(UploadedFile $file): UploadFile
    {
        $upload = $this->getMockBuilder(UploadFile::class)
            ->setConstructorArgs([$file])
            ->onlyMethods(['isSupport'])
            ->getMock();
        $upload->method('isSupport')->willReturn(true);

        return $upload;
    }

    private function sizes(): array
    {
        return [
            'xs' => ['width' => 50, 'height' => 50],
            'md' => ['width' => 150, 'height' => 150],
            'lg' => ['width' => 300, 'height' => 300],
        ];
    }

    public function test_it_stores_the_original_and_batches_one_job_per_size(): void
    {
        Storage::fake('public');
        Bus::fake();

        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $name = $this->uploader($file)
            ->setDisk('public')
            ->setFolder('Post')
            ->setName('image')
            ->setSizes($this->sizes())
            ->queue()
            ->save();

        $this->assertSame('image.jpg', $name);
        Storage::disk('public')->assertExists('Post/image.jpg');

        Bus::assertBatched(static fn (PendingBatch $batch) => $batch->jobs->count() === 3);
    }

    public function test_it_dispatches_a_single_job_for_all_sizes_when_per_size_disabled(): void
    {
        Storage::fake('public');
        Bus::fake();

        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $this->uploader($file)
            ->setDisk('public')
            ->setFolder('Post')
            ->setName('image')
            ->setSizes($this->sizes())
            ->queue(perSize: false)
            ->save();

        Bus::assertDispatched(
            GenerateImageSizesJob::class,
            static fn (GenerateImageSizesJob $job) => count($job->sizes) === 3 && $job->dispatchEvent === true,
        );
        Bus::assertNothingBatched();
    }

    public function test_it_uses_the_disk_from_config_when_set_disk_is_not_called(): void
    {
        config(['files-upload.disk' => 'media', 'files-upload.optimize' => false]);
        Storage::fake('media');

        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $name = $this->uploader($file)
            ->setFolder('Post')
            ->setName('image')
            ->save();

        $this->assertSame('image.jpg', $name);
        Storage::disk('media')->assertExists('Post/image.jpg');
    }

    public function test_config_enables_background_mode_without_calling_queue(): void
    {
        config(['files-upload.queue.enabled' => true]);
        Storage::fake('public');
        Bus::fake();

        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $this->uploader($file)
            ->setDisk('public')
            ->setFolder('Post')
            ->setName('image')
            ->setSizes($this->sizes())
            ->save();

        Bus::assertBatched(static fn (PendingBatch $batch) => $batch->jobs->count() === 3);
    }
}
