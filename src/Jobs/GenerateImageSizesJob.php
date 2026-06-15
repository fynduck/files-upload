<?php

namespace Fynduck\FilesUpload\Jobs;

use Fynduck\FilesUpload\Data\ImageSize;
use Fynduck\FilesUpload\Data\ManipulationOptions;
use Fynduck\FilesUpload\Events\ImageSizesGenerated;
use Fynduck\FilesUpload\ManipulationImage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Generates one or more image size variants off the request thread.
 *
 * In per-size (batch) mode each job carries a single ImageSize; in single-job mode it
 * carries every size. The payload is fully serialisable (scalars + value objects).
 */
class GenerateImageSizesJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, ImageSize>  $sizes
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $action,
        public readonly ManipulationOptions $options,
        public readonly array $sizes,
        public readonly bool $dispatchEvent = false,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        ManipulationImage::load($this->sourcePath)
            ->withOptions($this->options)
            ->setSizes($this->sizes)
            ->save($this->action);

        if ($this->dispatchEvent) {
            ImageSizesGenerated::dispatch(
                $this->options->disk,
                $this->options->folder,
                $this->options->name,
                array_keys($this->sizes),
                $this->sourcePath,
            );
        }
    }

    public function failed(Throwable $e): void
    {
        logger()->error('FilesUpload: image size generation failed', [
            'source' => $this->sourcePath,
            'sizes'  => array_keys($this->sizes),
            'error'  => $e->getMessage(),
        ]);
    }
}
