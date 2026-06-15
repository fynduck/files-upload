<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Data\ImageSize;
use Fynduck\FilesUpload\Data\ManipulationOptions;
use Fynduck\FilesUpload\Events\ImageSizesGenerated;
use Fynduck\FilesUpload\Jobs\GenerateImageSizesJob;
use Fynduck\FilesUpload\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class GenerateImageSizesJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rendering needs a real image driver; pick whichever extension is available.
        $driver = match (true) {
            extension_loaded('imagick') => \Intervention\Image\Drivers\Imagick\Driver::class,
            extension_loaded('gd')      => \Intervention\Image\Drivers\Gd\Driver::class,
            default                     => null,
        };

        if ($driver === null) {
            $this->markTestSkipped('No image driver (GD or Imagick) available to render variants.');
        }

        config(['image.driver' => $driver]);
    }

    public function test_it_renders_a_size_variant_and_fires_the_event(): void
    {
        Storage::fake('public');
        Event::fake([ImageSizesGenerated::class]);

        $source = \Intervention\Image\Laravel\Facades\Image::create(200, 200)
            ->encodeByExtension('jpg');
        Storage::disk('public')->put('src.jpg', (string) $source);

        $options = new ManipulationOptions(
            disk: 'public',
            folder: '',
            name: 'thumb',
            optimize: false,
            encode: 'jpg',
        );

        (new GenerateImageSizesJob(
            sourcePath: 'src.jpg',
            action: 'resize',
            options: $options,
            sizes: ['xs' => ImageSize::make(50, 50)],
            dispatchEvent: true,
        ))->handle();

        Storage::disk('public')->assertExists('xs/thumb.jpg');
        Event::assertDispatched(ImageSizesGenerated::class, static fn ($e) => $e->sizes === ['xs']);
    }
}
